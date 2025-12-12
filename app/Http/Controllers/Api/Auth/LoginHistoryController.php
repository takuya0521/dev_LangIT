<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    /**
     * ログイン中ユーザー自身のログイン履歴を返す。
     *
     * GET /api/me/login-histories
     */
    public function index(Request $request)
    {
        $user = $request->user(); // auth.jwt ミドルウェアでセットされる想定

        // 直近10件だけ返す（必要に応じて件数は変えてOK）
        $histories = $user->loginHistories()
            ->orderByDesc('logged_in_at')
            ->limit(10)
            ->get(['logged_in_at', 'ip_address', 'user_agent']);

        return response()->json([
            'data' => $histories->map(function ($h) {
                return [
                    'logged_in_at' => $h->logged_in_at?->toIso8601String(),
                    'ip_address'   => $h->ip_address,
                    'user_agent'   => $h->user_agent,
                ];
            }),
        ]);
    }
}
