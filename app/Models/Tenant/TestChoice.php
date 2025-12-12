<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestChoice extends TenantModel
{
    use SoftDeletes;

    protected $table = 'test_choices';

    protected $fillable = [
        'question_id',
        'choice_text',
        'is_correct',
        'sort_order',
    ];

    public function question()
    {
        return $this->belongsTo(TestQuestion::class, 'question_id');
    }
}
