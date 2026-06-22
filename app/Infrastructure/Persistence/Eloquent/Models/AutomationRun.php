<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends TenantWorkspaceModel
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'automation_rule_id',
        'trigger_event',
        'entity_type',
        'entity_id',
        'status',
        'result',
        'error_message',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
