<?php

// ===================================================================
// STEP 1: Update Tenant Model - Add Status Tracking
// ===================================================================

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class Tenant extends BaseTenant
{
    use HasFactory, UsesLandlordConnection;

    // Add status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CREATING_DATABASE = 'creating_database';
    const STATUS_RUNNING_MIGRATIONS = 'running_migrations';
    const STATUS_SEEDING_DATA = 'seeding_data';
    const STATUS_CREATING_OWNER = 'creating_owner';
    const STATUS_ACTIVE = 'active';
    const STATUS_FAILED = 'failed';
    const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        "name", "uuid", "subdomain", "domain", "slug",
        "database_name", "database_host", "plan_type",
        "subscription_status", "trial_ends_at", "billing_email",
        "status", "max_users", "max_posts", "max_storage_mb",
        "setup_step", "setup_error", "setup_completed_at"
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'setup_completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function boot(){
        parent::boot();

        static::creating(function ($tenant) {
            if(empty($tenant->slug)){
                $tenant->slug = static::generateUniqueSlug($tenant->name);
            }

            if(empty($tenant->database_name)){
                $tenant->database_name = static::generateDatabasename($tenant->slug);
            }
        });

        // Remove the automatic database creation from model events
        // We'll handle this in the queue job
    }

    public static function generateUniqueSlug(string $name): string
    {
        $actualSlug = Str::slug($name);
        $slug = $actualSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $actualSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public static function generateDatabasename(string $slug): string {
        $prefix = config('app.env') === 'local' ? 'local_tenant_' : 'tenant_';
        return $prefix . $slug . '_' . Str::random(6);
    }

    public function createDatabase(): void {
        $LandlordDbConnection = config('multitenancy.landlord_database_connection_name');

        DB::connection($LandlordDbConnection)->statement(
            "CREATE DATABASE IF NOT EXISTS `{$this->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public function getDatabaseName(): string
    {
        return $this->database_name;
    }

    public function dropDatabase(): void{
        $LandlordDbConnection = config('multitenancy.landlord_database_connection_name');

        DB::connection($LandlordDbConnection)->statement(
            "DROP DATABASE IF EXISTS `{$this->database_name}`"
        );
    }

    public function runMigrations(): void {
        $this->execute(function () {
            try {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true
                ]);

                $output = Artisan::output();
                Log::info("Tenant [{$this->id}] migration succeeded. Output: " . $output);
            } catch (\Throwable $th) {
               Log::error("Tenant [{$this->id}] migration failed: " . $th->getMessage());
               throw $th;
            }
        });
    }

    // New helper methods for status management
    public function updateStatus(string $status, string $step = null, string $error = null): void
    {
        $this->update([
            'status' => $status,
            'setup_step' => $step,
            'setup_error' => $error,
            'setup_completed_at' => $status === self::STATUS_ACTIVE ? now() : null,
        ]);
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasFailed(): bool {
        return $this->status === self::STATUS_FAILED;
    }

    public function isOnTrial(): bool {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function scopeActive($query){
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query){
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query){
        return $query->where('status', self::STATUS_FAILED);
    }

    public function getLocalUrlAttribute(): string {
        return url("/api/{$this->slug}");
    }

    public function getProductionUrlAttribute(): string {
        if($this->domain) {
            $configDomain = config('app.tenant_domain', 'localhost');
            return "https://{$this->domain}.{$configDomain}";
        }

        return $this->local_url;
    }
}

// ===================================================================
// STEP 2: Create the Queue Job
// ===================================================================

namespace App\Jobs\basket;

use Exception;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\HashService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SetupTenantJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenant;
    public $tenantOwnerData;

    // Job configuration
    public $tries = 3;
    public $maxExceptions = 3;
    public $timeout = 300; // 5 minutes
    public $backoff = [60, 120, 300]; // Retry after 1min, 2min, 5min

    public function __construct(Tenant $tenant, array $tenantOwnerData)
    {
        $this->tenant = $tenant;
        $this->tenantOwnerData = $tenantOwnerData;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->tenant->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting tenant setup for: {$this->tenant->name} (ID: {$this->tenant->id})");

            // Step 1: Create Database
            $this->createDatabase();

            // Step 2: Run Migrations
            $this->runMigrations();

            // Step 3: Seed Default Data
            $this->seedDefaultData();

            // Step 4: Create Tenant Owner
            $this->createTenantOwner();

            // Step 5: Mark as Active
            $this->markTenantAsActive();

            Log::info("Tenant setup completed successfully for: {$this->tenant->name}");

        } catch (\Throwable $e) {
            $this->handleFailure($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    private function createDatabase(): void
    {
        $this->tenant->updateStatus(Tenant::STATUS_CREATING_DATABASE, 'Creating tenant database');

        Log::info("Creating database for tenant: {$this->tenant->id}");
        $this->tenant->createDatabase();

        Log::info("Database created successfully for tenant: {$this->tenant->id}");
    }

    private function runMigrations(): void
    {
        $this->tenant->updateStatus(Tenant::STATUS_RUNNING_MIGRATIONS, 'Running database migrations');

        Log::info("Running migrations for tenant: {$this->tenant->id}");
        $this->tenant->runMigrations();

        Log::info("Migrations completed successfully for tenant: {$this->tenant->id}");
    }

    private function seedDefaultData(): void
    {
        $this->tenant->updateStatus(Tenant::STATUS_SEEDING_DATA, 'Seeding default data');

        Log::info("Seeding default data for tenant: {$this->tenant->id}");

        $this->tenant->execute(function () {
            Artisan::call('db:seed',[
                '--database' => 'tenant',
                '--class' =>  'TenantDatabaseSeeder',
                '--force' => true
            ]);
        });

        Log::info("Default data seeded successfully for tenant: {$this->tenant->id}");
    }

    private function createTenantOwner(): void
    {
        $this->tenant->updateStatus(Tenant::STATUS_CREATING_OWNER, 'Creating tenant owner account');

        Log::info("Creating tenant owner for tenant: {$this->tenant->id}");

        $this->tenant->execute(function () {
            DB::connection('tenant')->transaction(function () {
                $tenantOwner = User::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'email' => $this->tenantOwnerData['email'],
                    'first_name' => $this->tenantOwnerData['first_name'],
                    'last_name' => $this->tenantOwnerData['last_name'],
                    'password' => app(HashService::class)->make($this->tenantOwnerData['password']),
                    'is_owner' => true,
                ]);

                if (!$tenantOwner) {
                    throw new Exception("Error creating tenant owner");
                }

                // Check if role exists before assigning
                $roleExists = DB::connection('tenant')->table('roles')->where('id', 1)->exists();
                if (!$roleExists) {
                    throw new Exception("Default role not found. Ensure seeding completed successfully.");
                }

                DB::connection('tenant')->table('user_roles')->insert([
                    'user_id' => $tenantOwner->id,
                    'role_id' => 1,
                    'assigned_by' => null,
                    'assigned_at' => now()
                ]);

                Log::info("Tenant owner created successfully for tenant: {$this->tenant->id}");
            });
        });
    }

    private function markTenantAsActive(): void
    {
        Log::info("Marking tenant as active: {$this->tenant->id}");

        $this->tenant->updateStatus(Tenant::STATUS_ACTIVE, 'Tenant setup completed');

        // You can dispatch additional jobs here, like:
        // - Send welcome email to tenant owner
        // - Set up default integrations
        // - Create sample data
        // dispatch(new SendTenantWelcomeEmailJob($this->tenant, $this->tenantOwnerData));
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        $this->handleFailure($exception);
    }

    private function handleFailure(\Throwable $exception): void
    {
        Log::error("Tenant setup failed for tenant: {$this->tenant->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->tenant->updateStatus(
            Tenant::STATUS_FAILED,
            'Setup failed',
            $exception->getMessage()
        );

        // Cleanup if necessary
        try {
            if ($this->tenant->database_name) {
                $this->tenant->dropDatabase();
                Log::info("Cleaned up database for failed tenant: {$this->tenant->id}");
            }
        } catch (\Throwable $cleanupException) {
            Log::error("Failed to cleanup database for tenant: {$this->tenant->id}", [
                'error' => $cleanupException->getMessage()
            ]);
        }
    }
}

// ===================================================================
// STEP 3: Updated TenantService
// ===================================================================

namespace App\Services\Landlord;

use Exception;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;
use App\Jobs\Tenant\SetupTenantJob;
use App\Services\Auth\HashService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use App\Contracts\Services\TenantServiceInterface;
use App\Repositories\TenantRepository;
use Illuminate\Database\Eloquent\Collection;

class TenantService implements TenantServiceInterface
{
    public function create(array $tenantData): ?Tenant
    {
        try {
            // Create tenant record in landlord database
            $tenant = DB::connection('landlord')->transaction(function () use ($tenantData) {
                return Tenant::create([
                    'uuid' => Str::uuid(),
                    'billing_email' => $tenantData['email'],
                    'name' => $tenantData['name'],
                    'status' => Tenant::STATUS_PENDING,
                ]);
            });

            if (!$tenant) {
                throw new Exception("Error creating tenant");
            }

            Log::info("Tenant created in landlord database", [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'status' => $tenant->status
            ]);

            // Dispatch the setup job
            SetupTenantJob::dispatch($tenant, $tenantData);

            Log::info("Tenant setup job dispatched", [
                'tenant_id' => $tenant->id
            ]);

            return $tenant;

        } catch (\Throwable $th) {
            Log::error("Tenant registration error: " . $th->getMessage());
            throw new Exception($th->getMessage());
        }
    }

    public function delete(Tenant $tenant): bool
    {
        try {
            // Drop database first
            if ($tenant->database_name) {
                $tenant->dropDatabase();
            }

            // Delete tenant record
            return $tenant->delete();
        } catch (\Throwable $th) {
            Log::error("Error deleting tenant: " . $th->getMessage());
            return false;
        }
    }

    public function update(Tenant $tenant, array $tenantData): bool
    {
        try {
            return $tenant->update($tenantData);
        } catch (\Throwable $th) {
            Log::error("Error updating tenant: " . $th->getMessage());
            return false;
        }
    }

    public function statistics(): Collection
    {
        return collect([
            'total' => Tenant::count(),
            'active' => Tenant::active()->count(),
            'pending' => Tenant::pending()->count(),
            'failed' => Tenant::failed()->count(),
        ]);
    }

    /**
     * Retry failed tenant setup
     */
    public function retrySetup(Tenant $tenant): bool
    {
        if (!$tenant->hasFailed()) {
            throw new Exception("Can only retry failed tenants");
        }

        try {
            // Reset tenant status
            $tenant->updateStatus(Tenant::STATUS_PENDING, 'Retrying setup');

            // Dispatch the job again
            SetupTenantJob::dispatch($tenant, [
                'email' => $tenant->billing_email,
                'first_name' => 'Admin', // You might want to store this data
                'last_name' => 'User',
                'password' => 'temporary-password-' . Str::random(8)
            ]);

            return true;
        } catch (\Throwable $th) {
            Log::error("Error retrying tenant setup: " . $th->getMessage());
            return false;
        }
    }

    /**
     * Get tenant setup progress
     */
    public function getSetupProgress(Tenant $tenant): array
    {
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

// ===================================================================
// STEP 4: API Controller for Status Checking
// ===================================================================

namespace App\Http\Controllers\Api\Landlord;

use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Landlord\TenantService;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,billing_email',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        try {
            $tenant = $this->tenantService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tenant creation initiated successfully',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'status' => $tenant->status,
                    'setup_url' => route('tenant.setup.status', $tenant->id),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function setupStatus(Tenant $tenant)
    {
        $progress = $this->tenantService->getSetupProgress($tenant);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'progress' => $progress,
            ]
        ]);
    }

    public function retrySetup(Tenant $tenant)
    {
        try {
            $this->tenantService->retrySetup($tenant);

            return response()->json([
                'success' => true,
                'message' => 'Tenant setup retry initiated',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

// ===================================================================
// STEP 5: Database Migration for New Columns
// ===================================================================

/*
Create migration: php artisan make:migration add_setup_columns_to_tenants_table

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('setup_step')->nullable()->after('status');
            $table->text('setup_error')->nullable()->after('setup_step');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_error');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['setup_step', 'setup_error', 'setup_completed_at']);
        });
    }
};
*/

// ===================================================================
// STEP 6: Routes
// ===================================================================

/*
// In routes/api.php or your landlord routes

Route::prefix('tenants')->group(function () {
    Route::post('/', [TenantController::class, 'store']);
    Route::get('/{tenant}/setup-status', [TenantController::class, 'setupStatus'])->name('tenant.setup.status');
    Route::post('/{tenant}/retry-setup', [TenantController::class, 'retrySetup']);
});
*/
