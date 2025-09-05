<?php

namespace App\Services\Landlord;

use Exception;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Contracts\Services\TenantServiceInterface;
use App\Enums\TenantStatuses;
use App\Jobs\Landlord\SetupTenantJob;
use App\Repositories\Landlord\TenantDatabaseRepository;
use App\Repositories\Landlord\TenantRepository;

class TenantService implements TenantServiceInterface
{

    public function __construct(
        protected TenantRepository $tenantRepo,
        protected  TenantDatabaseRepository $tenantDatabase
    ){}

    public function create(array $tenantData): ?Tenant
    {
        try {
           $tenant =  DB::connection('landlord')->transaction(function () use ($tenantData) {
                return $this->tenantRepo->createTenant($tenantData);
            });

            if(!$tenant) {
                throw new Exception("Error creating tenant");
            }

            SetupTenantJob::dispatch($tenant, $tenantData);

            return $tenant;

        } catch (\Throwable $th) {
            Log::error("Tenant registration error: " . $th->getMessage());
            throw new Exception($th->getMessage());
        }

    }

    public function delete($tenantId): bool
    {
        try {
            $tenant = $this->tenantRepo->fetchTenant('id', $tenantId);
            if(!$tenant) {
                throw new Exception("Tenant not found");
            }

            if($tenant->database_name){
                $this->tenantDatabase->dropDatabase($tenant);
            }

            return $tenant->delete();
        } catch (\Throwable $th) {
            Log::error("Error deleting tenant", [
                'error' => $th->getMessage()
            ]);

            throw new Exception("Error deleting tenant: {$th->getMessage()}");
        }
    }

    public function update(Tenant $tenant, array $tenantData): bool
    {
        return true;
    }

    public function retrySetup(int $tenantId) {

        $tenant = $this->tenantRepo->fetchTenant('id', $tenantId);
        if(!$tenant->hasFailed()) {
            throw new Exception("can only retry failed tenants");
        }

        try {
            $this->tenantRepo->updateStatus($tenant, TenantStatuses::PENDING->value, "Retrying setup");

            $tenantOwnerData = [
                'email' => $tenant->email,
                'first_name' => $tenant->first_name,
                'last_name' => $tenant->last_name,
                'password' => $tenant->password,
            ];

            SetupTenantJob::dispatch($tenant, $tenantOwnerData);

            $tenant = $this->tenantRepo->fetchTenant('id', $tenantId);
            return $tenant;

        } catch (\Throwable $th) {
            Log::error("Error retrying tenant setup: " . $th->getMessage());
        }
    }

    public function getTenants($criteria = "all"): ?Tenant
    {
        return Tenant::get();
    }

    public function getSetupProgress(Tenant $tenant): array {
        return [
            'status' => $tenant->status,
            'step' => $tenant->setup_step,
            'error' => $tenant->setup_error,
            'completed_at' => $tenant->setup_completed_at,
            'is_complete' => $tenant->isActive(),
            'has_failed' => $tenant->hasFailed(),
        ];
    }
}
