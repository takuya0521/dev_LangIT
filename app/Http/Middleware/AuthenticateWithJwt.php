<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithJwt
{
    public function __construct(
        protected JwtService $jwtService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization', '');

        if (! Str::startsWith($authHeader, 'Bearer ')) {
            return response()->json([
                'message' => __('api.auth.messages.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = trim(Str::after($authHeader, 'Bearer '));

        try {
            $payload = $this->jwtService->decodeToken($token);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('api.auth.messages.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $payload['sub'] ?? null;

        if (! $userId) {
            return response()->json([
                'message' => __('api.auth.messages.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'message' => __('api.auth.messages.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // ステータスが active 以外なら利用不可
        if ($user->status !== 'active') {
            return response()->json([
                'message' => __('api.auth.messages.inactive_user'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

}