<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class Transaction extends Model
{
    use UsesLandlordConnection;
    
    protected $table = "transactions";

    protected $fillable = [
        "tenant_id", "type", "amount",
        "currency", "status", "gateway",
        "gateway_transaction_id", "metadata", "processed_at",
        "payment_method", "reference"
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }
}
