<?php

namespace App\Application\Integrations;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use App\Jobs\ProcessWooCommerceOrderJob;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class WooCommerceBridgeService
{
    public function __construct(
        protected WooCommerceSyncService $productSync,
        protected WooCommerceOrderService $orderService,
        protected WooCommerceWebhookService $webhooks,
        protected TenantContext $tenantContext,
    ) {}

    public function resolveConnection(string $token): ?WooCommerceConnection
    {
        return WooCommerceConnection::withoutGlobalScopes()
            ->where('webhook_token', $token)
            ->where('is_active', true)
            ->first();
    }

    public function assertBridgeAccess(WooCommerceConnection $connection): void
    {
        $tenant = Tenant::find($connection->tenant_id);

        if (! $tenant || ! $tenant->hasModule('mod-integrations')) {
            abort(402, 'ماژول یکپارچگی فعال نیست.');
        }
    }

    public function verifyRequest(string $payload, ?string $signature, WooCommerceConnection $connection): void
    {
        if (! $this->webhooks->verifySignature($payload, $signature, $connection)) {
            abort(401, 'امضای درخواست نامعتبر است.');
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function ping(WooCommerceConnection $connection, array $meta = []): array
    {
        $this->tenantContext->set($connection->tenant_id, $connection->workspace_id);

        $connection->update([
            'plugin_last_ping_at' => now(),
            'plugin_version' => $meta['plugin_version'] ?? $connection->plugin_version,
            'connection_mode' => 'plugin',
        ]);

        return [
            'message' => 'اتصال پلاگین برقرار است.',
            'store_url' => $connection->store_url,
            'order_sync_enabled' => $connection->order_sync_enabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncProducts(WooCommerceConnection $connection, array $payload): array
    {
        $this->tenantContext->set($connection->tenant_id, $connection->workspace_id);

        $categories = is_array($payload['categories'] ?? null) ? $payload['categories'] : [];
        $products = is_array($payload['products'] ?? null) ? $payload['products'] : [];
        $finalize = (bool) ($payload['finalize'] ?? true);

        $stats = $this->productSync->ingestFromPlugin($connection, $categories, $products, $finalize);

        $connection->update([
            'plugin_last_ping_at' => now(),
            'connection_mode' => 'plugin',
        ]);

        return [
            'message' => $finalize ? 'همگام‌سازی محصولات انجام شد.' : 'دسته محصولات دریافت شد.',
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncOrders(WooCommerceConnection $connection, array $payload): array
    {
        $this->tenantContext->set($connection->tenant_id, $connection->workspace_id);

        if (! $connection->order_sync_enabled) {
            abort(422, 'همگام‌سازی سفارش غیرفعال است.');
        }

        $orders = is_array($payload['orders'] ?? null) ? $payload['orders'] : [];
        $processed = 0;
        $errors = 0;

        foreach ($orders as $orderPayload) {
            if (! is_array($orderPayload) || empty($orderPayload['id'])) {
                $errors++;

                continue;
            }

            try {
                DB::transaction(fn () => $this->orderService->processOrder($connection, $orderPayload));
                $processed++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        $finalize = (bool) ($payload['finalize'] ?? true);

        if ($finalize) {
            $connection->update([
                'last_order_sync_at' => now(),
                'last_order_sync_status' => $errors > 0 && $processed === 0 ? 'failed' : 'success',
                'last_order_sync_message' => "پردازش: {$processed}".($errors ? "، خطا: {$errors}" : ''),
                'order_sync_run_status' => 'completed',
                'plugin_last_ping_at' => now(),
                'connection_mode' => 'plugin',
            ]);

            $this->ackCommands($connection->fresh(), ['sync_orders' => true]);
        } else {
            $connection->update([
                'plugin_last_ping_at' => now(),
                'connection_mode' => 'plugin',
            ]);
        }

        return [
            'message' => "{$processed} سفارش پردازش شد.",
            'processed' => $processed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     */
    public function pushOrder(WooCommerceConnection $connection, array $orderPayload): array
    {
        $this->tenantContext->set($connection->tenant_id, $connection->workspace_id);

        if (! $connection->order_sync_enabled) {
            abort(422, 'همگام‌سازی سفارش غیرفعال است.');
        }

        ProcessWooCommerceOrderJob::dispatch($connection->id, $orderPayload);

        $connection->update([
            'plugin_last_ping_at' => now(),
            'connection_mode' => 'plugin',
        ]);

        return ['message' => 'queued'];
    }

    /**
     * @return array<string, mixed>
     */
    public function pollCommands(WooCommerceConnection $connection): array
    {
        $commands = $connection->plugin_pending_commands ?? [];
        $pending = [];

        if (! empty($commands['sync_products'])) {
            $pending[] = [
                'action' => 'sync_products',
                'requested_at' => $commands['sync_products'],
            ];
        }

        if (! empty($commands['sync_orders'])) {
            $pending[] = [
                'action' => 'sync_orders',
                'from_date' => $commands['orders_from_date'] ?? $connection->order_sync_from_date?->toDateString(),
                'requested_at' => $commands['sync_orders'],
            ];
        }

        return [
            'commands' => $pending,
            'order_sync_enabled' => $connection->order_sync_enabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ackCommands(WooCommerceConnection $connection, array $payload): array
    {
        $commands = $connection->plugin_pending_commands ?? [];

        if (! empty($payload['sync_products'])) {
            unset($commands['sync_products']);
        }

        if (! empty($payload['sync_orders'])) {
            unset($commands['sync_orders'], $commands['orders_from_date']);
        }

        $connection->update([
            'plugin_pending_commands' => $commands === [] ? null : $commands,
            'plugin_last_ping_at' => now(),
        ]);

        return ['message' => 'ok'];
    }

    public function requestProductSync(WooCommerceConnection $connection): void
    {
        $commands = $connection->plugin_pending_commands ?? [];
        $commands['sync_products'] = now()->toIso8601String();

        $connection->update([
            'plugin_pending_commands' => $commands,
            'connection_mode' => 'plugin',
            'last_sync_status' => 'running',
            'last_sync_message' => 'در انتظار پلاگین وردپرس…',
        ]);
    }

    public function requestOrderSync(WooCommerceConnection $connection, ?string $fromDate = null): void
    {
        if ($fromDate) {
            $connection->order_sync_from_date = $fromDate;
        }

        $commands = $connection->plugin_pending_commands ?? [];
        $commands['sync_orders'] = now()->toIso8601String();
        $commands['orders_from_date'] = $fromDate ?? $connection->order_sync_from_date?->toDateString();

        $connection->update([
            'plugin_pending_commands' => $commands,
            'connection_mode' => 'plugin',
            'order_sync_run_status' => 'running',
            'last_order_sync_status' => 'running',
            'last_order_sync_message' => 'در انتظار پلاگین وردپرس…',
        ]);
    }

    /**
     * @return array{products: bool, orders: bool}
     */
    public function pendingSyncFlags(WooCommerceConnection $connection): array
    {
        $commands = $connection->plugin_pending_commands ?? [];

        return [
            'products' => ! empty($commands['sync_products']),
            'orders' => ! empty($commands['sync_orders']),
        ];
    }
}
