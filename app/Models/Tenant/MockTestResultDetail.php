<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockTestResultDetail extends TenantModel
{
    protected $fillable = [
        'mock_test_result_id',
        'mock_test_question_id',
        'selected_choice_id',
        'is_correct',
        'correct_choice_id',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(MockTestResult::class, 'mock_test_result_id');
    }
}
