<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebFormSubmission extends TenantWorkspaceModel
{
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'web_form_id',
        'lead_id',
        'payload',
        'status',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(WebForm::class, 'web_form_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
