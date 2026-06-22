<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'name',
        'sort_order',
        'color',
        'type',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'marketing_stage_id');
    }
}
