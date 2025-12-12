<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->table('test_results', function (Blueprint $table) {
            $table->unsignedInteger('elapsed_seconds')
                ->nullable()
                ->after('is_passed')
                ->comment('テストにかかった秒数（秒）');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('test_results', function (Blueprint $table) {
            $table->dropColumn('elapsed_seconds');
        });
    }
};
