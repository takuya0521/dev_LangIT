<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mock_test_choices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mock_test_question_id');
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['mock_test_question_id', 'sort_order']);
            $table->foreign('mock_test_question_id')
                ->references('id')->on('mock_test_questions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_test_choices');
    }
};
