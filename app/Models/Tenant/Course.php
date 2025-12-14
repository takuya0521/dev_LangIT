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
        'base_course_id',
        'version',
        'is_latest',
        'published_at',
        // 必要に応じて他のカラムも
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function baseCourse()
    {
        return $this->belongsTo(self::class, 'base_course_id');
    }

    public function versions()
    {
        return $this->hasMany(self::class, 'base_course_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }

    public function userCourses()
    {
        return $this->hasMany(UserCourse::class);
    }

        public function scopeLatestVersion($query)
    {
        return $query->where('is_latest', true);
    }

    public function scopeOfBaseCourse($query, int $baseCourseId)
    {
        return $query->where('base_course_id', $baseCourseId);
    }

}
