<?php

namespace App\Services;

use App\Models\Tenant\Video;
use App\Models\Tenant\Progress;
use App\Models\Tenant\UserCourse;
use App\Models\Tenant\Course;
use App\Models\Tenant\Chapter;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class VideoProgressService
{
    /**
     * 視聴完了とみなす閾値（80%）.
     */
    private const COMPLETE_THRESHOLD = 0.8;

    /**
     * 視聴進捗を更新して、ステータスと視聴率を返す.
     *
     * - 動画の存在チェック
     * - コース・チャプターの紐づきチェック
     * - user_courses による受講権限チェック
     * - progress テーブルの更新（watched_seconds / watched_rate / is_completed）
     * - user_courses.progress_rate / learning_status の更新
     *
     * @param  User  $user
     * @param  int   $videoId
     * @param  int   $watchTime
     * @return array{0:string,1:float} [status, watched_rate]
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function updateProgress(User $user, int $videoId, int $watchTime): array
    {
        if ($watchTime < 0) {
            throw new InvalidArgumentException('watch_time は 0 以上である必要があります。');
        }

        // 共通ロジックで Video / Chapter / Course を取得 + 受講権限チェック
        [$video, $chapter, $course] = $this->resolveVideoForUserOrFail($user, $videoId);

        $duration = (int) $video->duration;

        if ($duration <= 0) {
            throw new InvalidArgumentException('動画の総再生時間が不正です。');
        }

        // watch_time が異常に大きい（例：durationの10倍超え）は 400 + ログ
        if ($watchTime > $duration * 10) {
            Log::warning('video.watch_time.suspicious', [
                'user_id'    => $user->id,
                'video_id'   => $videoId,
                'watch_time' => $watchTime,
                'duration'   => $duration,
            ]);

            throw new InvalidArgumentException('watch_time が異常な値です。');
        }

        // 視聴率（0〜1.0）に正規化（1.0以上は 1.0 に丸め）
        $normalizedWatchTime = min($watchTime, $duration);
        $watchedRate         = min($normalizedWatchTime / $duration, 1.0);

        return DB::transaction(function () use (
            $user,
            $course,
            $chapter,
            $duration,
            $normalizedWatchTime,
            $watchedRate
        ) {
            $progress = Progress::firstOrNew([
                'user_id'   => $user->id,
                'course_id' => $course->id,
                'chapter_id'=> $chapter->id,
            ]);

            $alreadyCompleted = (bool) $progress->is_completed;

            // ---- duration 変更時ケア（既存値を新 duration に合わせてクリップ）----
            $progress->watched_seconds = min((int) $progress->watched_seconds, $duration);

            // ---- 今回の watch 情報で更新（値は「大きい方」を保持） ----
            $progress->watched_seconds = max(
                (int) $progress->watched_seconds,
                $normalizedWatchTime
            );

            $progress->watched_rate = max(
                (float) $progress->watched_rate,
                $watchedRate
            );

            // 一度 completed になったら、以降は維持
            if (! $alreadyCompleted && $progress->watched_rate >= self::COMPLETE_THRESHOLD) {
                $progress->is_completed = 1;
            }

            $progress->save();

            // ---- (1) user_courses の progress_rate / learning_status を更新 ----
            $this->updateUserCourseProgress($user, $course);

            $status = $progress->is_completed ? 'completed' : 'learning';

            return [$status, $progress->watched_rate];
        });
    }

    /**
     * Video / Chapter / Course を取得し、ユーザーの受講権限がなければ 404 を投げる共通ロジック.
     *
     * @param  User  $user
     * @param  int   $videoId
     * @return array{0:Video,1:Chapter,2:Course}
     *
     * @throws ModelNotFoundException
     */
    public function resolveVideoForUserOrFail(User $user, int $videoId): array
    {
        $video = Video::with('chapter.course')->find($videoId);

        if (! $video || ! $video->chapter || ! $video->chapter->course) {
            // 設計書上は 404 「この動画は現在利用できません。」
            throw new ModelNotFoundException('Video not found');
        }

        $chapter = $video->chapter;
        $course  = $chapter->course;

        // 受講権限チェック（user_courses に存在しない場合は閲覧不可）
        $isEnrolled = UserCourse::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();

        if (! $isEnrolled) {
            // 設計上は 404 と同等扱い（エラーメッセージは「この動画は現在利用できません。」）
            throw new ModelNotFoundException('Video not accessible for this user');
        }

        return [$video, $chapter, $course];
    }

    /**
     * user_courses.progress_rate / learning_status を更新する.
     *
     * - 対象コースのチャプター総数
     * - 完了チャプター数（progress.is_completed = true）
     * から進捗率を算出し、学習ステータスを決定する。
     */
    private function updateUserCourseProgress(User $user, Course $course): void
    {
        // 対象コースのチャプター総数
        $totalChapters = Chapter::where('course_id', $course->id)->count();

        if ($totalChapters <= 0) {
            $progressRate   = 0;
            $learningStatus = 'not_started';
        } else {
            // 完了チャプター数
            $completed = Progress::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('is_completed', true)
                ->count();

            $progressRate = (int) floor($completed * 100 / $totalChapters);

            // CourseProgressService::decideLearningStatus と同じロジック
            if ($progressRate <= 0) {
                $learningStatus = 'not_started';
            } elseif ($progressRate >= 100) {
                $learningStatus = 'completed';
            } else {
                $learningStatus = 'in_progress';
            }
        }

        // user_courses を更新（存在しない場合は何もしない）
        $userCourse = UserCourse::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $userCourse) {
            return;
        }

        $userCourse->progress_rate   = $progressRate;
        $userCourse->learning_status = $learningStatus;
        $userCourse->save();
    }
}
