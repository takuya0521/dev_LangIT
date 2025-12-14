<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class CoursePath extends Model
{
    protected $table = 'course_paths';

    protected $fillable = [
        'from_course_id',
        'to_course_id',
        'sort_order',
        'label',
    ];

    public function fromCourse()
    {
        return $this->belongsTo(Course::class, 'from_course_id');
    }

    public function toCourse()
    {
        return $this->belongsTo(Course::class, 'to_course_id');
    }
}
