<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 管理者だけ 2FA を有効にするためのフラグ
            $table->boolean('mfa_enabled')
                ->default(false)
                ->after('status');

            // メールで送るワンタイムコードのハッシュ値
            $table->string('mfa_email_code', 255)
                ->nullable()
                ->after('mfa_enabled');

            // ワンタイムコードの有効期限
            $table->dateTime('mfa_email_code_expires_at')
                ->nullable()
                ->after('mfa_email_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mfa_enabled');
            $table->dropColumn('mfa_email_code');
            $table->dropColumn('mfa_email_code_expires_at');
        });
    }
};
