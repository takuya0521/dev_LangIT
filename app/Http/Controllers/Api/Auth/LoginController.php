<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant\LoginHistory;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Mail\AdminMfaCodeMail;


class LoginController extends Controller
{
    private const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 900; // 15分

    public function __construct(
        protected JwtService $jwtService
    ) {}

    public function __invoke(Request $request)
    {
        $key = $this->throttleKey($request);

        // ▼ ロック中（429）
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => __('api.auth.messages.locked'),
            ], Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', $retryAfter);
        }

        // ▼ バリデーション（400）
        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:64',
                    'regex:/^(?=.*[A-Za-z])(?=.*\d)[!-~]+$/',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('api.auth.messages.validation_error'),
                'errors'  => $e->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $email    = strtolower(trim($validated['email']));
            $password = $validated['password'];

            /** @var User|null $user */
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            // ▼ 認証失敗（401）
            if (! $user || ! Hash::check($password, $user->password)) {
                RateLimiter::hit($key, self::DECAY_SECONDS);

                return response()->json([
                    'message' => __('api.auth.messages.unauthorized'),
                ], Response::HTTP_UNAUTHORIZED);
            }

            // ▼ ステータスが active 以外（401）
            if ($user->status !== 'active') {
                RateLimiter::hit($key, self::DECAY_SECONDS);

                // アカウント状態専用のメッセージにする
                return response()->json([
                    'message' => __('api.auth.messages.inactive_user'),
                ], Response::HTTP_UNAUTHORIZED);
            }

            // ▼ 成功 → 最終ログイン日時＋ログイン履歴を記録
            $now = now();

            // users.last_login_at 更新
            $user->last_login_at = $now;
            $user->save();

            // login_histories に1件追加（App\Models\Tenant\LoginHistory を利用）
            LoginHistory::create([
                'user_id'      => $user->id,
                'logged_in_at' => $now,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);

            // ▼ 成功 → カウンターリセット
            RateLimiter::clear($key);

            // 管理者で 2FA 有効な場合は、ここではまだ JWT を返さずに
            // メールで 6 桁コードを送って「追加認証待ち」にする
            if ($user->role === 'admin' && $user->mfa_enabled) {
                try {
                    $rawCode = (string) random_int(100000, 999999);

                    $user->mfa_email_code = Hash::make($rawCode);
                    $user->mfa_email_code_expires_at = now()->addMinutes(10);
                    $user->save();

                    Mail::to($user->email)->send(new AdminMfaCodeMail($rawCode));

                    return response()->json([
                        'requires_mfa' => true,
                        'message'      => '管理者ログイン用の二段階認証コードをメールに送信しました。',
                    ], Response::HTTP_OK);
                } catch (\Throwable $e) {
                    Log::error('Admin MFA mail send error', [
                        'exception' => $e,
                        'user_id'   => $user->id,
                    ]);

                    return response()->json([
                        'message' => '二段階認証コードの送信に失敗しました。時間をおいて再度お試しください。',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            // ★ここから下は今までどおり（student/teacher、または mfa_disabled の admin）
            $token = $this->jwtService->createToken($user);

            return response()->json([
                'token' => $token,
                'user'  => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'role'   => $user->role,
                    'email'  => $user->email,
                    'status' => $user->status,
                ],
            ], Response::HTTP_OK);

            // JWT発行（既存どおり）
            $token = $this->jwtService->createToken($user);

            return response()->json([
                'token' => $token,
                'user'  => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'role'   => $user->role,
                    'email'  => $user->email,
                    'status' => $user->status,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Login API error', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => __('api.auth.messages.server_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function throttleKey(Request $request): string
    {
        $host  = $request->getHost();
        $email = strtolower((string) $request->input('email', ''));

        return sprintf('login:%s:%s', $host, $email);
    }
}
