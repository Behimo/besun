<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Scopes\TenantScope;
use App\Infrastructure\Persistence\Scopes\WorkspaceScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class TenantWorkspaceModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (self $model): void {
            if (! $model->tenant_id) {
                $model->tenant_id = app(\App\Infrastructure\Services\TenantContext::class)->tenantId();
            }
            if (! $model->workspace_id) {
                $model->workspace_id = app(\App\Infrastructure\Services\TenantContext::class)->workspaceId();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
