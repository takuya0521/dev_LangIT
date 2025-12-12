<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->table('test_questions', function (Blueprint $table) {
            $table->string('difficulty', 20)
                ->default('normal')
                ->after('sort_order')
                ->comment('難易度: easy / normal / hard など');

            $table->unsignedBigInteger('related_chapter_id')
                ->nullable()
                ->after('difficulty')
                ->comment('復習に推奨するチャプターID');

            $table->unsignedBigInteger('related_video_id')
                ->nullable()
                ->after('related_chapter_id')
                ->comment('復習に推奨する動画ID');

            $table->foreign('related_chapter_id')
                ->references('id')
                ->on('chapters');

            $table->foreign('related_video_id')
                ->references('id')
                ->on('videos');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('test_questions', function (Blueprint $table) {
            // 外部キー削除
            $table->dropForeign(['related_chapter_id']);
            $table->dropForeign(['related_video_id']);

            // カラム削除
            $table->dropColumn([
                'difficulty',
                'related_chapter_id',
                'related_video_id',
            ]);
        });
    }
};
