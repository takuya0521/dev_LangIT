<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Video\VideoCompleteRequest;
use App\Services\VideoProgressService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class VideoProgressController extends Controller
{
    public function complete(
        VideoCompleteRequest $request,
        int $video,
        VideoProgressService $service
    ) {
        $user      = $request->user();
        $watchTime = (int) $request->input('watch_time');

        try {
            [$status, $watchedRate] = $service->updateProgress($user, $video, $watchTime);

            return response()->json([
                'status'       => $status,       // "completed" | "learning"
                'watched_rate' => $watchedRate,  // 0.0〜1.0
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => __('api.video_progress.messages.invalid'), // 「視聴データが不正です。」
                'errors'  => [
                    'watch_time' => [$e->getMessage()],
                ],
            ], 400);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.video_progress.messages.not_found'), // 「この動画は現在利用できません。」
            ], 404);
        } catch (\Throwable $e) {
            Log::error('video.complete.failed', [
                'user_id'  => $user->id,
                'video_id' => $video,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('api.video_progress.messages.failed'),
            ], 500);
        }
    }
}