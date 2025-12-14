<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // 同一「コースファミリー」を識別するためのID
            $table->unsignedBigInteger('base_course_id')->nullable()->after('id');

            // バージョン番号（1,2,3...）
            $table->unsignedInteger('version')->default(1)->after('base_course_id');

            // 最新版フラグ
            $table->boolean('is_latest')->default(true)->after('version');

            // 公開日時（必要ならフロントで「公開日」を出すのに使える）
            $table->timestamp('published_at')->nullable()->after('is_latest');

            $table->foreign('base_course_id')
                ->references('id')
                ->on('courses')
                ->onDelete('set null');
        });

        // 既存コースは「自分自身をベース」として version=1, is_latest=1 に初期化
        DB::table('courses')->update([
            'base_course_id' => DB::raw('id'),
            'version'        => 1,
            'is_latest'      => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['base_course_id']);
            $table->dropColumn(['base_course_id', 'version', 'is_latest', 'published_at']);
        });
    }
};
