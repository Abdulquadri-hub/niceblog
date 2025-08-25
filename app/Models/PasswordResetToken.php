<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class PasswordResetToken extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'email', 'token', 'expires_at','created_at'
    ];
}
