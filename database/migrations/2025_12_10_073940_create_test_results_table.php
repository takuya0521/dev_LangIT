<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('test_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('test_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('score');
            $table->boolean('is_passed');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); // 設計書に updated_at 無しなら nullable 可
            $table->softDeletes();

            $table->index('test_id');
            $table->index('user_id');
            $table->foreign('test_id')->references('id')->on('tests');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('test_results');
    }
};
