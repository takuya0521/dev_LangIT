<?php

namespace App\Services;

use App\Models\Tenant\UserCourse;
use App\Models\Tenant\Course;
use App\Models\Tenant\Progress;
use App\Models\Tenant\Chapter;

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
        $userCourses = UserCourse::with('course')
            ->where('user_id', $userId)
            ->get();

        return $userCourses->map(function (UserCourse $uc) {
            return [
                'course_id'       => $uc->course_id,
                'title'           => $uc->course->title,
                'description'     => $uc->course->description,
                'thumbnail_url'   => $uc->course->thumbnail_url,
                'progress_rate'   => (int) $uc->progress_rate,
                'learning_status' => $uc->learning_status,
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

        // 各チャプターのステータス＋視聴情報を決定
        $chapters = $course->chapters->map(function (Chapter $chapter) use ($progressList) {
            /** @var \App\Models\Tenant\Progress|null $progress */
            $progress = $progressList->get($chapter->id);

            $isCompleted   = $progress && $progress->is_completed;
            $watchedRate   = $progress ? (float) $progress->watched_rate : 0.0;
            $watchedSec    = $progress ? (int)   $progress->watched_seconds : 0;
            $lastPosition  = $progress ? (int)   $progress->last_watch_position : 0;

            return [
                'chapter_id'          => $chapter->id,
                'title'               => $chapter->title,
                'chapter_type'        => $chapter->chapter_type,
                'status'              => $isCompleted ? 'completed' : 'not_started',
                'watched_rate'        => $watchedRate,      // 0.0〜1.0
                'watched_seconds'     => $watchedSec,       // 秒
                'last_watch_position' => $lastPosition,     // 秒
            ];
        })->all();

        // コース全体の進捗率は ProgressRateService で算出
        $rates = $this->progressRateService
            ->getProgressRates($userId, $course->id);

        $progressRate   = $rates[0]['progress_rate'] ?? 0;
        $learningStatus = self::decideLearningStatus($progressRate);

        return [
            'course_id'       => $course->id,
            'title'           => $course->title,
            'description'     => $course->description,
            'thumbnail_url'   => $course->thumbnail_url,
            'progress_rate'   => $progressRate,
            'learning_status' => $learningStatus,
            'chapters'        => $chapters,
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
}
