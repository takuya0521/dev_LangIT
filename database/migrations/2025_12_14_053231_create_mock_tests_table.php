<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mock_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id'); // 受講コースに紐づく前提
            $table->string('title');
            $table->text('description')->nullable();

            // 仕様：APIレスポンスの time_limit（単位は秒想定）
            $table->unsignedInteger('time_limit_seconds')->default(1800); // 30分

            // 合否基準（スコア%）
            $table->unsignedTinyInteger('pass_score')->default(60);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'is_active']);

            // courses テーブルが tenant 側にある前提
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_tests');
    }
};
