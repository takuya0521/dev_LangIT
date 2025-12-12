<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantController extends Controller
{
    /**
     * プラットフォーム側：テナント一覧取得
     *
     * GET /api/platform/tenants
     */
    public function index(Request $request)
    {
        // 共通DB（mysql接続）の tenants テーブルから一覧取得
        $tenants = Tenant::orderBy('id')->get([
            'id',
            'name',
            'subdomain',
        ]);

        return response()->json([
            'data' => $tenants,
        ], Response::HTTP_OK);
    }
}
