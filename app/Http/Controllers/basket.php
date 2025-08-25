
## STEP 8: Tenant-Aware Models

# app/Models/Post.php (Tenant Model)
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Post extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'status',
        'author_id',
        'category_id',
        'featured_image',
        'meta_data',
        'published_at',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = \Str::slug($post->title);
            }
            if (empty($post->author_id)) {
                $post->author_id = auth()->id();
            }
        });
    }

    /**
     * Relationships
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}

# app/Models/User.php (Tenant Model)
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, UsesTenantConnection;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'avatar',
        'bio',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'settings' => 'array',
    ];

    /**
     * Relationships
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is editor
     */
    public function isEditor(): bool
    {
        return in_array($this->role, ['admin', 'editor']);
    }
}

## STEP 9: Service Layer

# app/Services/TenantService.php
<?php

namespace App\Services;

use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\DB;

class TenantService
{
    /**
     * Create a new tenant
     */
    public function create(array $data): Tenant
    {
        DB::connection('landlord')->beginTransaction();

        try {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'domain' => $data['domain'] ?? null,
                'owner_email' => $data['owner_email'],
                'settings' => $data['settings'] ?? [],
                'trial_ends_at' => now()->addDays(30),
            ]);

            DB::connection('landlord')->commit();

            return $tenant;

        } catch (Exception $e) {
            DB::connection('landlord')->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a tenant and its database
     */
    public function delete(Tenant $tenant): bool
    {
        DB::connection('landlord')->beginTransaction();

        try {
            // Delete the tenant record (this will trigger the model's deleting event)
            $tenant->delete();

            DB::connection('landlord')->commit();

            return true;

        } catch (Exception $e) {
            DB::connection('landlord')->rollBack();
            throw $e;
        }
    }

    /**
     * Seed tenant with initial da
     */
    public function seedTenant(Tenant $tenant): void
    {
        $tenant->execute('db:seed --database=tenant --class=TenantSeeder --force');
    }

    /**
     * Migrate tenant database
     */
    public function migrateTenant(Tenant $tenant): void
    {
        $tenant->execute('migrate --database=tenant --force');
    }

    /**
     * Get tenant statistics
     */
    public function getStats(Tenant $tenant): array
    {
        $currentTenant = Tenant::current();

        try {
            $tenant->makeCurrent();

            return [
                'posts_count' => \App\Models\Post::count(),
                'users_count' => \App\Models\User::count(),
                'comments_count' => \App\Models\Comment::count(),
                'published_posts' => \App\Models\Post::published()->count(),
            ];

        } finally {
            $currentTenant?->makeCurrent();
        }
    }
}

## STEP 10: Controllers

# app/Http/Controllers/Api/TenantController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Display a listing of tenants
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::with(['stats'])
            ->latest()
            ->paginate(15);

        return response()->json($tenants);
    }

    /**
     * Store a newly created tenant
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        try {
            $tenant = $this->tenantService->create($request->validated());

            return response()->json([
                'message' => 'Tenant created successfully',
                'data' => [
                    'tenant' => $tenant,
                    'urls' => [
                        'local' => $tenant->local_url,
                        'production' => $tenant->production_url,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified tenant
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $stats = $this->tenantService->getStats($tenant);

        return response()->json([
            'tenant' => $tenant,
            'stats' => $stats,
        ]);
    }

    /**
     * Remove the specified tenant
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        try {
            $this->tenantService->delete($tenant);

            return response()->json([
                'message' => 'Tenant deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

# app/Http/Controllers/Api/Tenant/PostController.php
<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    /**
     * Display a listing of posts
     */
    public function index(): JsonResponse
    {
        $posts = Post::with(['author', 'category'])
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    /**
     * Store a newly created post
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create($request->validated());

        return response()->json([
            'message' => 'Post created successfully',
            'data' => $post->load(['author', 'category'])
        ], 201);
    }

    /**
     * Display the specified post
     */
    public function show(Post $post): JsonResponse
    {
        return response()->json($post->load(['author', 'category', 'comments.author']));
    }

    /**
     * Update the specified post
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $post->update($request->validated());

        return response()->json([
            'message' => 'Post updated successfully',
            'data' => $post->load(['author', 'category'])
        ]);
    }

    /**
     * Remove the specified post
     */
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully'
        ]);
    }
}

## STEP 11: Form Requests

# app/Http/Requests/StoreTenantRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add proper authorization logic
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('tenants', 'slug')
            ],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9.-]+$/',
                Rule::unique('tenants', 'domain')
            ],
            'owner_email' => ['required', 'email', 'max:255'],
            'settings' => ['nullable', 'array'],
        ];
    }
}

# app/Http/Requests/StorePostRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'featured_image' => ['nullable', 'string'],
            'meta_data' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}

## STEP 12: Routes

# routes/api.php
<?php

use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\Tenant\PostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Landlord routes (admin/system management)
Route::prefix('admin')->group(function () {
    Route::apiResource('tenants', TenantController::class);

    // Additional admin routes
    Route::get('tenants/{tenant}/stats', [TenantController::class, 'stats']);
    Route::post('tenants/{tenant}/migrate', [TenantController::class, 'migrate']);
});

// Tenant-specific routes
Route::prefix('{tenant}')
    ->middleware('tenant')
    ->group(function () {

        // Posts management
        Route::apiResource('posts', PostController::class);

        // Additional tenant routes can be added here
        // Route::apiResource('categories', CategoryController::class);
        // Route::apiResource('users', UserController::class);
        // Route::apiResource('comments', CommentController::class);
    });

## STEP 13: Database Migrations

# Run landlord migration
php artisan migrate --path=database/migrations/landlord --database=landlord

# Create tenant migrations directory
mkdir -p database/migrations/tenant

# database/migrations/tenant/2024_01_01_000001_create_posts_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('featured_image')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('author_id');
            $table->fullText(['title', 'content', 'excerpt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

# database/migrations/tenant/2024_01_01_000002_create_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'editor', 'author'])->default('author');
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

## STEP 14: Artisan Commands

# Run migrations for all tenants
php artisan tenants:artisan "migrate --database=tenant --path=database/migrations/tenant"

# Seed all tenant databases
php artisan tenants:artisan "db:seed --database=tenant --class=TenantSeeder"

## STEP 15: Testing Examples

# 1. Create a tenant
POST http://localhost:8000/api/admin/tenants
Content-Type: application/json

{
    "name": "Tech Blog",
    "owner_email": "admin@techblog.com",
    "domain": "techblog"
}

# 2. List all tenants
GET http://localhost:8000/api/admin/tenants

# 3. Get tenant posts (path-based)
GET http://localhost:8000/api/tech-blog/posts

# 4. Create a post for specific tenant
POST http://localhost:8000/api/tech-blog/posts
Content-Type: application/json

{
    "title": "My First Blog Post",
    "content": "This is the content of my first blog post...",
    "status": "published",
    "published_at": "2024-01-01T10:00:00Z"
}

# 5. Get specific post
GET http://localhost:8000/api/tech-blog/posts/1

# 6. Update post
PUT http://localhost:8000/api/tech-blog/posts/1
Content-Type: application/json

{
    "title": "Updated Blog Post Title",
    "content": "Updated content..."
}

# 7. Delete post
DELETE http://localhost:8000/api/tech-blog/posts/1

# 8. Get tenant details and stats
GET http://localhost:8000/api/admin/tenants/1
