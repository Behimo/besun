<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStageTransition extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'entity_type',
        'entity_id',
        'from_stage_id',
        'to_stage_id',
        'user_id',
        'transitioned_at',
    ];

    protected function casts(): array
    {
        return [
            'transitioned_at' => 'datetime',
        ];
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
