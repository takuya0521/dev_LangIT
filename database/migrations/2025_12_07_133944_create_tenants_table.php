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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // 学校名
            $table->string('subdomain')->unique();  // サブドメイン（例: demo, schoolA）
            $table->string('db_host')->default('mysql');
            $table->unsignedSmallInteger('db_port')->default(3306);
            $table->string('db_database');          // DB名（例: schoolA_db）
            $table->string('db_username');          // DBユーザー
            $table->string('db_password');          // DBパスワード
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
