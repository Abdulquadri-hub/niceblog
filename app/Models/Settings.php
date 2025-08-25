<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Settings extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'key_name', 'value', 'type', 'description', 'is_public', 'group_name'
    ];
}
