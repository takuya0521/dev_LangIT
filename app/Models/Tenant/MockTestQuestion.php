<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockTestQuestion extends TenantModel
{
    protected $fillable = [
        'mock_test_id',
        'text',
        'explanation',
        'sort_order',
    ];

    public function mockTest(): BelongsTo
    {
        return $this->belongsTo(MockTest::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(MockTestChoice::class)->orderBy('sort_order');
    }
}
