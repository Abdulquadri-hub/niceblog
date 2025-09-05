<?php

namespace App\Repositories\Landlord;

use Illuminate\Support\Str;
use App\Enums\TenantStatuses;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\DB;
use App\Contracts\Repositories\TenantDatabaseRepositoryInterface;
use App\Models\Tenant\User;
use Exception;
use Illuminate\Support\Facades\Artisan;

class TenantDatabaseRepository implements TenantDatabaseRepositoryInterface
{
    protected $tenantRepo;

    public function __construct(TenantRepository $tenantRepo)
    {
        $this->tenantRepo = $tenantRepo;
    }

    public function createDatabase(Tenant $tenant): void
    {
        $this->tenantRepo->updateStatus($tenant, TenantStatuses::CREATING_DATABASE->value, 'Creating database');

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
        $this->tenantRepo->updateStatus($tenant, TenantStatuses::RUNNING_MIGRATIONS->value, 'Running tenant migrations');

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
        $this->tenantRepo->updateStatus($tenant, TenantStatuses::SEEDING_DATA->value, 'Seeding default data');

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
        $this->tenantRepo->updateStatus($tenant, TenantStatuses::CREATING_OWNER->value, 'Creating tenant owner account');

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
        $this->tenantRepo->updateStatus($tenant, TenantStatuses::ACTIVE->value, 'Tenant setup completed');
    }
}
