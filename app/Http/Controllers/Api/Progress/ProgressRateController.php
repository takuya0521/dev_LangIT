<?php

namespace App\Http\Controllers\Api\Progress;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Progress\ProgressRateRequest;
use App\Services\ProgressRateService;
use App\Models\Tenant\UserCourse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProgressRateController extends Controller
{
    public function __construct(
        private readonly ProgressRateService $progressRateService
    ) {
    }

    /**
     * F04_01 進捗率計算API（API22）
     *
     * GET /progress-rate?course_id={id?}
     */
    public function index(ProgressRateRequest $request)
    {
        $user     = $request->user(); // JWTミドルウェアがセット
        $courseId = $request->validated()['course_id'] ?? null;

        try {
            $rates = $this->progressRateService
                ->getProgressRates($user->id, $courseId);

                // 計算結果を user_courses.progress_rate に反映
            foreach ($rates as $row) {
                UserCourse::where('user_id', $user->id)
                    ->where('course_id', $row['course_id'])
                    ->update([
                        'progress_rate' => $row['progress_rate'],
                    ]);
            }

            // 監査ログ（成功）
            Log::info('progress_rate.success', [
                'user_id'    => $user->id,
                'course_id'  => $courseId,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Res（成功：HTTP200）：[{ "course_id": number, "progress_rate": number }]
            return response()->json($rates, Response::HTTP_OK);

        } catch (\Throwable $e) {
            // 監査ログ（失敗）
            Log::error('progress_rate.failed', [
                'user_id'    => $user?->id,
                'course_id'  => $courseId,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error'      => $e->getMessage(),
            ]);

            // 設計書のRes（5xx）に合わせたレスポンス
            return response()->json([
                'message' => __('api.progress.messages.failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
