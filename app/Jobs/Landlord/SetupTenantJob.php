<?php

namespace App\Jobs\Landlord;

use Exception;
use App\Enums\TenantStatuses;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Log;
use App\Repositories\TenantRepository;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Repositories\Landlord\TenantDatabaseRepository;

class SetupTenantJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable,SerializesModels;

    public $tenant;
    public $tenantOwnerData;

    public $tries = 3;
    public $maxException = 3;
    public $timeout = 300;
    public $backoff = [60, 120, 300];

    public function __construct(Tenant $tenant, array $tenantOwnerData)
    {
        $this->tenant = $tenant;
        $this->tenantOwnerData = $tenantOwnerData;
    }

    public function uniqueId() {
        return $this->tenant->id;
    }

    public function handle(): void
    {
        try {
            Log::info("Starting tenant setup for: {$this->tenant->name} (ID: {$this->tenant->id})");

            app(TenantDatabaseRepository::class)->createDatabase($this->tenant);

            app(TenantDatabaseRepository::class)->runMigrations($this->tenant);

            app(TenantDatabaseRepository::class)->seedDefaultData($this->tenant);

            app(TenantDatabaseRepository::class)->createTenantOwner($this->tenant, $this->tenantOwnerData);

            app(TenantDatabaseRepository::class)->markTenantAsActive($this->tenant);

            Log::info("Tenant setup completed successfully for {$this->tenant->name} (ID: {$this->tenant->id})");

        } catch (\Throwable $th) {
            $this->handleFailure($th);
            throw new Exception("Tenant setup failed for: {$this->tenant->name} (ID: {$this->tenant->id})");
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    protected function handleFailure(\Throwable $exception) {
        Log::error("Tenant setup failed for tenant: {$this->tenant->name}", [
            'error' => $exception->getMessage()
        ]);

        app(TenantRepository::class)->updateStatus(
            $this->tenant,
            TenantStatuses::FAILED->value,
            "Tenant setup failed",
            $exception->getMessage());

        try {
            if ($this->tenant->database_name) {
                app(TenantDatabaseRepository::class)->dropDatabase($this->tenant);
                Log::info("Cleaned up database for failed tenant: {$this->tenant->id}");
            }
        } catch (\Throwable $cleanupException) {
            Log::error("Failed to cleanup database for tenant: {$this->tenant->id}", [
                'error' => $cleanupException->getMessage()
            ]);
        }

    }

}
