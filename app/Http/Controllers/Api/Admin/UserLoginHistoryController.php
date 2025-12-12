<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserLoginHistoryController extends Controller
{
    /**
     * 管理者用：指定ユーザーのログイン履歴を一覧取得。
     *
     * GET /api/admin/users/{user}/login-histories
     */
    public function index(Request $request, User $user)
    {
        // ページネーション付きで返す
        $histories = $user->loginHistories()
            ->orderByDesc('logged_in_at')
            ->paginate(
                perPage: (int) $request->input('per_page', 20),
                columns: ['logged_in_at', 'ip_address', 'user_agent']
            );

        // そのまま JSON 返却（Laravelの標準paginate形式）
        return response()->json($histories);
    }
}
