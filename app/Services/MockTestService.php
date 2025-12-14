<?php

namespace App\Services;

use App\Models\Tenant\MockTest;
use App\Models\Tenant\MockTestResult;
use App\Models\Tenant\MockTestResultDetail;
use App\Models\Tenant\UserCourse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MockTestService
{
    /**
     * 受験権限チェック（受講コースに紐づくこと）
     */
    public function assertAccessible(MockTest $mockTest, int $userId): void
    {
        $has = UserCourse::query()
            ->where('user_id', $userId)
            ->where('course_id', $mockTest->course_id)
            ->exists();

        if (! $has) {
            // 仕様により 403/404 どちらでも良いが、情報漏えい防止で 404 に寄せる
            throw new NotFoundHttpException('模擬試験が見つかりません。');
        }
    }

    /**
     * API40: 模擬試験取得
     */
    public function getMockTestPayload(int $mockTestId, int $userId): array
    {
        $mockTest = MockTest::query()
            ->where('is_active', true)
            ->with(['questions.choices'])
            ->findOrFail($mockTestId);

        $this->assertAccessible($mockTest, $userId);

        return [
            'mock_test_id' => $mockTest->id,
            'title'        => $mockTest->title,
            'time_limit'   => $mockTest->time_limit_seconds,
            'questions'    => $mockTest->questions->map(function ($q) {
                return [
                    'question_id' => $q->id,
                    'text'        => $q->text,
                    'choices'     => $q->choices->map(fn ($c) => [
                        'choice_id' => $c->id,
                        'text'      => $c->text,
                    ])->values(),
                ];
            })->values(),
        ];
    }

    /**
     * API41: 採点＆保存
     * 未回答は許容（未回答=不正解扱い）
     */
    public function scoreAndStore(int $mockTestId, int $userId, array $answers): array
    {
        $mockTest = MockTest::query()
            ->where('is_active', true)
            ->with(['questions.choices'])
            ->findOrFail($mockTestId);

        $this->assertAccessible($mockTest, $userId);

        $answerMap = [];
        foreach ($answers as $a) {
            $answerMap[(int)$a['question_id']] = (int)$a['choice_id'];
        }

        $questions = $mockTest->questions;
        $total = $questions->count();
        if ($total === 0) {
            // 極端ケース：問題が0件なら 0点・不合格
            $score = 0;
            $pass = false;
            $details = [];
            $correctCount = 0;
        } else {
            $correctCount = 0;
            $details = [];

            foreach ($questions as $q) {
                $selectedChoiceId = $answerMap[$q->id] ?? null;

                $correctChoiceId = $q->choices->firstWhere('is_correct', true)?->id;
                $isCorrect = ($selectedChoiceId !== null && $correctChoiceId !== null && $selectedChoiceId === $correctChoiceId);

                // 不正形式（他の問題の選択肢IDなど）を弾く
                if ($selectedChoiceId !== null) {
                    $belongs = $q->choices->contains('id', $selectedChoiceId);
                    if (! $belongs) {
                        return [
                            'error' => [
                                'status' => 400,
                                'message' => '回答データが不正です。',
                            ],
                        ];
                    }
                }

                if ($isCorrect) {
                    $correctCount++;
                }

                $details[] = [
                    'question_id' => $q->id,
                    'correct'     => $isCorrect,
                ];

                $detailRows[] = [
                    'mock_test_question_id' => $q->id,
                    'selected_choice_id'    => $selectedChoiceId,
                    'is_correct'            => $isCorrect,
                    'correct_choice_id'     => $correctChoiceId,
                ];
            }

            $score = (int) floor(($correctCount / $total) * 100);
            $pass = ($score >= (int) $mockTest->pass_score);
        }

        Log::info('mock_test.score', [
            'user_id' => $userId,
            'mock_test_id' => $mockTestId,
            'answers_count' => count($answers),
            'score' => $score,
            'pass' => $pass,
        ]);

        return DB::transaction(function () use ($mockTest, $userId, $score, $pass, $correctCount, $total, $detailRows) {
            $result = MockTestResult::create([
                'mock_test_id'     => $mockTest->id,
                'user_id'          => $userId,
                'score'            => $score,
                'pass'             => $pass,
                'correct_count'    => $correctCount,
                'total_questions'  => $total,
            ]);

            foreach ($detailRows ?? [] as $row) {
                MockTestResultDetail::create([
                    'mock_test_result_id'    => $result->id,
                    'mock_test_question_id'  => $row['mock_test_question_id'],
                    'selected_choice_id'     => $row['selected_choice_id'],
                    'is_correct'             => $row['is_correct'],
                    'correct_choice_id'      => $row['correct_choice_id'],
                ]);
            }

            return [
                'mock_test_result_id' => $result->id,
                'score'   => $score,
                'pass'    => $pass,
                'details' => collect($detailRows ?? [])->map(fn ($r) => [
                    'question_id' => $r['mock_test_question_id'],
                    'correct'     => (bool) $r['is_correct'],
                ])->values(),
            ];
        });
    }

    /**
     * F06_02（任意）：直近結果
     */
    public function latestResult(int $mockTestId, int $userId): ?MockTestResult
    {
        $mockTest = MockTest::findOrFail($mockTestId);
        $this->assertAccessible($mockTest, $userId);

        return MockTestResult::query()
            ->where('mock_test_id', $mockTestId)
            ->where('user_id', $userId)
            ->with('details')
            ->latest()
            ->first();
    }
<<<<<<< Updated upstream
=======

    public function getLatestResultForUser(int $userId, int $mockTestId): ?MockTestResult
    {
        return MockTestResult::query()
            ->where('user_id', $userId)
            ->where('mock_test_id', $mockTestId)
            ->latest('id')
            ->with([
                'mockTest:id,title,time_limit',
                'details:mock_test_result_id,mock_test_question_id,selected_choice_id,is_correct,correct_choice_id',
                'details.question:id,mock_test_id,text',
                'details.selectedChoice:id,mock_test_question_id,text',
                'details.correctChoice:id,mock_test_question_id,text',
            ])
            ->first();
    }

    public function getResultByIdForUser(int $userId, int $resultId): ?MockTestResult
    {
        return MockTestResult::query()
            ->where('id', $resultId)
            ->where('user_id', $userId)
            ->with([
                'mockTest:id,title,time_limit',
                'details:mock_test_result_id,mock_test_question_id,selected_choice_id,is_correct,correct_choice_id',
                'details.question:id,mock_test_id,text',
                'details.selectedChoice:id,mock_test_question_id,text',
                'details.correctChoice:id,mock_test_question_id,text',
            ])
            ->first();
    }

    public function listResultsForUser(int $userId, int $mockTestId, int $limit = 20)
    {
        return MockTestResult::query()
            ->where('user_id', $userId)
            ->where('mock_test_id', $mockTestId)
            ->latest('id')
            ->limit($limit)
            ->get(['id','mock_test_id','score','pass','created_at']);
    }

    public function formatResultResponse(MockTestResult $result): array
    {
        $total = $result->details->count();
        $correct = $result->details->where('is_correct', true)->count();

        return [
            'mock_test_result_id' => $result->id,
            'mock_test_id' => $result->mock_test_id,
            'title' => optional($result->mockTest)->title,
            'time_limit' => optional($result->mockTest)->time_limit,
            'score' => $result->score,
            'pass' => (bool) $result->pass,
            'correct_count' => $correct,
            'total_questions' => $total,
            'submitted_at' => optional($result->created_at)?->toISOString(),
            'details' => $result->details->map(function ($d) {
                return [
                    'question_id' => $d->mock_test_question_id,
                    'question_text' => optional($d->question)->text,
                    'selected_choice_id' => $d->selected_choice_id,
                    'selected_choice_text' => optional($d->selectedChoice)->text,
                    'correct_choice_id' => $d->correct_choice_id,
                    'correct_choice_text' => optional($d->correctChoice)->text,
                    'correct' => (bool) $d->is_correct,
                ];
            })->values(),
        ];
    }
>>>>>>> Stashed changes
}
