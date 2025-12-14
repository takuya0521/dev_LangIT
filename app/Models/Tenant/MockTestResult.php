<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockTestResult extends TenantModel
{
    protected $fillable = [
        'mock_test_id',
        'user_id',
        'score',
        'pass',
        'correct_count',
        'total_questions',
        'started_at',
        'finished_at',
        'elapsed_seconds',
    ];

    protected $casts = [
        'pass' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function mockTest(): BelongsTo
    {
        return $this->belongsTo(MockTest::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(MockTestResultDetail::class);
    }
}