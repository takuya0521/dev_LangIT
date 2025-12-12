<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;

class TestAnswerDetail extends TenantModel
{
    protected $table = 'test_answer_details';

    protected $fillable = [
        'test_result_id',
        'question_id',
        'choice_id',
        'is_correct',
    ];

    public function result()
    {
        return $this->belongsTo(TestResult::class, 'test_result_id');
    }

    public function question()
    {
        return $this->belongsTo(TestQuestion::class, 'question_id');
    }

    public function choice()
    {
        return $this->belongsTo(TestChoice::class, 'choice_id');
    }
}
