<?php

namespace App\Services\Landlord;

use Exception;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Contracts\Services\TenantServiceInterface;
use App\Enums\TenantStatuses;
use App\Jobs\Tenant\SetupTenantJob;
use App\Repositories\TenantRepository;
use Illuminate\Database\Eloquent\Collection;

class TenantService implements TenantServiceInterface
{
    public function create(array $tenantData): ?Tenant
    {
        try {
           $tenant =  DB::connection('landlord')->transaction(function () use ($tenantData) {

                return Tenant::create([
                    'uuid' => Str::uuid(),
                    'billing_email' => $tenantData['email'],
                    'name' => $tenantData['name'],
                    'status' => TenantStatuses::PENDING->value
                ]);
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

    public function delete(Tenant $tenant): bool
    {
        try {
            if($tenant->database_name){
                app(TenantRepository::class)->dropDatabase($tenant);
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

    public function retrySetup(Tenant $tenant) {
        if(!$tenant->hasFailed()) {
            throw new Exception("can only retry failed tenants");
        }

        try {
            app(TenantRepository::class)->updateStatus($tenant, TenantStatuses::PENDING->value, "Retrying setup");

            $tenantOwnerData = $tenant->execute(function ($tenant) {
                return User::where('email', $tenant->email)->first();
            });

            SetupTenantJob::dispatch($tenant, $tenantOwnerData);

            return true;
        } catch (\Throwable $th) {
            Log::error("Error retrying tenant setup: " . $th->getMessage());
            return false;
        }
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
