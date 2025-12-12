<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;

class JwtService
{
    /**
     * ログイン成功時に JWT を発行する
     */
    public function createToken(User $user): string
    {
        $now = Carbon::now();
        $expires = $now->copy()->addHours(1); // 有効期限はひとまず1時間（仕様に合わせて後で調整可）

        $payload = [
            'iss' => config('app.url'),       // 発行者
            'sub' => $user->id,               // ユーザーID
            'role' => $user->role,            // ロール
            'iat' => $now->timestamp,         // 発行時刻
            'exp' => $expires->timestamp,     // 有効期限
        ];

        $secret = (string) config('app.jwt_secret', 'changeme');

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * トークンを検証してペイロードを返す（今後のAPIで使用予定）
     */
    public function decodeToken(string $token): array
    {
        $secret = config('app.jwt_secret');

        $decoded = JWT::decode($token, new Key($secret, 'HS256'));

        return (array) $decoded;
    }
}
