<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mock_test_result_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mock_test_result_id');
            $table->unsignedBigInteger('mock_test_question_id');

            // 未回答を許容するため nullable
            $table->unsignedBigInteger('selected_choice_id')->nullable();

            $table->boolean('is_correct');

            // 表示・分析用（任意）
            $table->unsignedBigInteger('correct_choice_id')->nullable();

            $table->timestamps();

            $table->index(['mock_test_result_id']);
            $table->foreign('mock_test_result_id')
                ->references('id')->on('mock_test_results')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_test_result_details');
    }
};
