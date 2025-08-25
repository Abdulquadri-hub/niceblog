<?php

namespace App\Enums;

enum TenantStatuses: string
{
    case PENDING = "pending";
    case CREATING_DATABASE = 'creating_database';
    case CREATING_OWNER = 'creating_owner';
    case RUNNING_MIGRATIONS = 'running_migrations';
    case SEEDING_DATA = 'seeding_data';
    case ACTIVE = 'active';
    case FAILED = 'failed';
    case SUSPENDED = 'suspended';
    case ERROR = 'error';
}
