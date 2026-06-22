<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'title',
        'description',
        'completion_note',
        'status',
        'priority',
        'effort_points',
        'due_at',
        'completed_at',
        'work_started_at',
        'work_ended_at',
        'time_spent_minutes',
        'reminder_at',
        'reminder_sent_at',
        'assignee_id',
        'created_by',
        'assigned_by',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'work_started_at' => 'datetime',
            'work_ended_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
