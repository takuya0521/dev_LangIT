<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionTag extends TenantModel
{
    use SoftDeletes;

    protected $table = 'question_tags';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function questions()
    {
        return $this->belongsToMany(
            TestQuestion::class,
            'question_tag_pivot',
            'tag_id',
            'question_id'
        );
    }
}
