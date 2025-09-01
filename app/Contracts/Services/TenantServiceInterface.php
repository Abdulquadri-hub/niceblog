<?php

namespace App\Contracts\Services;

use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Collection;

interface TenantServiceInterface
{
    public function create(array $tenantData): ?Tenant;

    public function delete(Tenant $tenant): bool;

    public function update(Tenant $tenant, array $tenantData): bool;

    public function getTenants(): ?Tenant;

    public function retrySetup(int $tenantId);
}
