<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Video\VideoEventRequest;
use App\Models\Tenant\Progress;
use App\Services\VideoProgressService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class VideoEventController extends Controller
{
    public function __construct(
        private readonly VideoProgressService $videoProgressService,
    ) {
    }

    public function store(VideoEventRequest $request, int $video)
    {
        $user     = $request->user();
        $event    = $request->input('event');      // play / pause / seek
        $position = (int) $request->input('position');

        try {
            // 共通ロジックで Video / Chapter / Course を解決（存在チェック＋受講権限チェック）
            [$videoModel, $chapter, $course] = $this->videoProgressService
                ->resolveVideoForUserOrFail($user, $video);

            $duration = (int) $videoModel->duration;

            // progress 行を upsert
            $progress = Progress::firstOrNew([
                'user_id'   => $user->id,
                'course_id' => $course->id,
                'chapter_id'=> $chapter->id,
            ]);

            // 再生位置を 0〜duration の範囲にクリップ（最後に止まった位置としてそのまま保存）
            $position = (int) $position;
            $position = max(0, $position); // マイナス防止

            if ($duration > 0) {
                $position = min($position, $duration);
            }

            // 「一番先」ではなく「最後に止まった位置」で上書きする
            $progress->last_watch_position = $position;


            // event 種別も保持したければ、カラムを追加した上でここでセット
            // $progress->last_event_type = $event;

            $progress->save();

            return response()->json([
                'message' => 'OK',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.video_progress.messages.not_found'),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('video.event.failed', [
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