<?php

namespace App\Contracts\Repositories;

use App\Models\Landlord\Tenant;

interface TenantRepositoryInterface
{
    public function findByEmail(string $email): ?Tenant;
    public function findBySlug(string $slug): ?Tenant;
    public function createTenant(array $tenantData): ?Tenant;
    public function fetchTenant(string $column, $criteria): ?Tenant;
    public function updateStatus(Tenant $tenant, ?string $status, ?string $setup = null, ?string $error = null): void;
}
