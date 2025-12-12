<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('test_answer_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('test_result_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('choice_id');
            $table->boolean('is_correct');
            $table->timestamps();

            $table->index('test_result_id');
            $table->index('question_id');

            $table->foreign('test_result_id')
                ->references('id')
                ->on('test_results');

            $table->foreign('question_id')
                ->references('id')
                ->on('test_questions');

            $table->foreign('choice_id')
                ->references('id')
                ->on('test_choices');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('test_answer_details', function (Blueprint $table) {
            $table->dropForeign(['test_result_id']);
            $table->dropForeign(['question_id']);
            $table->dropForeign(['choice_id']);
        });

        Schema::connection('tenant')->dropIfExists('test_answer_details');
    }
};
