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
        // テナントDB側に login_histories テーブルを作成
        Schema::connection('tenant')->create('login_histories', function (Blueprint $table) {
            $table->id();

            // tenant.users テーブルへの外部キー
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('logged_in_at');          // ログイン日時
            $table->string('ip_address', 45)->nullable(); // IPアドレス（IPv4/IPv6対応）
            $table->text('user_agent')->nullable();    // ブラウザ情報（UA文字列）

            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('login_histories');
    }
};
