<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;

class Chapter extends TenantModel
{
    protected $table = 'chapters';

    protected $fillable = [
        'course_id',
        'title',
        'chapter_type', // 'video', 'test', 'report' など
        'sort_order',
        // ...
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
