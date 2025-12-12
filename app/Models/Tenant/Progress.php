<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;

class Progress extends TenantModel
{
    protected $table = 'progress';

    protected $fillable = [
        'user_id',
        'course_id',
        'chapter_id',
        'is_completed',
        'watched_seconds',
        'watched_rate',
        'last_watch_position',
    ];
}
