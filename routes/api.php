<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\AdminMfaController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Progress\ProgressRateController;
use App\Http\Controllers\Api\Course\CourseController;
use App\Http\Controllers\Api\Video\VideoEventController;
use App\Http\Controllers\Api\Video\VideoMetaController;
use App\Http\Controllers\Api\Video\VideoProgressController;
use App\Http\Controllers\Api\Test\TestController;
use App\Http\Controllers\Api\QuestionTag\QuestionTagController;
use App\Http\Controllers\Api\Auth\LoginHistoryController;
use App\Http\Controllers\Api\Admin\UserLoginHistoryController;
use App\Http\Controllers\Api\Platform\TenantController;
use App\Http\Controllers\Api\MockTest\MockTestController;


Route::post('auth/login', LoginController::class);

// 既存：パスワード変更
Route::middleware('auth.jwt')
    ->post('auth/password', [PasswordController::class, 'change']);

Route::post('auth/mfa/verify', [AdminMfaController::class, 'verify']);

// 追加：ログイン中ユーザー自身のログイン履歴取得
Route::middleware('auth.jwt')
    ->get('me/login-histories', [LoginHistoryController::class, 'index']);

Route::get('/debug/server-error', function () {
    throw new \RuntimeException('debug server error');
});

Route::middleware(['auth.jwt', 'role:student'])->group(function () {
    // F06_01 模擬試験設問取得（API40）
    Route::get('/mock-tests/{mock_test}', [MockTestController::class, 'show'])
        ->whereNumber('mock_test');

    // F06_01 採点（API41）
    Route::post('/mock-tests/{mock_test}/score', [MockTestController::class, 'score'])
        ->whereNumber('mock_test');

    // F06_02（任意）直近結果
    Route::get('/mock-tests/{mock_test}/result', [MockTestController::class, 'latestResult'])
        ->whereNumber('mock_test');
});


// --------------------------
// 問題タグ系 API
// --------------------------

// 受講者向け（一覧 & 正答率集計）
Route::middleware(['auth.jwt', 'role:student'])->group(function () {
    // タグ一覧（テスト一覧画面のフィルタ用）
    Route::get('/question-tags', [QuestionTagController::class, 'index']);

    // タグ別正答率集計
    Route::get('/question-tags/stats', [QuestionTagController::class, 'stats']);
});

// 管理画面向け（タグマスタ管理 & 問題への付与）
Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
    // タグ一覧（管理画面でも同じエンドポイントを共用）
    Route::get('/question-tags', [QuestionTagController::class, 'index']);

    // タグ作成
    Route::post('/question-tags', [QuestionTagController::class, 'store']);

    // タグ更新
    Route::put('/question-tags/{tag}', [QuestionTagController::class, 'update'])
        ->whereNumber('tag');

    // タグ削除（使用中は 400）
    Route::delete('/question-tags/{tag}', [QuestionTagController::class, 'destroy'])
        ->whereNumber('tag');

    // 問題にタグを付ける
    Route::put('/tests/{test}/questions/{question}/tags', [QuestionTagController::class, 'syncQuestionTags'])
        ->whereNumber('test')
        ->whereNumber('question');
});

Route::middleware(['auth.jwt', 'role:student'])->group(function () {
    // F05_01 小テスト受験(API30)
    Route::get('/tests/{test}', [TestController::class, 'show'])
        ->whereNumber('test');

    // F05_02 自動採点(API31)
    Route::post('/tests/{test}/score', [TestController::class, 'score'])
        ->whereNumber('test');

    // 最新のテスト結果取得
    Route::get('/tests/{test}/result', [TestController::class, 'latestResult'])
        ->whereNumber('test');

    // タグ一覧（フィルタ UI 用）
    Route::get('/question-tags', [QuestionTagController::class, 'index']);

    // タグ別正答率
    Route::get('/question-tags/stats', [QuestionTagController::class, 'stats']);
});

Route::middleware(['auth.jwt', 'role:student'])->group(function () {
    // F03_01 動画視聴イベント記録（API20）
    Route::post('/videos/{video}/event', [VideoEventController::class, 'store'])
        ->whereNumber('video');

    // F03_02 視聴完了・視聴時間更新（API21）
    Route::post('/videos/{video}/complete', [VideoProgressController::class, 'complete'])
        ->whereNumber('video');

    // メタAPI（読み取り専用）
    Route::get('/video', [VideoMetaController::class, 'show']);
});

Route::middleware(['auth.jwt', 'role:student'])->group(function () {
    // F04_01 進捗率計算API（API22）
    Route::get('/progress-rate', [ProgressRateController::class, 'index']);
});

Route::middleware(['auth.jwt', 'role:student'])->group(function () {
    // F02_01 コース一覧取得（API10）
    Route::get('/courses', [CourseController::class, 'index']);

    // F02_02 コース詳細取得（API11）
    Route::get('/courses/{course}', [CourseController::class, 'show'])
        ->whereNumber('course');

    Route::get('/courses/{course}/timeline', [CourseController::class, 'timeline'])
    ->whereNumber('course');

});

// 管理者用アカウント管理 API
Route::middleware(['auth.jwt', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        // 一覧取得（F01_03）
        Route::get('/users', [AdminUserController::class, 'index']);

        // CSVエクスポート / インポート
        Route::get('/users/export', [AdminUserController::class, 'export']);
        Route::post('/users/import', [AdminUserController::class, 'import']);

        // 利用停止（suspended）
        Route::post('/users/{id}/suspend', [AdminUserController::class, 'suspend']);

        // 退会（withdrawn ＋ left_at 設定）
        Route::post('/users/{id}/withdraw', [AdminUserController::class, 'withdraw']);

        // 完全削除（個人情報の匿名化 ＋ status=deleted）
        Route::post('/users/{id}/anonymize', [AdminUserController::class, 'anonymize']);

        // 編集（F01_04）
        Route::put('/users/{id}', [AdminUserController::class, 'update']);

        // 新規作成（F01_02）
        Route::post('/users', [AdminUserController::class, 'store']);

        // パスワードリセット（F01_06）
        Route::put('/users/{id}/password', [AdminUserController::class, 'resetPassword']);

        // 追加：指定ユーザーのログイン履歴取得
        Route::get('/users/{user}/login-histories', [UserLoginHistoryController::class, 'index']);
    });

// =====================================
// プラットフォーム用：テナント一覧 API
// =====================================
Route::middleware(['auth.jwt', 'role:admin'])
    ->prefix('platform')
    ->group(function () {
        // 運営スタッフ向け：テナント一覧
        Route::get('/tenants', [TenantController::class, 'index']);
    });
