<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasCrmProducts;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends TenantWorkspaceModel
{
    use HasCrmProducts;
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'campaign_id',
        'marketing_stage_id',
        'name',
        'email',
        'phone',
        'company',
        'job_title',
        'city',
        'score',
        'status',
        'converted_at',
        'source',
        'assigned_to',
        'notes',
        'next_follow_up_at',
        'follow_up_reminder_at',
        'follow_up_reminder_sent_at',
        'contact_id',
        'department',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function marketingStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'marketing_stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'follow_up_reminder_at' => 'datetime',
            'follow_up_reminder_sent_at' => 'datetime',
        ];
    }

    protected function crmProductEntityType(): string
    {
        return 'lead';
    }
}
