<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Course\CourseIndexRequest;
use App\Models\Tenant\UserCourse;
use App\Models\Tenant\Course;
use App\Services\ProgressRateService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CourseController extends Controller
{
    public function __construct(
        private readonly ProgressRateService $progressRateService,
        private readonly CourseProgressService $courseProgressService,
    ) {
    }

    /**
     * F02_01 コース一覧取得（API10）
     *
     * GET /courses
     */
    public function index(CourseIndexRequest $request)
    {
        $user = $request->user(); // JWTミドルウェアがセット

        try {
            // 1) 受講可能コース（user_courses）を取得
            $userCourses = UserCourse::with('course')
                ->where('user_id', $user->id)
                ->get();

            // 0件の場合も正常扱い（フロント側で「受講中のコースがありません。」表示）
            if ($userCourses->isEmpty()) {
                Log::info('courses.index.success', [
                    'user_id'        => $user->id,
                    'ip'             => $request->ip(),
                    'user_agent'     => $request->userAgent(),
                    'courses_count'  => 0,
                ]);

                // 仕様上は [] だけ返せばOK。必要なら message を足してもよい。
                return response()->json([], Response::HTTP_OK);
            }

            // 2) レスポンス組み立て
            $results = $userCourses->map(function (UserCourse $uc) {
                $course = $uc->course;

                $rate = (int) ($uc->progress_rate ?? 0);

                return [
                    'course_id'       => $course->id,
                    'title'           => $course->title,
                    'description'     => $course->description,
                    'thumbnail_url'   => $course->thumbnail_url ?? null,
                    'progress_rate'   => $rate,
                    'learning_status' => CourseProgressService::decideLearningStatus($rate),
                ];
            })->values()->all();

            // 3) 監査ログ（成功）
            Log::info('courses.index.success', [
                'user_id'        => $user->id,
                'ip'             => $request->ip(),
                'user_agent'     => $request->userAgent(),
                'courses_count'  => count($results),
            ]);

            // Res（成功）：[{ course_id, title, progress_rate, description, thumbnail_url, learning_status }]
            // 設計書の例にならって配列そのものを返す
            return response()->json($results, Response::HTTP_OK);

        } catch (\Throwable $e) {
            // 監査ログ（失敗）
            Log::error('courses.index.failed', [
                'user_id'    => $user?->id,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error'      => $e->getMessage(),
            ]);

            // Res（5xx）：{ "message": "コース一覧を取得できませんでした。" }
            return response()->json([
                'message' => __('api.course.messages.list_failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * F02_02 コース詳細取得（API11想定）
     *
     * GET /courses/{course}
     */
    public function show(Request $request, int $courseId)
    {
        $user = $request->user();

        try {
            // 1) コース存在チェック
            $course = Course::find($courseId);

            if (! $course) {
                return response()->json([
                    'message' => __('api.course.messages.not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            // 2) 受講権限チェック（user_courses にレコードがあるか）
            $isEnrolled = UserCourse::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (! $isEnrolled) {
                return response()->json([
                    'message' => __('api.course.messages.forbidden'),
                ], Response::HTTP_FORBIDDEN);
            }

            // 3) コース詳細＋チャプター＋進捗を組み立て
            $detail = $this->courseProgressService
                ->getCourseDetailForUser($user->id, $course);

            // 監査ログ（成功）
            Log::info('course_detail.success', [
                'user_id'    => $user->id,
                'course_id'  => $course->id,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json($detail, Response::HTTP_OK);

        } catch (\Throwable $e) {
            // 監査ログ（失敗）
            Log::error('course_detail.failed', [
                'user_id'    => $user?->id,
                'course_id'  => $courseId ?? null,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('api.auth.messages.server_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}