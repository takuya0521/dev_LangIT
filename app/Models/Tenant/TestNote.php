<?php

namespace App\Models\Tenant;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class TestNote extends TenantModel
{
    use SoftDeletes;

    protected $table = 'test_notes';

    protected $fillable = [
        'test_id',
        'user_id',
        'note_text',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function user()
    {
        // ★ App\Models\User
        return $this->belongsTo(User::class);
    }
}
