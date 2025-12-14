<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ★ ここで存在チェックを入れる
        if (! Schema::hasTable('course_paths')) {
            Schema::create('course_paths', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('from_course_id');
                $table->unsignedBigInteger('to_course_id');
                $table->unsignedInteger('sort_order')->default(1);
                $table->timestamps();

                $table->foreign('from_course_id')
                    ->references('id')->on('courses')
                    ->onDelete('cascade');

                $table->foreign('to_course_id')
                    ->references('id')->on('courses')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        // 開発中なら drop してもOK
        Schema::dropIfExists('course_paths');
    }
};
