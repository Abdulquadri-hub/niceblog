<?php

namespace App\Contracts\Repositories;

use App\Models\Landlord\Tenant;

interface TenantDatabaseRepository
{
    public function createDatabase(Tenant $tenant): void;
    public function dropDatabase(Tenant $tenant): void;
    public function runMigrations(Tenant $tenant): void;
    public function seedDefaultData(Tenant $tenant): void;
    public function createTenantOwner(Tenant $tenant, array $tenantOwnerData): void;
    public function markTenantAsActive(Tenant $tenant): void;
}
