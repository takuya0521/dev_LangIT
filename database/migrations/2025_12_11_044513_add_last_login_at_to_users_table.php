<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // テナントDBの users テーブルに last_login_at を追加
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            // status カラムの直後に追加（status がない場合は after はあってもなくてもOK）
            $table->timestamp('last_login_at')
                ->nullable()
                ->index()
                ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
