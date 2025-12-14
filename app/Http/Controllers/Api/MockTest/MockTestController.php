<?php

namespace App\Http\Controllers\Api\MockTest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MockTest\MockTestScoreRequest;
use App\Services\MockTestService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MockTestController extends Controller
{
    public function __construct(
        private readonly MockTestService $mockTestService,
    ) {}

    // API40: GET /mock-tests/{mock_test}
    public function show(Request $request, int $mock_test)
    {
        $user = $request->user();

        try {
            $payload = $this->mockTestService->getMockTestPayload($mock_test, $user->id);
            return response()->json($payload, Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => '模擬試験が見つかりません。'], Response::HTTP_NOT_FOUND);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json(['message' => '模擬試験が見つかりません。'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return response()->json(['message' => '模擬試験情報を取得できませんでした。時間をおいて再度お試しください。'], 500);
        }
    }

    // API41: POST /mock-tests/{mock_test}/score
    public function score(MockTestScoreRequest $request, int $mock_test)
    {
        $user = $request->user();
        $answers = $request->input('answers', []);

        try {
            $res = $this->mockTestService->scoreAndStore($mock_test, $user->id, $answers);

            if (isset($res['error'])) {
                return response()->json([
                    'message' => $res['error']['message'],
                ], $res['error']['status']);
            }

            return response()->json($res, Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => '模擬試験が見つかりません。'], Response::HTTP_NOT_FOUND);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json(['message' => '模擬試験が見つかりません。'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return response()->json(['message' => '採点処理に失敗しました。時間をおいて再度お試しください。'], 500);
        }
    }

    // F06_02（任意）: GET /mock-tests/{mock_test}/result
    public function latestResult(Request $request, int $mock_test)
    {
        $user = $request->user();

        try {
            $result = $this->mockTestService->latestResult($mock_test, $user->id);

            if (! $result) {
                return response()->json(null, Response::HTTP_OK);
            }

            return response()->json([
                'mock_test_result_id' => $result->id,
                'score' => $result->score,
                'pass'  => $result->pass,
                'correct_count' => $result->correct_count,
                'total_questions' => $result->total_questions,
                'details' => $result->details->map(fn ($d) => [
                    'question_id' => $d->mock_test_question_id,
                    'correct' => (bool) $d->is_correct,
                ])->values(),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json(['message' => '結果を取得できませんでした。'], 500);
        }
    }

    public function results(Request $request, int $mockTestId)
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 100)); // 1〜100に丸める

        $items = $this->mockTestService->listResultsForUser($user->id, $mockTestId, $limit);

        // 一覧は軽く（detailsなし）
        return response()->json($items->map(function ($r) {
            return [
                'mock_test_result_id' => $r->id,
                'mock_test_id' => $r->mock_test_id,
                'score' => $r->score,
                'pass' => (bool) $r->pass,
                'submitted_at' => optional($r->created_at)?->toISOString(),
            ];
        })->values(), Response::HTTP_OK);
    }

    public function showResult(Request $request, int $resultId)
    {
        $user = $request->user();

        $result = $this->mockTestService->getResultByIdForUser($user->id, $resultId);

        if (!$result) {
            return response()->json(['message' => '結果が見つかりません。'], 404);
        }

        return response()->json($this->mockTestService->formatResultResponse($result), 200);
    }
}
