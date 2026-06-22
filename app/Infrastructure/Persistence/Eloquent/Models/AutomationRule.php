<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'name',
        'description',
        'trigger_event',
        'conditions',
        'actions',
        'runtime_state',
        'is_active',
        'priority',
        'last_run_at',
        'run_count',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'runtime_state' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
