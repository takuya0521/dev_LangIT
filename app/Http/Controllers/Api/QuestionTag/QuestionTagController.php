<?php

namespace App\Http\Controllers\Api\QuestionTag;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuestionTag\QuestionTagStoreRequest;
use App\Http\Requests\Api\QuestionTag\QuestionTagUpdateRequest;
use App\Http\Requests\Api\QuestionTag\QuestionTagSyncRequest;
use App\Models\Tenant\QuestionTag;
use App\Models\Tenant\TestQuestion;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionTagController extends Controller
{
    /**
     * タグ一覧 API（学生 / 管理者共通）
     * GET /api/question-tags?keyword=xxx
     * GET /api/admin/question-tags?keyword=xxx
     */
    public function index(Request $request)
    {
        $keyword = $request->input('keyword');

        $query = QuestionTag::query()
            ->withCount('questions');

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        $tags = $query
            ->orderBy('name') // 並び順を name 昇順に統一
            ->get();

        return response()->json([
            'tags' => $tags->map(function (QuestionTag $tag) {
                return [
                    'id'              => $tag->id,
                    'name'            => $tag->name,
                    'slug'            => $tag->slug,
                    'questions_count' => (int) $tag->questions_count,
                    'created_at'      => optional($tag->created_at)->toIso8601String(),
                    'updated_at'      => optional($tag->updated_at)->toIso8601String(),
                ];
            })->values(),
        ], 200);
    }

    /**
     * タグ作成 API（管理者用）
     * POST /api/admin/question-tags
     */
    public function store(QuestionTagStoreRequest $request)
    {
        $validated = $request->validated();
        $name      = $validated['name'];

        // 日本語も区別したい slug 仕様：
        // Str::slug() 結果があればそれを使う / 空なら name をそのまま使う
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = $name;
        }

        $tag = QuestionTag::create([
            'name' => $name,
            'slug' => $slug,
        ]);

        return response()->json([
            'id'   => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ], 201);
    }

    /**
     * タグ更新 API（管理者用）
     * PUT /api/admin/question-tags/{tag}
     */
    public function update(QuestionTagUpdateRequest $request, int $tag)
    {
        try {
            /** @var QuestionTag $tagModel */
            $tagModel  = QuestionTag::findOrFail($tag);
            $validated = $request->validated();
            $name      = $validated['name'];

            // name 変更に合わせて slug も更新（日本語も区別）
            $slug = Str::slug($name);
            if ($slug === '') {
                $slug = $name;
            }

            $tagModel->update([
                'name' => $name,
                'slug' => $slug,
            ]);

            return response()->json([
                'id'   => $tagModel->id,
                'name' => $tagModel->name,
                'slug' => $tagModel->slug,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.question_tag.not_found'),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to update question tag', [
                'error'  => $e->getMessage(),
                'tag_id' => $tag,
            ]);

            return response()->json([
                'message' => __('api.question_tag.update_failed'),
            ], 500);
        }
    }

    /**
     * タグ削除 API（管理者用・使用中は 400）
     * DELETE /api/admin/question-tags/{tag}
     */
    public function destroy(int $tag)
    {
        try {
            /** @var QuestionTag $tagModel */
            $tagModel = QuestionTag::withCount('questions')->findOrFail($tag);

            if ($tagModel->questions_count > 0) {
                // 他の問題で使用中のタグは削除禁止
                return response()->json([
                    'message' => __('api.question_tag.in_use_cannot_delete'),
                ], 400);
            }

            $tagModel->delete();

            return response()->json([
                'message' => __('api.question_tag.deleted'),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.question_tag.not_found'),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to delete question tag', [
                'error'  => $e->getMessage(),
                'tag_id' => $tag,
            ]);

            return response()->json([
                'message' => __('api.question_tag.delete_failed'),
            ], 500);
        }
    }

    /**
     * 問題へのタグ付け（pivot 更新）
     * PUT /api/admin/tests/{test}/questions/{question}/tags
     * body: { "tag_ids": [1, 3, 5] }
     */
    public function syncQuestionTags(QuestionTagSyncRequest $request, int $test, int $question)
    {
        try {
            /** @var TestQuestion $questionModel */
            $questionModel = TestQuestion::where('test_id', $test)
                ->where('id', $question)
                ->firstOrFail();

            $tagIds = $request->validated()['tag_ids'] ?? [];

            // 存在しない tag_id が混ざっていないかチェック
            $existingIds = QuestionTag::whereIn('id', $tagIds)->pluck('id')->all();
            $missingIds  = array_values(array_diff($tagIds, $existingIds));

            if (!empty($missingIds)) {
                return response()->json([
                    'message' => __('api.common.validation_error'),
                    'errors'  => [
                        'tag_ids' => [__('api.question_tag.invalid_ids')],
                    ],
                ], 400);
            }

            // pivot 更新（空配列なら全解除）
            $questionModel->tags()->sync($tagIds);

            // 最新状態を返す
            $questionModel->load('tags');

            return response()->json([
                'question_id' => $questionModel->id,
                'test_id'     => $questionModel->test_id,
                'tags'        => $questionModel->tags->map(function (QuestionTag $tag) {
                    return [
                        'id'   => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                })->values(),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => __('api.test.not_found'),
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to sync question tags', [
                'error'       => $e->getMessage(),
                'test_id'     => $test,
                'question_id' => $question,
            ]);

            return response()->json([
                'message' => __('api.question_tag.sync_failed'),
            ], 500);
        }
    }

    /**
     * タグ別の正答率集計（学生向け）
     * GET /api/question-tags/stats
     *
     * クエリパラメータ:
     * - test_id   : 特定テストに絞る
     * - days      : 直近○日分に絞る（tr.created_at 基準）
     * - course_id : 「問題の related_chapter_id が属するコース」で絞る
     * - chapter_id: 「問題の related_chapter_id」で絞る
     *
     * ※ chapter / course フィルタは tests.chapter_id ではなく
     *    test_questions.related_chapter_id ベースで集計する仕様。
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $testId    = $request->query('test_id');
        $days      = $request->query('days');
        $courseId  = $request->query('course_id');
        $chapterId = $request->query('chapter_id');

        try {
            $query = DB::connection('tenant')
                ->table('question_tags as qt')
                ->join('question_tag_pivot as qtp', 'qtp.tag_id', '=', 'qt.id')
                ->join('test_questions as tq', 'tq.id', '=', 'qtp.question_id')
                ->join('test_answer_details as tad', 'tad.question_id', '=', 'tq.id')
                ->join('test_results as tr', 'tr.id', '=', 'tad.test_result_id')
                ->where('tr.user_id', $user->id);

            // --- 期間フィルタ（直近 N 日） ---
            if (!is_null($days)) {
                $daysInt = (int) $days;
                if ($daysInt > 0) {
                    $from = now()->subDays($daysInt);
                    $query->where('tr.created_at', '>=', $from);
                }
            }

            // --- テスト単位での絞り込み ---
            if (!is_null($testId)) {
                $query->where('tr.test_id', (int) $testId);
            }

            // --- コース / チャプター絞り込み（related_chapter_id ベース） ---
            // 「この問題はどのチャプター内容か？」＝ tq.related_chapter_id
            if (!is_null($chapterId) || !is_null($courseId)) {
                // 問題の related_chapter_id -> chapters.id
                $query->join('chapters as c', 'c.id', '=', 'tq.related_chapter_id');

                if (!is_null($chapterId)) {
                    $query->where('c.id', (int) $chapterId);
                }

                if (!is_null($courseId)) {
                    // chapters.course_id -> courses.id
                    $query->join('courses as co', 'co.id', '=', 'c.course_id')
                          ->where('co.id', (int) $courseId);
                }
            }

            $rows = $query
                ->groupBy('qt.id', 'qt.name', 'qt.slug')
                ->orderBy('qt.name')
                ->selectRaw('
                    qt.id   as tag_id,
                    qt.name as name,
                    qt.slug as slug,
                    COUNT(*) as total_answers,
                    SUM(CASE WHEN tad.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                ')
                ->get();

            $stats = $rows->map(function ($row) {
                $total    = (int) $row->total_answers;
                $correct  = (int) $row->correct_answers;
                $accuracy = $total > 0
                    ? (int) floor($correct * 100 / $total)
                    : 0;

                return [
                    'tag_id'          => (int) $row->tag_id,
                    'name'            => $row->name,
                    'slug'            => $row->slug,
                    'total_answers'   => $total,
                    'correct_answers' => $correct,
                    'accuracy'        => $accuracy,
                ];
            })->values();

            return response()->json([
                'stats' => $stats,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch tag stats', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'query'   => [
                    'test_id'    => $testId,
                    'days'       => $days,
                    'course_id'  => $courseId,
                    'chapter_id' => $chapterId,
                ],
            ]);

            return response()->json([
                'message' => __('api.question_tag.stats_failed'),
            ], 500);
        }
    }
}
