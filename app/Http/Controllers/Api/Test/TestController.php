<?php

namespace App\Http\Controllers\Api\Test;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Test\TestShowRequest;
use App\Http\Requests\Api\Test\TestScoreRequest;
use App\Services\TestService;
use App\Models\User;
use App\Models\Tenant\Test;
use App\Models\Tenant\TestResult;
use App\Models\Tenant\UserCourse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TestController extends Controller
{
    public function __construct(
        private readonly TestService $testService
    ) {}

    /**
     * F05_01 小テスト受験 API30
     */
    public function show(TestShowRequest $request, int $test)
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $random        = $request->boolean('random', false);
            $limit         = $request->has('limit') ? (int) $request->input('limit') : null;
            $onlyIncorrect = $request->boolean('only_incorrect', false);
            $difficulty    = (array) $request->input('difficulty', []);
            $tags          = (array) $request->input('tags', []);

            $data = $this->testService->getTestForUserOrFail(
                $user,
                $test,
                $random,
                $limit,
                $onlyIncorrect,
                $difficulty,
                $tags
            );

            return response()->json($data, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.test.not_found'),
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => __('api.common.validation_error'),
                'errors'  => ['filters' => [$e->getMessage()]],
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Failed to get test', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'test_id' => $test,
            ]);

            return response()->json([
                'message' => __('api.test.fetch_failed'),
            ], 500);
        }
    }

    /**
     * F05_02 自動採点 API31
     */
    public function score(TestScoreRequest $request, int $test)
    {
        /** @var User $user */
        $user    = $request->user();
        $answers = $request->input('answers', []);

        // mode / elapsed_seconds を取得（任意）
        $mode           = $request->input('mode', 'normal');
        $elapsedSeconds = $request->input('elapsed_seconds');

        try {
            $result = $this->testService->scoreTestForUser(
                $user,
                $test,
                $answers,
                $mode,
                $elapsedSeconds
            );

            return response()->json($result, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.test.not_found'),
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => __('api.common.validation_error'),
                'errors'  => ['answers' => [$e->getMessage()]],
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Failed to score test', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'test_id' => $test,
            ]);

            return response()->json([
                'message' => __('api.test.score_failed'),
            ], 500);
        }
    }

    /**
     * 最新のテスト結果取得 API
     * GET /api/tests/{test}/result
     */
    public function latestResult(TestShowRequest $request, int $test)
    {
        /** @var User $user */
        $user = $request->user();

        try {
            // テスト + 受講権限チェック
            /** @var Test $testModel */
            $testModel = Test::with('chapter.course')->findOrFail($test);

            $chapter = $testModel->chapter;
            $course  = $chapter?->course;

            if (!$chapter || !$course) {
                throw new ModelNotFoundException('chapter or course not found');
            }

            $userCourseExists = UserCourse::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (!$userCourseExists) {
                throw new ModelNotFoundException('user_course not found');
            }

            /** @var TestResult|null $latest */
            $latest = TestResult::where('test_id', $testModel->id)
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();

            if (!$latest) {
                return response()->json([
                    'message' => __('api.test.result_not_found'),
                ], 404);
            }

            return response()->json([
                'test_id'        => $testModel->id,
                'score'          => $latest->score,
                'is_passed'      => (bool) $latest->is_passed,
                'pass_threshold' => 60,
                'taken_at'       => $latest->created_at?->toIso8601String(),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.test.not_found'),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch latest test result', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'test_id' => $test,
            ]);

            return response()->json([
                'message' => __('api.test.result_fetch_failed'),
            ], 500);
        }
    }
}
