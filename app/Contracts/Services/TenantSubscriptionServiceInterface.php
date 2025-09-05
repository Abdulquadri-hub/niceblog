<?php

namespace App\Contracts\Services;

use App\Models\Landlord\Tenant;

interface TenantSubscriptionInterface
{
    public function subscribe(array $subscriptionData): array;
    public function cancel(Tenant $tenant): bool;
    public function upgrade(int $tenantId, array $subscriptionData): array;
    public function verify(array $payload, string $signature);
}
