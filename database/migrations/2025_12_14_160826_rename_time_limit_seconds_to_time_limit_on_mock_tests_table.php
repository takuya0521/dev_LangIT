<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // time_limit_seconds -> time_limit（秒のまま）
        DB::statement("
            ALTER TABLE mock_tests
            CHANGE time_limit_seconds time_limit INT UNSIGNED NOT NULL DEFAULT 1800
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE mock_tests
            CHANGE time_limit time_limit_seconds INT UNSIGNED NOT NULL DEFAULT 1800
        ");
    }
};
