<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Video\VideoShowRequest;
use App\Models\Tenant\Progress;
use App\Services\VideoProgressService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class VideoMetaController extends Controller
{
    public function __construct(
        private readonly VideoProgressService $videoProgressService,
    ) {
    }

    /**
     * 動画メタ情報取得API
     * GET /api/video?video_id={id}
     */
    public function show(VideoShowRequest $request)
    {
        $user    = $request->user();
        $videoId = (int) $request->query('video_id');

        try {
            // ★ 共通ロジックで Video / Chapter / Course を解決（存在チェック＋受講権限チェック）
            [$video, $chapter, $course] = $this->videoProgressService
                ->resolveVideoForUserOrFail($user, $videoId);

            $duration = (int) $video->duration;

            // 該当チャプターの progress から last_watch_position を取得
            $progress = Progress::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('chapter_id', $chapter->id)
                ->first();

            $lastPosition = $progress ? (int) $progress->last_watch_position : 0;

            // duration を超えていたらクリップ
            if ($duration > 0) {
                $lastPosition = min($lastPosition, $duration);
            }

            return response()->json([
                'video_url'           => $video->video_url,
                'duration'            => $duration,
                'title'               => $video->title,
                'last_watch_position' => $lastPosition,
            ], 200);
        } catch (ModelNotFoundException $e) {
            // 動画が無い or 受講権限なし
            return response()->json([
                'message' => __('api.video.messages.not_found'),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('video.meta.failed', [
                'user_id'  => $user->id,
                'video_id' => $videoId,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('api.video.messages.fetch_failed'),
            ], 500);
        }
    }
}
