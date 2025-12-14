<?php

namespace App\Services;

use App\Models\Tenant\UserCourse;
use App\Models\Tenant\Course;
use App\Models\Tenant\Progress;
use App\Models\Tenant\Chapter;
use App\Models\Tenant\CoursePath;

class CourseProgressService
{
    public function __construct(
        private readonly ProgressRateService $progressRateService,
    ) {
    }

    /**
     * コース一覧用（F02_01）：UserCourse ベースでまとめる
     */
    public function getCourseSummaryForUser(int $userId): array
    {
        $userCourses = UserCourse::with(['course.chapters'])
            ->where('user_id', $userId)
            ->get();

        // 🔹 受講コース（Course モデル）だけ取り出す
        $courseCollection = $userCourses->pluck('course')->filter();

        // 🔹 base_course_id ごとの「最新版 version」を計算（is_active のみ対象）
        $latestVersionMap = $courseCollection
            ->groupBy(function (Course $course) {
                // base_course_id が null の場合は自分の id をベースID扱い
                return $course->base_course_id ?: $course->id;
            })
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection $group */
                return $group->where('is_active', true)->max('version');
            });

        return $userCourses->map(function (UserCourse $uc) use ($userId, $latestVersionMap) {

            $course = $uc->course;

            $meta  = $this->getCourseMetaData($userId, $course);
            $stats = $this->getCourseStatistics($course);

            // 🔹チャプタータイトル連結
            $chapterTitles = $course->chapters
                ? $course->chapters->pluck('title')->implode(' ')
                : '';

            // 🔹最新版判定
            $baseId        = $course->base_course_id ?: $course->id;
            $latestVersion = $latestVersionMap->get($baseId);
            $isLatest      = $course->is_active && $latestVersion && $course->version === $latestVersion;

            return [
                'course_id'       => $course->id,
                'title'           => $course->title,
                'description'     => $course->description,
                'thumbnail_url'   => $course->thumbnail_url,
                'progress_rate'   => (int) $uc->progress_rate,
                'learning_status' => $uc->learning_status,

                // 🔹バージョン情報（STEP7）
                'base_course_id'  => $baseId,
                'version'         => (int) $course->version,
                'is_active'       => (bool) $course->is_active,
                'is_latest'       => $isLatest,

                // STEP1 メタ
                ...$meta,

                // STEP6 統計
                'stats'           => $stats,

                // 検索用
                'chapter_titles'  => $chapterTitles,
            ];

        })->all();
    }

    /**
     * F02_02 コース詳細用：
     * - チャプター一覧
     * - コース全体の進捗率
     * - 学習ステータス
     */
    public function getCourseDetailForUser(int $userId, Course $course): array
    {
        // チャプター一覧をソート順で取得
        $course->load(['chapters' => function ($q) {
            $q->orderBy('sort_order');
        }]);

        // 対象ユーザーの progress をまとめて取得して、chapter_id をキーにする
        $progressList = Progress::query()
            ->where('user_id', $userId)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('chapter_id');

        // 対象コースのチャプター ID 一覧
        $chapterIds = $course->chapters->pluck('id')->all();

        // チャプターごとの動画（Video）を事前取得＆groupBy
        $videosByChapter = \App\Models\Tenant\Video::whereIn('chapter_id', $chapterIds)
            ->get()
            ->groupBy('chapter_id');

        // チャプターごとのテスト（Test）を取得
        $tests = \App\Models\Tenant\Test::whereIn('chapter_id', $chapterIds)->get();
        $testsByChapter = $tests->groupBy('chapter_id');

        $testIds = $tests->pluck('id')->all();

        // テスト結果（TestResult）のうち、「ユーザーごとの履歴」を取得
        $allResults = ! empty($testIds)
            ? \App\Models\Tenant\TestResult::where('user_id', $userId)
                ->whereIn('test_id', $testIds)
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        // test_id ごとの「最新結果」
        $latestResultsByTest = $allResults
            ->unique('test_id')
            ->keyBy('test_id');

        // 一度でも合格した test_id の集合
        $passedTestIds = $allResults
            ->where('is_passed', true)
            ->pluck('test_id')
            ->unique();

        // チャプターのロック制御用：直前チャプターが completed かどうか
        $prevCompleted = true; // 1つ目はロックしない方針

        // 各チャプターのステータス＋サマリ情報を決定
        $chapters = $course->chapters->values()->map(
            function (Chapter $chapter, int $index) use (
                $progressList,
                $videosByChapter,
                $testsByChapter,
                $latestResultsByTest,
                $passedTestIds,
                &$prevCompleted
            ) {
                /** @var \App\Models\Tenant\Progress|null $progress */
                $progress = $progressList->get($chapter->id);

                $isCompleted   = $progress && $progress->is_completed;
                $watchedRate   = $progress ? (float) $progress->watched_rate : 0.0;
                $watchedSec    = $progress ? (int)   $progress->watched_seconds : 0;
                $lastPosition  = $progress ? (int)   $progress->last_watch_position : 0;

                // --- ① 推定学習時間（動画合計秒数） ---
                $chapterVideos = $videosByChapter->get($chapter->id) ?? collect();
                $estimatedTimeSeconds = (int) $chapterVideos->sum('duration');

                // --- ② テスト情報（最新スコア / 合否） ---
                $chapterTests = $testsByChapter->get($chapter->id) ?? collect();
                $test         = $chapterTests->first(); // 1チャプター1テスト前提

                $latestResult = $test
                    ? $latestResultsByTest->get($test->id)
                    : null;

                // 最新スコア（履歴の最後）
                $testScore = $latestResult
                    ? (int) $latestResult->score
                    : null;

                // 一度でも合格していれば「合格済み」
                $testPassed = $test
                    ? $passedTestIds->contains($test->id)
                    : false;

                // --- ③ チャプター単位の進捗率 ---
                if ($chapter->chapter_type === 'video') {
                    // 動画チャプターは視聴率ベース
                    $chapterProgressRate = $watchedRate;
                } elseif ($chapter->chapter_type === 'test') {
                    // テストチャプターは「一度でも合格していれば 1.0」
                    if ($test) {
                        $chapterProgressRate = $testPassed ? 1.0 : 0.0;
                    } else {
                        // テーブルにテスト行がない場合は completed フラグに従う
                        $chapterProgressRate = $isCompleted ? 1.0 : 0.0;
                    }
                } else {
                    // その他タイプは completed フラグで決定
                    $chapterProgressRate = $isCompleted ? 1.0 : 0.0;
                }

                // --- ④ ロック判定 ---
                // 「自分が未完了」かつ「直前チャプターが未完了」のときだけロック
                $isLocked = (!$isCompleted) && $index > 0 && ! $prevCompleted;

                // 次のチャプター用に更新
                $prevCompleted = $isCompleted;

                return [
                    'chapter_id'          => $chapter->id,
                    'title'               => $chapter->title,
                    'chapter_type'        => $chapter->chapter_type,
                    'status'              => $isCompleted ? 'completed' : 'not_started',
                    'watched_rate'        => $watchedRate,      // 0.0〜1.0
                    'watched_seconds'     => $watchedSec,       // 秒
                    'last_watch_position' => $lastPosition,     // 秒

                    // STEP5 追加項目
                    'estimated_time_seconds' => $estimatedTimeSeconds,
                    'chapter_progress_rate'  => $chapterProgressRate, // 0.0〜1.0
                    'test_latest_score'      => $testScore,           // int|null
                    'test_is_passed'         => $testPassed,          // bool
                    'is_locked'              => $isLocked,            // bool
                ];
            }
        )->all();

        // コース全体の進捗率は ProgressRateService で算出
        // コース全体の進捗率は ProgressRateService で算出
        $rates = $this->progressRateService
            ->getProgressRates($userId, $course->id);

        $progressRate   = $rates[0]['progress_rate'] ?? 0;
        $learningStatus = self::decideLearningStatus($progressRate);

        // コースメタ情報 & 統計
        $meta    = $this->getCourseMetaData($userId, $course);
        $stats   = $this->getCourseStatistics($course);
        $roadmap = $this->getCourseRoadmapForUser($userId, $course);

        // 🔹 STEP7: バージョン情報（詳細でも最新版判定して返す）
        $baseId = $course->base_course_id ?? $course->id;

        $latestVersion = Course::query()
            ->where(function ($q) use ($baseId) {
                $q->where('base_course_id', $baseId)
                ->orWhere('id', $baseId);
            })
            ->where('is_active', true)
            ->max('version');

        $isLatest = $course->is_active && $latestVersion && $course->version === $latestVersion;

        return [
            'course_id'       => $course->id,
            'title'           => $course->title,
            'description'     => $course->description,
            'thumbnail_url'   => $course->thumbnail_url,
            'progress_rate'   => $progressRate,
            'learning_status' => $learningStatus,

            // 🔽 ここ統一
            'base_course_id' => $baseId,
            'version'        => (int) ($course->version ?? 1),
            'is_active'      => (bool) $course->is_active,
            'is_latest'      => $isLatest,
            'published_at'   => $course->published_at,

            'meta'    => $meta,
            'stats'   => $stats,
            'roadmap' => $roadmap,
            'chapters' => $chapters,
        ];
    }

    /**
     * 進捗率から学習ステータスを決める「共通ロジック」
     */
    public static function decideLearningStatus(int $progressRate): string
    {
        if ($progressRate <= 0) {
            return 'not_started';
        }

        if ($progressRate >= 100) {
            return 'completed';
        }

        return 'in_progress';
    }

    public function getCourseMetaData(int $userId, Course $course): array
    {
        $courseId = $course->id;

        /** チャプター一覧 */
        $chapters = Chapter::where('course_id', $courseId)->get();
        $chapterIds = $chapters->pluck('id')->all();

        /** 進捗テーブル取得 */
        $progressList = Progress::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->get()
            ->keyBy('chapter_id');

        // --------- ① 完了チャプター数 ---------
        $completedChapters = $progressList->where('is_completed', true)->count();
        $remainingChapters = max(count($chapterIds) - $completedChapters, 0);

        // --------- ② 動画（Video）進捗 ---------
        $videos = \App\Models\Tenant\Video::whereIn('chapter_id', $chapterIds)->get();
        $completedVideos = $videos->filter(function ($video) use ($progressList) {
            return $progressList[$video->chapter_id]->is_completed ?? false;
        })->count();
        $remainingVideos = max(count($videos) - $completedVideos, 0);

        // --------- ③ テスト進捗 ---------
        $tests = \App\Models\Tenant\Test::whereIn('chapter_id', $chapterIds)->get();
        $completedTests = $tests->filter(function ($test) use ($userId) {
            return \App\Models\Tenant\TestResult::where('test_id', $test->id)
                ->where('user_id', $userId)
                ->where('is_passed', true)
                ->exists();
        })->count();
        $remainingTests = max(count($tests) - $completedTests, 0);

        // --------- ④ 最終学習日時 ---------
        $lastProgress = $progressList->sortByDesc('updated_at')->first();
        $lastTest     = \App\Models\Tenant\TestResult::where('user_id', $userId)
            ->whereIn('test_id', $tests->pluck('id'))
            ->orderByDesc('created_at')
            ->first();

        $lastActivityAt = collect([
            $lastProgress?->updated_at,
            $lastTest?->created_at,
        ])->filter()->sortDesc()->first();

        // --------- ⑤ 推定学習時間（総動画時間） ---------
        $estimatedLearningTime = $videos->sum('duration');

        return [
            'completed_chapters'      => $completedChapters,
            'remaining_chapters'      => $remainingChapters,
            'remaining_videos'        => $remainingVideos,
            'remaining_tests'         => $remainingTests,
            'last_activity_at'        => $lastActivityAt,
            'estimated_learning_time' => $estimatedLearningTime,
        ];
    }

    public function getCourseStatistics(Course $course): array
    {
        $courseId = $course->id;

        // ① 受講者数
        $enrolledUsers = UserCourse::where('course_id', $courseId)->count();

        // ② 完了ユーザー数
        $completedUsers = UserCourse::where('course_id', $courseId)
            ->where('learning_status', 'completed')
            ->count();

        $completionRate = $enrolledUsers > 0
            ? round(($completedUsers / $enrolledUsers) * 100, 2)
            : 0;

        // ③ テスト平均スコア（全受講者の test_results）
        // コース → chapters → tests → test_results
        $chapterIds = Chapter::where('course_id', $courseId)->pluck('id');

        $testIds = \App\Models\Tenant\Test::whereIn('chapter_id', $chapterIds)
            ->pluck('id');

        $avgScore = \App\Models\Tenant\TestResult::whereIn('test_id', $testIds)
            ->avg('score');

        return [
            'enrolled_users_count' => $enrolledUsers,
            'completion_rate'      => $completionRate,       // %
            'average_test_score'   => $avgScore ? round($avgScore, 2) : null,
        ];
    }

    public function getCourseTimeline(int $userId, Course $course): array
    {
        $courseId = $course->id;

        // コースのチャプター一覧を取得（タイトル参照用）
        $chapters = Chapter::where('course_id', $courseId)
            ->get()
            ->keyBy('id');

        // ---------- Progress 履歴（動画視聴） ----------
        $progressEvents = Progress::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->get()
            ->map(function ($p) use ($chapters) {
                return [
                    'event_type'      => 'video_progress',
                    'chapter_id'      => $p->chapter_id,
                    'chapter_title'   => $chapters[$p->chapter_id]?->title,
                    'watched_seconds' => $p->watched_seconds,
                    'watched_rate'    => $p->watched_rate,
                    'timestamp'       => $p->updated_at,
                ];
            });

        // ---------- テスト受験履歴 ----------
        $testResults = \App\Models\Tenant\TestResult::where('user_id', $userId)
            ->whereIn('test_id', function ($q) use ($courseId) {
                $q->select('id')
                ->from('tests')
                ->whereIn('chapter_id', function ($q2) use ($courseId) {
                    $q2->select('id')
                        ->from('chapters')
                        ->where('course_id', $courseId);
                });
            })
            ->get()
            ->map(function ($tr) use ($chapters) {

                $test = \App\Models\Tenant\Test::find($tr->test_id);

                return [
                    'event_type'    => 'test_result',
                    'chapter_id'    => $test->chapter_id,
                    'chapter_title' => $chapters[$test->chapter_id]?->title,
                    'test_id'       => $tr->test_id,
                    'score'         => $tr->score,
                    'is_passed'     => (bool) $tr->is_passed,
                    'timestamp'     => $tr->created_at,
                ];
            });

        // ---------- チャプター完了履歴 ----------
        $chapterCompleted = Progress::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('is_completed', true)
            ->get()
            ->map(function ($p) use ($chapters) {
                return [
                    'event_type'    => 'chapter_completed',
                    'chapter_id'    => $p->chapter_id,
                    'chapter_title' => $chapters[$p->chapter_id]?->title,
                    'timestamp'     => $p->updated_at,
                ];
            });

        // すべての履歴をまとめて時系列ソート
        $timeline = $progressEvents
            ->merge($testResults)
            ->merge($chapterCompleted)
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();

        return [
            'course_id' => $courseId,
            'timeline'  => $timeline,
        ];
    }

    public function getCourseRoadmapForUser(int $userId, Course $course): array
    {
        $courseId = $course->id;

        // このコースに「つながる」パス（前のコース）
        $prereqPaths = CoursePath::where('to_course_id', $courseId)
            ->orderBy('sort_order')
            ->get();

        // このコースから「次に進む」パス
        $nextPaths = CoursePath::where('from_course_id', $courseId)
            ->orderBy('sort_order')
            ->get();

        $prereqIds = $prereqPaths->pluck('from_course_id')->all();
        $nextIds   = $nextPaths->pluck('to_course_id')->all();

        $allCourseIds = array_values(array_unique(array_merge($prereqIds, $nextIds)));

        if (empty($allCourseIds)) {
            return [
                'position_in_path' => 1,
                'total_in_path'    => 1,
                'prerequisites'    => [],
                'next_courses'     => [],
            ];
        }

        // 対象コースたちをまとめて取得
        $courses = Course::whereIn('id', $allCourseIds)->get()->keyBy('id');

        // 受講状況（UserCourse）をまとめて取得
        $userCourses = UserCourse::where('user_id', $userId)
            ->whereIn('course_id', $allCourseIds)
            ->get()
            ->keyBy('course_id');


        // STEP7: このルートに登場するコース群の最新版マップ
        $latestVersionMap = $courses
            ->groupBy(function (Course $c) {
                return $c->base_course_id ?: $c->id;
            })
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection $group */
                return $group->where('is_active', true)->max('version');
            });

        $buildItem = function (int $id, int $sortOrder, string $direction) use ($courses, $userCourses, $latestVersionMap) {
            $c = $courses->get($id);
            if (! $c) {
                return null;
            }

            $uc = $userCourses->get($id);
            $progressRate   = $uc ? (int) $uc->progress_rate : 0;
            $learningStatus = $uc
                ? $uc->learning_status
                : self::decideLearningStatus($progressRate);

            $baseId        = $c->base_course_id ?: $c->id;
            $latestVersion = $latestVersionMap->get($baseId);
            $isLatest      = $c->is_active && $latestVersion && $c->version === $latestVersion;

            return [
                'course_id'       => $c->id,
                'title'           => $c->title,
                'description'     => $c->description,
                'thumbnail_url'   => $c->thumbnail_url,
                'sort_order'      => $sortOrder,
                'progress_rate'   => $progressRate,
                'learning_status' => $learningStatus,
                'direction'       => $direction,

                'base_course_id'  => $baseId,
                'version'         => (int) ($c->version ?? 1),
                'is_active'       => (bool) $c->is_active,
                'is_latest'       => $isLatest,
                'published_at'    => $c->published_at,
            ];
};

        // 前提コース一覧
        $prerequisites = array_values(array_filter(
            $prereqPaths->map(function (CoursePath $path) use ($buildItem) {
                return $buildItem($path->from_course_id, $path->sort_order, 'prerequisite');
            })->all()
        ));

        // 次に進むコース一覧
        $nextCourses = array_values(array_filter(
            $nextPaths->map(function (CoursePath $path) use ($buildItem) {
                return $buildItem($path->to_course_id, $path->sort_order, 'next');
            })->all()
        ));

        $positionInPath = count($prerequisites) + 1;
        $totalInPath    = count($prerequisites) + 1 + count($nextCourses);

        return [
            'position_in_path' => $positionInPath,
            'total_in_path'    => $totalInPath,
            'prerequisites'    => $prerequisites,
            'next_courses'     => $nextCourses,
        ];
    }

}
