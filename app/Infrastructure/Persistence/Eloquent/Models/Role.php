<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
        'label',
        'department',
        'is_manager',
        'parent_role',
    ];

    protected function casts(): array
    {
        return [
            'is_manager' => 'boolean',
        ];
    }
}
