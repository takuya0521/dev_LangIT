<?php

namespace App\Services;

use App\Models\Tenant\Progress;
use App\Models\Tenant\Chapter;
use App\Models\Tenant\UserCourse;

class ProgressRateService
{
    /**
     * 進捗率を計算する
     *
     * @param int      $userId    JWTから取得した user_id
     * @param int|null $courseId  null の場合は「受講中全コース」
     *
     * @return array<int, array{course_id:int, progress_rate:int}>
     */
    public function getProgressRates(int $userId, ?int $courseId = null): array
    {
        // ① このユーザーが受講中の course_id 一覧を取得
        $enrolledCourseIds = UserCourse::where('user_id', $userId)
            ->pluck('course_id');

        // 受講コースが1つもない
        if ($enrolledCourseIds->isEmpty()) {
            // course_id 指定あり → そのコースに対する進捗は 0% とみなす
            if ($courseId !== null) {
                return [
                    [
                        'course_id'     => $courseId,
                        'progress_rate' => 0,
                    ],
                ];
            }

            // 指定なし → 何も返さない
            return [];
        }

        // ② リクエスト course_id と受講関係のチェック
        if ($courseId !== null) {
            // 受講していないコースが指定された場合も 0% として返す
            if (! $enrolledCourseIds->contains($courseId)) {
                return [
                    [
                        'course_id'     => $courseId,
                        'progress_rate' => 0,
                    ],
                ];
            }

            // この1コースだけを対象にする
            $targetCourseIds = collect([$courseId]);
        } else {
            // 受講中の全コースを対象にする
            $targetCourseIds = $enrolledCourseIds;
        }

        // ③ 対象コースの総チャプター数を course_id 単位で集計
        $chaptersByCourse = Chapter::query()
            ->whereIn('course_id', $targetCourseIds)
            ->select('course_id')
            ->selectRaw('COUNT(*) as total_chapters')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        // 指定された course_id について chapters が1件も無い場合 → 0％
        if ($courseId !== null && ! $chaptersByCourse->has($courseId)) {
            return [
                [
                    'course_id'     => $courseId,
                    'progress_rate' => 0,
                ],
            ];
        }

        // course_id 未指定で、チャプターが存在するコースが1つも無い → 空配列
        if ($chaptersByCourse->isEmpty()) {
            return [];
        }

        $courseIds = $chaptersByCourse->keys();

        // ④ 対象ユーザーの完了チャプター数を course_id 単位で集計
        $completedByCourse = Progress::query()
            ->where('user_id', $userId)
            ->whereIn('course_id', $courseIds)
            ->where('is_completed', true)
            ->select('course_id')
            ->selectRaw('COUNT(*) as completed_chapters')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        // ⑤ progress_rate = (完了チャプター数 ÷ 総チャプター数) × 100 を計算
        $results = [];

        foreach ($courseIds as $cid) {
            $total = (int) $chaptersByCourse[$cid]->total_chapters;

            if ($total === 0) {
                $completed = 0;
                $rate      = 0;
            } else {
                $completed = (int) optional($completedByCourse->get($cid))->completed_chapters ?? 0;
                // 整数丸め（ここでは切り捨て）
                $rate      = (int) floor($completed * 100 / $total);
            }

            $results[] = [
                'course_id'     => (int) $cid,
                'progress_rate' => $rate,
            ];
        }

        return $results;
    }
}
