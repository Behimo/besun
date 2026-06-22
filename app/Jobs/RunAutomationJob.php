<?php

namespace App\Jobs;

use App\Application\Automation\AutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAutomationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $tenantId,
        public ?int $workspaceId,
        public string $event,
        public string $entityType,
        public int $entityId,
        public array $context = [],
    ) {}

    public function handle(AutomationService $automation): void
    {
        $automation->processEvent(
            $this->tenantId,
            $this->workspaceId,
            $this->event,
            $this->entityType,
            $this->entityId,
            $this->context,
        );
    }
}
