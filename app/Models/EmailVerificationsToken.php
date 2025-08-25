<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class EmailVerificationsToken extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'user_id', 'token', 'expires_at', 'created_at' 
    ];
}
