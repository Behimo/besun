<?php

namespace App\Application\Integrations;

use App\Infrastructure\Integrations\WooCommerce\WooCommerceClient;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use App\Jobs\SyncWooCommerceOrdersJob;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WooCommerceOrderSyncService
{
    public const PER_PAGE = 20;

    public const RUN_IDLE = 'idle';

    public const RUN_RUNNING = 'running';

    public const RUN_COMPLETED = 'completed';

    public const RUN_FAILED = 'failed';

    public function __construct(
        protected WooCommerceOrderService $orderService,
    ) {}

    public function resolveAfterDate(?string $fromDate, ?WooCommerceConnection $connection = null): string
    {
        if ($fromDate) {
            return Carbon::parse($fromDate)->startOfDay()->toIso8601String();
        }

        if ($connection?->order_sync_from_date) {
            return $connection->order_sync_from_date->copy()->startOfDay()->toIso8601String();
        }

        if ($connection?->last_order_sync_at) {
            return $connection->last_order_sync_at->toIso8601String();
        }

        return now()->subDays(30)->startOfDay()->toIso8601String();
    }

    /**
     * @return array{total: int, total_pages: int, from_date: string, after: string}
     */
    public function preview(WooCommerceConnection $connection, ?string $fromDate = null): array
    {
        $this->assertCanSync($connection);

        $after = $this->resolveAfterDate($fromDate, $connection);
        $client = new WooCommerceClient($connection);
        $result = $client->fetchOrders(1, 1, $after);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'total_pages' => (int) ($result['total_pages'] ?? 0),
            'from_date' => Carbon::parse($after)->toDateString(),
            'after' => $after,
        ];
    }

    /**
     * @return array{queued: bool, preview: array<string, mixed>}
     */
    public function queueManualSync(WooCommerceConnection $connection, ?string $fromDate = null): array
    {
        $this->assertCanSync($connection);

        if ($connection->order_sync_run_status === self::RUN_RUNNING) {
            throw ValidationException::withMessages([
                'sync' => ['همگام‌سازی سفارش در حال اجراست. لطفاً تا پایان آن صبر کنید.'],
            ]);
        }

        if ($fromDate) {
            $connection->update(['order_sync_from_date' => Carbon::parse($fromDate)->toDateString()]);
            $connection = $connection->fresh();
        }

        $preview = $this->preview($connection, $fromDate);

        $connection->update([
            'order_sync_run_status' => self::RUN_RUNNING,
            'order_sync_run_progress' => [
                'total' => $preview['total'],
                'processed' => 0,
                'errors' => 0,
                'page' => 0,
                'total_pages' => $preview['total_pages'],
                'from_date' => $preview['from_date'],
                'after' => $preview['after'],
                'started_at' => now()->toIso8601String(),
                'finished_at' => null,
                'message' => $preview['total'] > 0
                    ? "آماده پردازش {$preview['total']} سفارش"
                    : 'سفارشی برای واردسازی یافت نشد',
            ],
            'last_order_sync_status' => 'running',
            'last_order_sync_message' => 'همگام‌سازی در صف اجرا قرار گرفت.',
        ]);

        if ($preview['total'] === 0) {
            $this->markCompleted($connection->fresh(), 0, 0);

            return [
                'queued' => false,
                'preview' => $preview,
            ];
        }

        SyncWooCommerceOrdersJob::dispatch($connection->id, 1, $preview['after'], true)->afterResponse();

        return [
            'queued' => true,
            'preview' => $preview,
        ];
    }

    public function processPage(WooCommerceConnection $connection, int $page, string $after, bool $isManual): void
    {
        $this->assertCanSync($connection);

        $client = new WooCommerceClient($connection);
        $progress = $connection->order_sync_run_progress ?? [];
        $stats = [
            'processed' => (int) Arr::get($progress, 'processed', 0),
            'errors' => (int) Arr::get($progress, 'errors', 0),
        ];

        try {
            $result = $client->fetchOrders($page, self::PER_PAGE, $after);
            $items = $result['items'] ?? [];
            $total = (int) ($result['total'] ?? Arr::get($progress, 'total', 0));
            $totalPages = (int) ($result['total_pages'] ?? 1);

            foreach ($items as $orderPayload) {
                try {
                    $this->orderService->processOrder($connection, $orderPayload);
                    $stats['processed']++;
                } catch (\Throwable) {
                    $stats['errors']++;
                }
            }

            $connection->update([
                'order_sync_run_progress' => array_merge($progress, [
                    'total' => $total,
                    'processed' => $stats['processed'],
                    'errors' => $stats['errors'],
                    'page' => $page,
                    'total_pages' => $totalPages,
                    'after' => $after,
                    'message' => "پردازش صفحه {$page} از {$totalPages} — {$stats['processed']} از {$total} سفارش",
                ]),
                'last_order_sync_message' => "در حال پردازش: {$stats['processed']} از {$total}",
            ]);

            if ($page < $totalPages) {
                SyncWooCommerceOrdersJob::dispatch($connection->id, $page + 1, $after, $isManual)->afterResponse();

                return;
            }

            $this->markCompleted($connection->fresh(), $stats['processed'], $stats['errors']);
        } catch (\Throwable $e) {
            $this->markFailed($connection->fresh(), $e->getMessage(), $stats);

            throw $e;
        }
    }

    /**
     * همگام‌سازی خودکار زمان‌بندی‌شده — فقط سفارش‌های جدید از آخرین اجرا.
     *
     * @return array{processed: int, errors: int}
     */
    public function syncIncremental(WooCommerceConnection $connection): array
    {
        if (! $connection->is_active || ! $connection->order_sync_enabled) {
            return ['processed' => 0, 'errors' => 0];
        }

        if ($connection->order_sync_run_status === self::RUN_RUNNING) {
            return ['processed' => 0, 'errors' => 0];
        }

        $tenant = Tenant::find($connection->tenant_id);
        if (! $tenant || ! $tenant->hasModule('mod-integrations')) {
            return ['processed' => 0, 'errors' => 0];
        }

        $after = $connection->last_order_sync_at?->toIso8601String()
            ?? $this->resolveAfterDate(null, $connection);

        $preview = $this->preview($connection, Carbon::parse($after)->toDateString());

        if ($preview['total'] === 0) {
            return ['processed' => 0, 'errors' => 0];
        }

        $connection->update([
            'order_sync_run_status' => self::RUN_RUNNING,
            'order_sync_run_progress' => [
                'total' => $preview['total'],
                'processed' => 0,
                'errors' => 0,
                'page' => 0,
                'total_pages' => $preview['total_pages'],
                'from_date' => $preview['from_date'],
                'after' => $preview['after'],
                'started_at' => now()->toIso8601String(),
                'finished_at' => null,
                'message' => 'همگام‌سازی خودکار در حال اجرا',
                'mode' => 'incremental',
            ],
        ]);

        SyncWooCommerceOrdersJob::dispatch($connection->id, 1, $preview['after'], false)->afterResponse();

        return ['processed' => 0, 'errors' => 0, 'queued' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(WooCommerceConnection $connection): array
    {
        $progress = $connection->order_sync_run_progress ?? [];

        return [
            'status' => $connection->order_sync_run_status ?? self::RUN_IDLE,
            'progress' => $progress,
            'percent' => $this->progressPercent($progress),
            'last_order_sync_at' => $connection->last_order_sync_at?->toIso8601String(),
            'last_order_sync_status' => $connection->last_order_sync_status,
            'last_order_sync_message' => $connection->last_order_sync_message,
        ];
    }

    protected function progressPercent(array $progress): int
    {
        $total = (int) Arr::get($progress, 'total', 0);
        $processed = (int) Arr::get($progress, 'processed', 0);

        if ($total <= 0) {
            return 0;
        }

        return min(100, (int) round(($processed / $total) * 100));
    }

    protected function markCompleted(WooCommerceConnection $connection, int $processed, int $errors): void
    {
        $progress = $connection->order_sync_run_progress ?? [];

        $connection->update([
            'order_sync_run_status' => self::RUN_COMPLETED,
            'order_sync_run_progress' => array_merge($progress, [
                'processed' => $processed,
                'errors' => $errors,
                'finished_at' => now()->toIso8601String(),
                'message' => "پایان — پردازش: {$processed} | خطا: {$errors}",
            ]),
            'last_order_sync_at' => now(),
            'last_order_sync_status' => 'success',
            'last_order_sync_message' => "پردازش: {$processed} | خطا: {$errors}",
        ]);
    }

    /**
     * @param  array{processed: int, errors: int}  $stats
     */
    protected function markFailed(WooCommerceConnection $connection, string $message, array $stats): void
    {
        $progress = $connection->order_sync_run_progress ?? [];

        $connection->update([
            'order_sync_run_status' => self::RUN_FAILED,
            'order_sync_run_progress' => array_merge($progress, [
                'processed' => $stats['processed'],
                'errors' => $stats['errors'],
                'finished_at' => now()->toIso8601String(),
                'message' => $message,
            ]),
            'last_order_sync_status' => 'failed',
            'last_order_sync_message' => $message,
        ]);
    }

    protected function assertCanSync(WooCommerceConnection $connection): void
    {
        if (! $connection->is_active || ! $connection->order_sync_enabled) {
            throw ValidationException::withMessages([
                'sync' => ['همگام‌سازی سفارش غیرفعال است.'],
            ]);
        }

        $tenant = Tenant::find($connection->tenant_id);
        if (! $tenant || ! $tenant->hasModule('mod-integrations')) {
            throw ValidationException::withMessages([
                'sync' => ['ماژول یکپارچگی فعال نیست.'],
            ]);
        }
    }
}
