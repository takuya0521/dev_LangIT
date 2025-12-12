<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdminMfaController extends Controller
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    /**
     * 管理者向け二段階認証コードの検証
     *
     * POST /api/auth/mfa/verify
     * body: { "email": "...", "code": "123456" }
     */
    public function verify(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email', 'max:255'],
                'code'  => ['required', 'string', 'digits:6'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '入力内容に誤りがあります。',
                'errors'  => $e->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $email = strtolower(trim($validated['email']));
            $code  = $validated['code'];

            /** @var User|null $user */
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $user || $user->role !== 'admin' || ! $user->mfa_enabled) {
                return response()->json([
                    'message' => '二段階認証に失敗しました。',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (! $user->mfa_email_code || ! $user->mfa_email_code_expires_at) {
                return response()->json([
                    'message' => '有効な二段階認証コードが存在しません。再度ログインからやり直してください。',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (now()->greaterThan($user->mfa_email_code_expires_at)) {
                return response()->json([
                    'message' => '二段階認証コードの有効期限が切れています。再度ログインからやり直してください。',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (! Hash::check($code, $user->mfa_email_code)) {
                return response()->json([
                    'message' => '二段階認証コードが正しくありません。',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // コードは一度使ったら無効化
            $user->mfa_email_code = null;
            $user->mfa_email_code_expires_at = null;
            $user->save();

            // ここで初めて JWT を発行（= 本当にログイン完了）
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
            Log::error('Admin MFA verify error', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'システムエラーが発生しました。時間をおいて再度お試しください。',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
