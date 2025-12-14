<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockTest extends TenantModel
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'time_limit',
        'pass_score',
        'is_active',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(MockTestQuestion::class)->orderBy('sort_order');
    }
}
