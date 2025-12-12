<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;

class UserCourse extends TenantModel
{
    protected $table = 'user_courses';

    protected $fillable = [
        'user_id',
        'course_id',
        'learning_status',  // 'not_started', 'in_progress', 'completed' など
        'progress_rate',    // 0〜100（設計書にある場合）
        // ...
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
