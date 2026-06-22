<?php

namespace App\Infrastructure\Persistence\Scopes;

use App\Infrastructure\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspaceId = app(TenantContext::class)->workspaceId();

        if ($workspaceId) {
            $builder->where($model->getTable().'.workspace_id', $workspaceId);
        }
    }
}
