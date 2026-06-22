<?php

namespace App\Application\Automation;

use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use App\Jobs\RunAutomationJob;
use Illuminate\Database\Eloquent\Model;

class AutomationDispatcher
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function dispatch(string $event, Model $entity, array $context = []): void
    {
        if (! empty($context['automation_running'])) {
            return;
        }

        $tenantId = $entity->getAttribute('tenant_id') ?? $this->tenantContext->tenantId();
        $workspaceId = $entity->getAttribute('workspace_id') ?? $this->tenantContext->workspaceId();

        if (! $tenantId) {
            return;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant?->hasModule('mod-automation')) {
            return;
        }

        $entityType = match (true) {
            $entity instanceof Lead => 'lead',
            $entity instanceof Deal => 'deal',
            default => null,
        };

        if (! $entityType) {
            return;
        }

        RunAutomationJob::dispatch(
            $tenantId,
            $workspaceId,
            $event,
            $entityType,
            $entity->id,
            $context,
        );
    }
}
