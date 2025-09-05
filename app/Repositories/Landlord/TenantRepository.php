<?php

namespace App\Repositories\Landlord;

use App\Contracts\Repositories\TenantRepositoryInterface;
use App\Enums\TenantStatuses;
use App\Models\Landlord\Tenant;
use App\Services\Auth\HashService;
use illuminate\Support\Str;

class TenantRepository implements TenantRepositoryInterface
{
    public function findByEmail(string $email): ?Tenant
    {
        return Tenant::where('email', $email)->first();
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    public function createTenant(array $tenantData): ?Tenant
    {
        return Tenant::create([
            'uuid' => Str::uuid(),
            'email' => $tenantData['email'],
            'name' => $tenantData['name'],
            'first_name' => $tenantData['first_name'],
            'last_name' => $tenantData['last_name'],
            'password' => app(HashService::class)->make($tenantData['password']),
            'status' => TenantStatuses::PENDING->value
        ]);
    }

    public function fetchTenant(string $column, $criteria): ?Tenant {
        return Tenant::where($column, $criteria)->first();
    }

    public function updateStatus(Tenant $tenant, ?string $status, ?string $step = null, ?string $error = null): void {
        $tenant->update([
            'status' => $status,
            'setup_step' => $step,
            'setup_error' => $error,
            'setup_completed_at' => $status === TenantStatuses::ACTIVE->value ? now() : null
        ]);
    }
}
