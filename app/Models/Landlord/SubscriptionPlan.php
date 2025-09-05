<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        "name", "slug", "description", "price",
        "billing_cycle", "features", "is_active"
    ];

    protected $casts = [
        'features' => 'array'
    ];

    public function subscriptions(): HasMany {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function scopeActive(){
        return $this->where('is_active', true);
    }
}
