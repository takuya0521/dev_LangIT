<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // demoテナント用のテストユーザー（例：管理者）
        User::updateOrCreate(
            ['email' => 'admin@demo-school.test'], // ユニークキー
            [
                'name'   => 'デモ管理者',
                'password' => Hash::make('Password123'), // 仮パスワード
                'role'   => 'admin',
                'status' => 'active',
                'mfa_enabled' => true,
            ]
        );

        // 必要なら、生徒用ユーザーもここで作れる
        // User::updateOrCreate(
        //     ['email' => 'student@demo-school.test'],
        //     [
        //         'name'     => 'デモ生徒',
        //         'password' => Hash::make('Password123'),
        //         'role'     => 'student',
        //         'status'   => 'active',
        //     ]
        // );
    }
}
