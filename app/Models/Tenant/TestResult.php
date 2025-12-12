<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class TestResult extends TenantModel
{
    use SoftDeletes;

    protected $table = 'test_results';

    protected $fillable = [
        'test_id',
        'user_id',
        'score',
        'is_passed',
        'elapsed_seconds',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function user()
    {
        // ★ App\Models\User とのリレーション
        return $this->belongsTo(User::class);
    }

    public function answerDetails()
    {
        return $this->hasMany(TestAnswerDetail::class, 'test_result_id');
    }
}
