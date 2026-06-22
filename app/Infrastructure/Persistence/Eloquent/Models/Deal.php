<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasCrmProducts;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends TenantWorkspaceModel
{
    use HasCrmProducts;
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'pipeline_stage_id',
        'contact_id',
        'lead_id',
        'title',
        'amount',
        'currency',
        'assigned_to',
        'expected_close_date',
        'notes',
        'next_follow_up_at',
        'follow_up_reminder_at',
        'follow_up_reminder_sent_at',
        'department',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_close_date' => 'date',
            'next_follow_up_at' => 'datetime',
            'follow_up_reminder_at' => 'datetime',
            'follow_up_reminder_sent_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    protected function crmProductEntityType(): string
    {
        return 'deal';
    }
}
