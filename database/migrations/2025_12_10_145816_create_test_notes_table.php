<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('test_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('test_id');
            $table->unsignedBigInteger('user_id');
            $table->text('note_text');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['test_id', 'user_id']);

            $table->foreign('test_id')
                ->references('id')
                ->on('tests');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('test_notes', function (Blueprint $table) {
            $table->dropForeign(['test_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::connection('tenant')->dropIfExists('test_notes');
    }
};
