<?php

namespace App\Jobs;

use App\Application\Integrations\WooCommerceOrderService;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWooCommerceOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $orderPayload
     */
    public function __construct(
        public int $connectionId,
        public array $orderPayload,
    ) {}

    public function handle(WooCommerceOrderService $orderService): void
    {
        $connection = WooCommerceConnection::withoutGlobalScopes()->find($this->connectionId);

        if (! $connection || ! $connection->is_active || ! $connection->order_sync_enabled) {
            return;
        }

        $orderService->processOrder($connection, $this->orderPayload);
    }
}
