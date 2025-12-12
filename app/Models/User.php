<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        // 'email_verified_at' => 'datetime',

        'enrolled_at'   => 'date',
        'left_at'       => 'date',
        'last_login_at' => 'datetime',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $connection = 'tenant';

    public function testResults()
    {
        return $this->hasMany(\App\Models\Tenant\TestResult::class, 'user_id');
    }

    public function testNotes()
    {
        return $this->hasMany(\App\Models\Tenant\TestNote::class, 'user_id');
    }

    public function loginHistories()
    {
        return $this->hasMany(\App\Models\Tenant\LoginHistory::class, 'user_id');
    }

}
