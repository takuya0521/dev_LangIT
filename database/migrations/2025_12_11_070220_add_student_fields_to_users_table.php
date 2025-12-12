<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // テナントDBの users に対してカラム追加
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            // 学籍番号・生徒番号など
            $table->string('student_number', 50)
                ->nullable()
                ->after('email');

            // 学年（高2など文字列で柔軟に）
            $table->string('grade', 20)
                ->nullable()
                ->after('student_number');

            // クラス名（A組 / 1-A / 夜間クラス など）
            $table->string('class_name', 50)
                ->nullable()
                ->after('grade');

            // コース（高校受験コース / 英検コース など）
            $table->string('course', 50)
                ->nullable()
                ->after('class_name');

            // 入校日
            $table->date('enrolled_at')
                ->nullable()
                ->after('course');

            // 退校日（任意）
            $table->date('left_at')
                ->nullable()
                ->after('enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->dropColumn([
                'student_number',
                'grade',
                'class_name',
                'course',
                'enrolled_at',
                'left_at',
            ]);
        });
    }
};
