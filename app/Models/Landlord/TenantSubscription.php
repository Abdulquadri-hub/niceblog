<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $table = "tenant_subscriptions";

    protected $fillable = [
        "tenant_id", "plan_id", "status",
        "current_period_start", "current_period_end",
        "canceled_at"
    ];

    protected $casts = [
        'canceled_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
    ];

    public function plan(): BelongsTo {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }
}
