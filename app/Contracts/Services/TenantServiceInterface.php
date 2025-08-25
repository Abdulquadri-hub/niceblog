<?php

namespace App\Contracts\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

interface TenantServiceInterface
{
    public function create(array $tenantData): ?Tenant;

    public function delete(Tenant $tenant): bool;

    public function update(Tenant $tenant, array $tenantData): bool;
}
