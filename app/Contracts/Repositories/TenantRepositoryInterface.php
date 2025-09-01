<?php

namespace App\Contracts\Repositories;

use App\Models\Landlord\Tenant;

interface TenantRepositoryInterface
{
    public function createTenant(array $tenantData): ?Tenant;

    public function fetchTenant(string $column, $criteria): ?Tenant;

    public function createDatabase(Tenant $tenant): void;

    public function dropDatabase(Tenant $tenant): void;

    public function runMigrations(Tenant $tenant): void;

    public function seedDefaultData(Tenant $tenant): void;

    public function createTenantOwner(Tenant $tenant, array $tenantOwnerData): void;

    public function markTenantAsActive(Tenant $tenant): void;

    public function updateStatus(Tenant $tenant, ?string $status, ?string $setup = null, ?string $error = null): void;
}
