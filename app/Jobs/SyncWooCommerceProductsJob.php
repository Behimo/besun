<?php

namespace App\Jobs;

use App\Application\Integrations\WooCommerceSyncService;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWooCommerceProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $connectionId,
    ) {}

    public function handle(WooCommerceSyncService $syncService): void
    {
        $connection = WooCommerceConnection::query()->findOrFail($this->connectionId);

        if (! $connection->is_active) {
            return;
        }

        $syncService->sync($connection);
    }
}
