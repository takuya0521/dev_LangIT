<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockTestChoice extends TenantModel
{
    protected $fillable = [
        'mock_test_question_id',
        'text',
        'is_correct',
        'sort_order',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(MockTestQuestion::class, 'mock_test_question_id');
    }
}