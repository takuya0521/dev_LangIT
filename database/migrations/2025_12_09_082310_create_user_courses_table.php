<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        if (Schema::connection('tenant')->hasTable('user_courses')) {
            return;
        }

        // tenant 接続に user_courses テーブルを作成
        Schema::connection('tenant')->create('user_courses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');

            // 学習ステータス（not_started / in_progress / completed）
            $table->string('learning_status')->default('not_started');

            // 進捗率（0〜100）
            $table->unsignedTinyInteger('progress_rate')->default(0);

            $table->timestamps();

            // 必要であれば外部キー制約（今はコメントアウトでOK）
            // $table->foreign('user_id')->references('id')->on('users');
            // $table->foreign('course_id')->references('id')->on('courses');

            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('user_courses');
    }
};
