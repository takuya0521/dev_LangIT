<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\MockTest;
use App\Models\Tenant\MockTestQuestion;
use App\Models\Tenant\MockTestChoice;

class TenantMockTestSeeder extends Seeder
{
    public function run(): void
    {
        $mockTest = MockTest::create([
            'course_id' => 1, // ← まずは course_id=1 が存在する前提。無ければ後述の確認へ
            'title' => 'Mock Test (Demo)',
            'description' => 'F06 動作確認用のダミー模擬試験',
            'time_limit' => 600, // 10分
            'pass_score' => 60,
            'is_active' => true,
        ]);

        $q1 = MockTestQuestion::create([
            'mock_test_id' => $mockTest->id,
            'text' => '【Q1】Laravelのルーティング定義ファイルは？',
            'explanation' => '通常は routes/web.php と routes/api.php。',
            'sort_order' => 1,
        ]);

        MockTestChoice::insert([
            [
                'mock_test_question_id' => $q1->id,
                'text' => 'routes/api.php',
                'is_correct' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mock_test_question_id' => $q1->id,
                'text' => 'storage/logs/laravel.log',
                'is_correct' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $q2 = MockTestQuestion::create([
            'mock_test_id' => $mockTest->id,
            'text' => '【Q2】JWTをクライアントから送る場所は？',
            'explanation' => 'Authorization ヘッダに Bearer トークン。',
            'sort_order' => 2,
        ]);

        MockTestChoice::insert([
            [
                'mock_test_question_id' => $q2->id,
                'text' => 'Authorization: Bearer <token>',
                'is_correct' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mock_test_question_id' => $q2->id,
                'text' => 'Cookieのrandomフィールド',
                'is_correct' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
