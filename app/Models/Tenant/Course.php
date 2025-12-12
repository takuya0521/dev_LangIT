<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;

class Course extends TenantModel
{
    protected $table = 'courses';

    protected $fillable = [
        'title',
        'description',
        'thumbnail_url',
        // 必要に応じて他のカラムも
    ];

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }

    public function userCourses()
    {
        return $this->hasMany(UserCourse::class);
    }
}
