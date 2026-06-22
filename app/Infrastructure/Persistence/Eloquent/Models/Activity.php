<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'user_id',
        'type',
        'subject',
        'body',
        'duration_minutes',
        'happened_at',
        'scheduled_at',
        'reminder_at',
        'reminder_sent_at',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
