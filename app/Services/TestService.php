<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant\Test;
use App\Models\Tenant\TestResult;
use App\Models\Tenant\TestQuestion;
use App\Models\Tenant\TestChoice;
use App\Models\Tenant\Course;
use App\Models\Tenant\Chapter;
use App\Models\Tenant\Progress;
use App\Models\Tenant\UserCourse;
use App\Models\Tenant\TestAnswerDetail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TestService
{
    /** 合格ライン（%） */
    private const PASS_THRESHOLD = 60;

    /**
     * 小テスト取得（F05_01 / API30）
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function getTestForUserOrFail(
        User $user,
        int $testId,
        bool $randomOrder = false,
        ?int $limitQuestions = null,
        bool $onlyIncorrect = false,
        array $difficultyFilter = [],
        array $tagFilter = []
    ): array {
        /** @var Test $test */
        $test = Test::with([
            'chapter.course',
        ])->findOrFail($testId);

        $chapter = $test->chapter;
        $course  = $chapter?->course;

        if (!$chapter || !$course) {
            throw new ModelNotFoundException('chapter or course not found');
        }

        // 受講権限チェック
        $userCourseExists = UserCourse::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();

        if (!$userCourseExists) {
            // 受講していないコースのテスト → 404 相当とする
            throw new ModelNotFoundException('user_course not found');
        }

        // ベースとなる設問セット
        $questions = $test->questions()
            ->orderBy('sort_order')
            ->with([
                'choices' => function ($q) {
                    $q->orderBy('sort_order');
                },
                'tags',
                'relatedChapter',
                'relatedVideo',
            ])
            ->get();

        // 難易度フィルタ
        if (!empty($difficultyFilter)) {
            $questions = $questions
                ->whereIn('difficulty', $difficultyFilter)
                ->values();
        }

        // タグフィルタ（QuestionTag.name ベースで判定想定）
        if (!empty($tagFilter)) {
            $tagFilterLower = array_map('mb_strtolower', $tagFilter);

            $questions = $questions->filter(function (TestQuestion $q) use ($tagFilterLower) {
                $names = $q->tags->pluck('name')->map(fn ($n) => mb_strtolower($n ?? ''));
                return $names->intersect($tagFilterLower)->isNotEmpty();
            })->values();
        }

        // 誤答だけ再出題
        if ($onlyIncorrect) {
            /** @var TestResult|null $latestResult */
            $latestResult = TestResult::where('test_id', $test->id)
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();

            if (!$latestResult) {
                throw new InvalidArgumentException('まだ受験履歴がありません。');
            }

            $wrongQuestionIds = TestAnswerDetail::where('test_result_id', $latestResult->id)
                ->where('is_correct', false)
                ->pluck('question_id')
                ->all();

            if (empty($wrongQuestionIds)) {
                throw new InvalidArgumentException('前回は全問正解でした。誤答のみの再テストはありません。');
            }

            $questions = $questions
                ->whereIn('id', $wrongQuestionIds)
                ->values();
        }

        // この時点で何も残っていなければエラー
        if ($questions->isEmpty()) {
            throw new InvalidArgumentException('指定された条件に一致する問題がありません。');
        }

        // ランダム順
        if ($randomOrder) {
            $questions = $questions->shuffle()->values();
            $questions->each(function (TestQuestion $q) {
                $q->setRelation('choices', $q->choices->shuffle()->values());
            });
        }

        // 出題数制限
        if ($limitQuestions !== null && $limitQuestions > 0) {
            $questions = $questions->take($limitQuestions)->values();
        }

        return [
            'test_id'   => $test->id,
            'title'     => $test->title,
            'questions' => $questions->map(function (TestQuestion $q) {
                // 共通メタ情報を取得
                $meta = $this->buildQuestionMeta($q);

                return [
                    'question_id' => $q->id,
                    'text'        => $q->question_text,

                    // difficulty / tags / related をここでまとめて展開
                    ...$meta,

                    'choices'     => $q->choices
                        ->map(function (TestChoice $c) {
                            return [
                                'choice_id' => $c->id,
                                'text'      => $c->choice_text,
                            ];
                        })
                        ->values(),
                ];
            })->values(),
            // 付帯情報（フロント側が使いたければ使える）
            'filters'   => [
                'only_incorrect' => $onlyIncorrect,
                'difficulty'     => $difficultyFilter,
                'tags'           => $tagFilter,
                'random'         => $randomOrder,
                'limit'          => $limitQuestions,
            ],
        ];

    }

    /**
     * 自動採点（F05_02 / API31）
     *
     * @param array<int,array{question_id:int,choice_id:int}> $answers
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function scoreTestForUser(
        User $user,
        int $testId,
        array $answers,
        string $mode = 'normal',
        ?int $elapsedSeconds = null
    ): array {
        if (empty($answers)) {
            throw new InvalidArgumentException('回答が指定されていません。');
        }

        return DB::connection('tenant')->transaction(function () use ($user, $testId, $answers, $mode, $elapsedSeconds) {
            /** @var Test $test */
            $test = Test::with(['chapter.course'])->findOrFail($testId);

            $chapter = $test->chapter;
            $course  = $chapter?->course;

            if (!$chapter || !$course) {
                throw new ModelNotFoundException('chapter or course not found');
            }

            // 受講権限チェック
            $userCourseExists = UserCourse::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (!$userCourseExists) {
                throw new ModelNotFoundException('user_course not found');
            }

            /** @var \Illuminate\Support\Collection<int,TestQuestion> $questions */
            $questions = $test->questions()
                ->orderBy('sort_order')
                ->with(['choices', 'tags', 'relatedChapter', 'relatedVideo'])
                ->get();

            if ($questions->isEmpty()) {
                throw new InvalidArgumentException('設問が登録されていません。');
            }

            // question_id => choice_id に整形
            $answersByQuestion = [];
            foreach ($answers as $row) {
                if (!isset($row['question_id'], $row['choice_id'])) {
                    throw new InvalidArgumentException('answers の形式が不正です。');
                }
                $answersByQuestion[(int) $row['question_id']] = (int) $row['choice_id'];
            }

            if (count($answersByQuestion) !== $questions->count()) {
                throw new InvalidArgumentException('すべての設問に回答してください。');
            }

            // すでに合格済みかどうか
            $alreadyPassed = TestResult::where('test_id', $test->id)
                ->where('user_id', $user->id)
                ->where('is_passed', true)
                ->exists();

            $correctCount = 0;
            $details      = [];

            foreach ($questions as $question) {
                $userChoiceId = $answersByQuestion[$question->id] ?? null;

                /** @var TestChoice|null $userChoice */
                $userChoice = $question->choices->firstWhere('id', $userChoiceId);
                if (!$userChoice) {
                    throw new InvalidArgumentException("不正な選択肢が送信されました。(question_id={$question->id})");
                }

                /** @var TestChoice|null $correctChoice */
                $correctChoice = $question->choices->firstWhere('is_correct', true);
                if (!$correctChoice) {
                    throw new InvalidArgumentException("設問 {$question->id} に正解が設定されていません。");
                }

                $isCorrect = $userChoice->id === $correctChoice->id;
                if ($isCorrect) {
                    $correctCount++;
                }

                // ★ 共通メタ情報を取得
                $meta = $this->buildQuestionMeta($question);

                $details[] = [
                    'question_id'          => $question->id,
                    'question_text'        => $question->question_text,
                    'correct'              => $isCorrect,
                    'explanation'          => $question->explanation,
                    'user_choice_id'       => $userChoice->id,
                    'user_choice_text'     => $userChoice->choice_text,
                    'correct_choice_id'    => $correctChoice->id,
                    'correct_choice_text'  => $correctChoice->choice_text,

                    // difficulty / tags / related をまとめて展開
                    ...$meta,
                ];
            }

            $total    = $questions->count();
            $score    = (int) floor($correctCount * 100 / max($total, 1));
            $isPassed = $score >= self::PASS_THRESHOLD;

            $latestResult = null;

            // normal モードのときだけ履歴を保存＆進捗連携
            if ($mode === 'normal') {
                $latestResult = TestResult::create([
                    'test_id'         => $test->id,
                    'user_id'         => $user->id,
                    'score'           => $score,
                    'is_passed'       => $isPassed,
                    'elapsed_seconds' => $elapsedSeconds,
                ]);

                foreach ($details as $d) {
                    TestAnswerDetail::create([
                        'test_result_id' => $latestResult->id,
                        'question_id'    => $d['question_id'],
                        'choice_id'      => $d['user_choice_id'],
                        'is_correct'     => $d['correct'],
                    ]);
                }

                if ($isPassed && !$alreadyPassed) {
                    $this->markTestChapterCompleted($user, $chapter, $course);
                }
            }

            $nextAction = $this->buildNextAction($isPassed, $course, $chapter);

            return [
                'score'            => $score,
                'correct_count'    => $correctCount,
                'total_count'      => $total,
                'status'           => $isPassed ? 'passed' : 'failed',
                'pass_threshold'   => self::PASS_THRESHOLD,
                'already_passed'   => $alreadyPassed,
                'latest_result_id' => $latestResult?->id,
                'details'          => $details,
                'elapsed_seconds'  => $elapsedSeconds,
                'mode'             => $mode,
                'next_action'      => $nextAction,
            ];
        });
    }

    private function markTestChapterCompleted(User $user, Chapter $chapter, Course $course): void
    {
        Progress::updateOrCreate(
            [
                'user_id'    => $user->id,
                'course_id'  => $course->id,
                'chapter_id' => $chapter->id,
            ],
            [
                'is_completed' => true,
            ]
        );

        $this->updateUserCourseProgress($user, $course);
    }

    private function updateUserCourseProgress(User $user, Course $course): void
    {
        $totalChapters = Chapter::where('course_id', $course->id)->count();

        if ($totalChapters === 0) {
            return;
        }

        $completed = Progress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->count();

        $progressRate = (int) floor($completed * 100 / $totalChapters);

        /** @var UserCourse|null $userCourse */
        $userCourse = UserCourse::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$userCourse) {
            return;
        }

        $status = 'not_started';
        if ($progressRate === 0) {
            $status = 'not_started';
        } elseif ($progressRate === 100) {
            $status = 'completed';
        } else {
            $status = 'in_progress';
        }

        $userCourse->update([
            'progress_rate'   => $progressRate,
            'learning_status' => $status,
        ]);
    }

    /**
     * 質問ごとの共通メタ情報を組み立てるヘルパー
     */
    private function buildQuestionMeta(TestQuestion $question): array
    {
        // タグ: name が空のものは除外
        $tags = $question->relationLoaded('tags')
            ? $question->tags
                ->pluck('name')
                ->filter(fn ($name) => $name !== '')
                ->values()
                ->all()
            : [];

        $chapter = $question->relatedChapter;
        $video   = $question->relatedVideo;

        return [
            'difficulty' => $question->difficulty ?? 'normal',
            'tags'       => $tags,
            'related'    => [
                'has_recommendation' => (bool) ($chapter || $video),
                'chapter'            => $chapter ? [
                    'id'    => $chapter->id,
                    'title' => $chapter->title,
                ] : null,
                'video'              => $video ? [
                    'id'    => $video->id,
                    'title' => $video->title,
                    'url'   => $video->url,
                ] : null,
            ],
        ];
    }

    private function buildNextAction(bool $isPassed, Course $course, Chapter $chapter): array
    {
        if (!$isPassed) {
            return [
                'type'    => 'review',
                'message' => '解説を確認して復習してから、もう一度テストを受けましょう。',
            ];
        }

        $nextChapter = Chapter::where('course_id', $course->id)
            ->where('sort_order', '>', $chapter->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($nextChapter) {
            return [
                'type'    => 'go_next_chapter',
                'message' => '次のチャプターに進みましょう。',
                'chapter' => [
                    'id'    => $nextChapter->id,
                    'title' => $nextChapter->title,
                    'type'  => $nextChapter->chapter_type,
                ],
            ];
        }

        return [
            'type'    => 'course_completed',
            'message' => 'このコースはすべてのチャプターが完了しました。お疲れさまでした！',
        ];
    }
}
