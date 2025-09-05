<?php

namespace App\Repositories\Landlord;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantSubscription;
use Illuminate\Database\Eloquent\Collection;

class TenantSubscriptionRepository
{
    public function createSubscription(array $subscriptionData): ?TenantSubscription {
        return TenantSubscription::create($subscriptionData);
    }

    public function getSubscriptionByStatus(Tenant $tenant, string $status): ?TenantSubscription {
        return $tenant->subscriptions->where('status', $status)->get();
    }

    public function getCurrentSubscription(Tenant $tenant): ?TenantSubscription {
        return TenantSubscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->first();
    }

}
