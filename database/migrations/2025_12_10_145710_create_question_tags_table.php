<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('question_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)
                ->comment('タグ名（HTML, CSS, link など）');
            $table->string('slug', 100)
                ->unique()
                ->comment('タグの機械用キー');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('question_tags');
    }
};
