<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('test_choices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('question_id');
            $table->text('choice_text');
            $table->boolean('is_correct');
            $table->integer('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index('question_id');
            $table->foreign('question_id')->references('id')->on('test_questions');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('test_choices');
    }
};
