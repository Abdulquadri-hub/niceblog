<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class TenantOwner extends Model
{
    use HasFactory, UsesLandlordConnection;

    protected $fillable = [
        'tenant_id', 'user_id', 'email', 'last_name',
        'password', 'email_verified_at', 'first_name'
    ];

    protected $cast = [
        'email_verified_at' => 'datetime'
    ];

    public function tenant():BelongsTo {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

}
