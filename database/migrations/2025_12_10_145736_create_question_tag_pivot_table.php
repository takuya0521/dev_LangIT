<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('question_tag_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->unique(['question_id', 'tag_id']);

            $table->foreign('question_id')
                ->references('id')
                ->on('test_questions');

            $table->foreign('tag_id')
                ->references('id')
                ->on('question_tags');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('question_tag_pivot', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
            $table->dropForeign(['tag_id']);
        });

        Schema::connection('tenant')->dropIfExists('question_tag_pivot');
    }
};
