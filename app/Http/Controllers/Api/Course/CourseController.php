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
    // app/Http/Controllers/Api/Course/CourseController.php
    public function index(CourseIndexRequest $request)
    {
        $user = $request->user(); // JWTミドルウェアがセット

        try {
            // 1) まずは「受講中コースのサマリ一覧」をサービスから取得
            //    → ここで STEP1 のメタや STEP6 の stats も入っている想定
            $courses = $this->courseProgressService
                ->getCourseSummaryForUser($user->id);

            // 0件ならそのまま返却（前と同じ方針）
            if (empty($courses)) {
                Log::info('courses.index.success', [
                    'user_id'       => $user->id,
                    'ip'            => $request->ip(),
                    'user_agent'    => $request->userAgent(),
                    'courses_count' => 0,
                ]);

                return response()->json([], Response::HTTP_OK);
            }

            // ============================
            // STEP3: 検索・フィルター
            // ============================
            $keyword        = $request->input('keyword');          // タイトル・説明に対するキーワード
            $learningStatus = $request->input('learning_status');  // not_started / in_progress / completed
            $minProgress    = $request->input('min_progress');     // 0〜100
            $maxProgress    = $request->input('max_progress');     // 0〜100
            $latestOnly = filter_var($request->input('latest_only', false), FILTER_VALIDATE_BOOL);

            $filtered = array_filter($courses, function (array $course) use (
                $keyword,
                $learningStatus,
                $minProgress,
                $maxProgress,
                $latestOnly,
            ) {
                if ($latestOnly && empty($course['is_latest'])) {
                    return false;
                }

                // --- keyword: title / description / chapter_titles に部分一致 ---
                if (!empty($keyword)) {
                    $haystack = implode(' ', [
                        $course['title']          ?? '',
                        $course['description']    ?? '',
                        $course['chapter_titles'] ?? '',  // 🔹追加
                    ]);

                    if (mb_stripos($haystack, $keyword) === false) {
                        return false;
                    }
                }

                // --- learning_status 完全一致 ---
                if (!empty($learningStatus)) {
                    if (($course['learning_status'] ?? null) !== $learningStatus) {
                        return false;
                    }
                }

                // --- min_progress ---
                if ($minProgress !== null && $minProgress !== '') {
                    if ((int)($course['progress_rate'] ?? 0) < (int)$minProgress) {
                        return false;
                    }
                }

                // --- max_progress ---
                if ($maxProgress !== null && $maxProgress !== '') {
                    if ((int)($course['progress_rate'] ?? 0) > (int)$maxProgress) {
                        return false;
                    }
                }

                return true;
            });

            // 添字を振り直す
            $filtered = array_values($filtered);

            // 3) ログ（成功）
            Log::info('courses.index.success', [
                'user_id'       => $user->id,
                'ip'            => $request->ip(),
                'user_agent'    => $request->userAgent(),
                'courses_count' => count($filtered),
                'keyword'       => $keyword,
                'learning_status' => $learningStatus,
                'min_progress'  => $minProgress,
                'max_progress'  => $maxProgress,
            ]);

            // Res：フィルタ済みコース一覧
            return response()->json($filtered, Response::HTTP_OK);

        } catch (\Throwable $e) {
            // 監査ログ（失敗）
            Log::error('courses.index.failed', [
                'user_id'    => $user?->id,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error'      => $e->getMessage(),
            ]);

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

    /**
     * STEP4：コース学習履歴（タイムライン）
     *
     * GET /courses/{course}/timeline
     */
    public function timeline(Request $request, int $courseId)
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

            // 2) 受講権限チェック
            $isEnrolled = UserCourse::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (! $isEnrolled) {
                return response()->json([
                    'message' => __('api.course.messages.forbidden'),
                ], Response::HTTP_FORBIDDEN);
            }

            // 3) タイムライン取得
            $timeline = $this->courseProgressService
                ->getCourseTimeline($user->id, $course);

            Log::info('course_timeline.success', [
                'user_id'    => $user->id,
                'course_id'  => $course->id,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json($timeline, Response::HTTP_OK);

        } catch (\Throwable $e) {

            Log::error('course_timeline.failed', [
                'user_id'    => $user?->id,
                'course_id'  => $courseId,
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