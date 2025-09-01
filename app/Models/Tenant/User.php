<?php

namespace App\Models\Tenant;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, UsesTenantConnection;


    protected $fillable = [
        'first_name', 'last_name', 'avatar', 'uuid',
        'email', 'email_verified_at', 'password','bio',
        'is_verified', 'status', 'is_owner',
        'password', 'last_login_at', "remember_token"
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];



    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime'
        ];
    }

}
