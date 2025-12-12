<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends TenantModel
{
    use SoftDeletes;

    protected $table = 'tests';

    protected $fillable = [
        'chapter_id',
        'title',
    ];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function questions()
    {
        return $this->hasMany(TestQuestion::class);
    }

    public function results()
    {
        return $this->hasMany(TestResult::class);
    }

    public function notes()
    {
        return $this->hasMany(TestNote::class);
    }
}
