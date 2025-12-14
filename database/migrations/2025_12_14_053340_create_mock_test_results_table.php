<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mock_test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mock_test_id');
            $table->unsignedBigInteger('user_id'); // user_id はJWTから取得する想定

            $table->unsignedTinyInteger('score'); // 0-100
            $table->boolean('pass');

            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_questions')->default(0);

            // 任意：開始/終了/経過秒（拡張用）
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('elapsed_seconds')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'mock_test_id', 'created_at']);
            $table->foreign('mock_test_id')->references('id')->on('mock_tests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_test_results');
    }
};
