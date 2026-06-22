<?php

namespace App\Infrastructure\Services;

class TenantContext
{
    protected ?int $tenantId = null;

    protected ?int $workspaceId = null;

    public function set(?int $tenantId, ?int $workspaceId = null): void
    {
        $this->tenantId = $tenantId;
        $this->workspaceId = $workspaceId;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function workspaceId(): ?int
    {
        return $this->workspaceId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->workspaceId = null;
    }
}
