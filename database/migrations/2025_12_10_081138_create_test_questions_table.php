<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // すでに test_questions テーブルがある場合は何もしない
        if (Schema::connection('tenant')->hasTable('test_questions')) {
            return;
        }

        Schema::connection('tenant')->create('test_questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('test_id');
            $table->text('question_text');
            $table->text('explanation')->nullable();
            $table->integer('sort_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index('test_id');
            $table->foreign('test_id')->references('id')->on('tests');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('test_questions');
    }
};
