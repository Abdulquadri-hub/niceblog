<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Role extends Model
{
    use UsesTenantConnection;

    protected $fillable= [
        'name', 'slug', 'descriptions', 'permissions', 'is_default'
    ];
}
