<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PasswordController extends Controller
{
    /**
     * F01_05 本人によるパスワード変更
     *
     * POST /api/auth/password
     */
    public function change(ChangePasswordRequest $request)
    {
        $user = $request->user(); // AuthenticateWithJwt でセット済み

        // 現在のパスワードチェック
        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => __('api.auth.messages.validation_error'),
                'errors'  => [
                    'current_password' => ['現在のパスワードが正しくありません。'],
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->password = $request->input('password');
        $user->save();

        return response()->json([
            'message' => __('api.auth.messages.password_changed'),
        ], Response::HTTP_OK);
    }
}
