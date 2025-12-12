<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetTenant;
use App\Http\Middleware\AuthenticateWithJwt; // ★ 追加
use App\Http\Middleware\CheckRole;          // ★ 追加
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // すべての Web / API リクエストでテナント解決ミドルウェアを通す
        $middleware->web(append: [
            SetTenant::class,
        ]);

        $middleware->api(append: [
            SetTenant::class,
        ]);

        // ルートミドルウェアのエイリアスを登録
        $middleware->alias([
            'auth.jwt' => AuthenticateWithJwt::class,
            'role'     => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (\Throwable $e, Request $request) {

            // API 以外は標準のエラーレンダリングに任せる
            if (! $request->is('api/*')) {
                return null;
            }

            // バリデーション例外は各コントローラでハンドリング済みなのでそのまま
            if ($e instanceof ValidationException) {
                return null;
            }

            // 共通 500 メッセージ（翻訳ファイルから取得）
            $serverErrorMessage = __('api.auth.messages.server_error');

            // 念のため、翻訳キーが見つからなかったときのフォールバック
            if ($serverErrorMessage === 'api.auth.messages.server_error') {
                $serverErrorMessage = 'システムエラーが発生しました。時間をおいて再度お試しください。';
            }

            // HTTP 例外（4xx / 5xx）
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                // 4xx は認証・バリデーションなどの個別実装に任せる
                if ($status < 500) {
                    return null;
                }

                // 5xx 系は共通メッセージで返す
                return response()->json([
                    'message' => $serverErrorMessage,
                ], $status);
            }

            // 想定外例外 → 500 固定で共通メッセージ
            return response()->json([
                'message' => $serverErrorMessage,
            ], 500);
        });
    })
    ->create();
