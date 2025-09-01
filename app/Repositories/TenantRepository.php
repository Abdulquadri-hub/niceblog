<?php

namespace App\Repositories;

use Exception;
use App\Models\Tenant\User;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Str;
use App\Enums\TenantStatuses;
use App\Services\Auth\HashService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Contracts\Repositories\TenantRepositoryInterface;
use Spatie\Multitenancy\Landlord;

class TenantRepository implements TenantRepositoryInterface
{

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

    public function createDatabase(Tenant $tenant): void
    {
        $this->updateStatus($tenant, TenantStatuses::CREATING_DATABASE->value, 'Creating database');

        $landlordConnectionName = config('multitenancy.landlord_database_connection_name');

        DB::connection($landlordConnectionName)->statement(
            "CREATE DATABASE IF NOT EXISTS `{$tenant->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public function dropDatabase(Tenant $tenant): void
    {

        $landlordConnectionName = config('multitenancy.landlord_database_connection_name');

        DB::connection($landlordConnectionName)->statement(
            "DROP DATABASE IF EXISTS `{$tenant->database_name}` "
        );
    }

    public function runMigrations(Tenant $tenant): void
    {
        $this->updateStatus($tenant, TenantStatuses::RUNNING_MIGRATIONS->value, 'Running tenant migrations');

        $tenant->execute(function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });
    }

    public function seedDefaultData(Tenant $tenant): void
    {
        $this->updateStatus($tenant, TenantStatuses::SEEDING_DATA->value, 'Seeding default data');

        $tenant->execute(function () {
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true
            ]);
        });
    }

    public function createTenantOwner(Tenant $tenant, array $tenantOwnerData): void
    {
        $this->updateStatus($tenant, TenantStatuses::CREATING_OWNER->value, 'Creating tenant owner account');

        $tenant->execute(function () use ($tenantOwnerData, $tenant) {

            DB::connection('tenant')->transaction(function () use ($tenantOwnerData, $tenant) {
                $tenantOwner = User::create([
                    'uuid' => Str::uuid(),
                    'email' => $tenantOwnerData['email'],
                    'first_name' => $tenantOwnerData['first_name'],
                    'last_name' => $tenantOwnerData['last_name'],
                    'password' => $tenant->password,
                    'is_owner' => true,
                ]);

                if(!$tenantOwner) {
                    throw new Exception("Error creating tenant owner account");
                }

                $tenant->execute(function() use ($tenantOwner, $tenant){
                    $tenant->update([
                        'user_id' => $tenantOwner->id
                    ]);
                });

                $roleExists = DB::connection('tenant')->table('roles')->where('id', 1)->exists();

                if(!$roleExists) {
                    throw new Exception("Default role not found. Ensure seeding completed successfully");

                }

                DB::connection('tenant')->table('user_roles')->insert([
                    'user_id' => $tenantOwner->id,
                    'role_id' => 1,
                    'assigned_by' => null,
                    'assigned_at' => now()
                ]);
            });
        });
    }

    public function markTenantAsActive(Tenant $tenant): void
    {
        $this->updateStatus($tenant, TenantStatuses::ACTIVE->value, 'Tenant setup completed');
    }

    public function updateStatus(Tenant $tenant, ?string $status, ?string $step = null, ?string $error = null): void {
        $tenant->update([
            'status' => $status,
            'setup_step' => $step,
            'setup_error' => $error,
            'setup_completed_at' => $status === TenantStatuses::ACTIVE->value ? now() : null
        ]);
    }

    // public function deleteTenantOwner(Tenant $tenant) {
    //     return $tenant->delete();
    // }

    // public function updateTenantOwner(Tenant $tenant,array $tenantOwnerData) {
    //     return $tenant->update();
    // }
}
