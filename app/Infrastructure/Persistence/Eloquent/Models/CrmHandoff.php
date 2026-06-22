<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmHandoff extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'entity_type',
        'entity_id',
        'from_user_id',
        'to_user_id',
        'from_stage_id',
        'to_stage_id',
        'handoff_type',
        'note',
        'status',
        'returned_to_user_id',
        'parent_handoff_id',
        'task_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function returnedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to_user_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    public function parentHandoff(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_handoff_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
