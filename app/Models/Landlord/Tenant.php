<?php

namespace App\Models\Landlord;

use App\Enums\TenantStatuses;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

class Tenant extends BaseTenant
{
    use HasFactory, UsesLandlordConnection;

    protected $fillable = [
        "name", "uuid", "first_name", "last_name",
        "email_verified_at", "user_id", "is_verified",
        "password", "subdomain", "domain", "slug",
        "database_name", "database_host",
        "email","status","setup_error", "setup_step", "setup_completed_at","is_verified","last_login_at"
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $hidden = [
        'password'
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
    }

    protected static function generateUniqueSlug(string $name): string
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

    protected static function generateDatabasename(string $slug): string {
        $prefix = config('app.env') === 'local' ? 'local_tenant_' : 'tenant_';
        return $prefix . $slug . '_' . Str::random(6);
    }

    public function getDatabaseName(): string
    {
        return $this->database_name;
    }

    public function subscriptions(): HasMany {
        return $this->hasMany(TenantSubscription::class);
    }

    public function transactions(): HasMany {
        return $this->hasMany(Transaction::class);
    }

    public function isActive(): bool {
        return $this->status === TenantStatuses::ACTIVE->value;
    }

    public function isPending(): bool {
        return $this->status === TenantStatuses::PENDING->value;
    }

    public function hasFailed(): bool {
        return $this->status === TenantStatuses::FAILED->value;
    }

    public function isOnTrial(): bool {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function scopeActive($query){
        return $query->where('status', 'active');
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
