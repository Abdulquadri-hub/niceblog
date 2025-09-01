<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Settings extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'key_name', 'value', 'type', 'description', 'is_public', 'group_name'
    ];
}
