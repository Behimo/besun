<?php

namespace App\Jobs;

use App\Application\Integrations\WooCommerceOrderSyncService;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWooCommerceOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public int $connectionId,
        public int $page = 1,
        public ?string $after = null,
        public bool $isManual = false,
    ) {}

    public function handle(WooCommerceOrderSyncService $syncService): void
    {
        $connection = WooCommerceConnection::withoutGlobalScopes()->find($this->connectionId);

        if (! $connection || ! $connection->is_active || ! $connection->order_sync_enabled) {
            return;
        }

        $after = $this->after ?? $syncService->resolveAfterDate(null, $connection);

        $syncService->processPage($connection, $this->page, $after, $this->isManual);
    }
}
