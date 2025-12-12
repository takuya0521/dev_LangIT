<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    /**
     * マルチテナント用に tenant 接続を利用
     */
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'logged_in_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
