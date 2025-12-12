<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('chapter_id');
            $table->string('title', 255);
            $table->timestamps();
            $table->softDeletes(); // deleted_at

            $table->index('chapter_id');
            $table->foreign('chapter_id')->references('id')->on('chapters');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tests');
    }
};
