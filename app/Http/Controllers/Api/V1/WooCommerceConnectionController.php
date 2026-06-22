<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Integrations\WooCommerceBridgeService;
use App\Application\Integrations\WooCommercePluginPackageService;
use App\Application\Integrations\WooCommerceWebhookService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use App\Infrastructure\Services\TenantContext;
use App\Support\PersianDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WooCommerceConnectionController extends Controller
{
    use ChecksCrmAccess;

    public function show(WooCommerceWebhookService $webhookService, WooCommerceBridgeService $bridge): JsonResponse
    {
        $this->requirePermission('integrations.manage');

        $connection = WooCommerceConnection::query()->first();

        if ($connection) {
            $connection = $webhookService->ensureConnectionSecrets($connection);
        }

        return response()->json([
            'connection' => $connection ? $this->formatConnection($connection, $bridge) : null,
        ]);
    }

    public function store(Request $request, WooCommerceWebhookService $webhookService, WooCommerceBridgeService $bridge): JsonResponse
    {
        $this->requirePermission('integrations.manage');

        $data = $request->validate([
            'store_url' => ['required', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'order_sync_enabled' => ['nullable', 'boolean'],
            'order_sync_from_date' => ['nullable', 'string', 'max:32'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        $data['order_sync_from_date'] = $this->normalizeRequestDate(
            'order_sync_from_date',
            $data['order_sync_from_date'] ?? null,
        );

        $context = app(TenantContext::class);

        $connection = WooCommerceConnection::query()->firstOrNew([
            'tenant_id' => $context->tenantId(),
            'workspace_id' => $context->workspaceId(),
        ]);

        $connection->fill([
            'store_url' => rtrim($data['store_url'], '/'),
            'connection_mode' => 'plugin',
            'is_active' => $data['is_active'] ?? true,
            'order_sync_enabled' => $data['order_sync_enabled'] ?? true,
            'order_sync_from_date' => $data['order_sync_from_date'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
        ])->save();

        $connection = $webhookService->ensureConnectionSecrets($connection);

        return response()->json(['connection' => $this->formatConnection($connection, $bridge)], 201);
    }

    public function test(WooCommerceBridgeService $bridge): JsonResponse
    {
        $this->requirePermission('integrations.manage');

        $connection = WooCommerceConnection::query()->firstOrFail();

        if ($connection->plugin_last_ping_at && $connection->plugin_last_ping_at->greaterThan(now()->subDay())) {
            return response()->json([
                'message' => 'پلاگین متصل است.',
                'connection' => $this->formatConnection($connection, $bridge),
            ]);
        }

        return response()->json([
            'message' => 'پلاگین هنوز وصل نشده. پس از نصب، در وردپرس «تست اتصال» را بزنید.',
        ], 422);
    }

    public function sync(WooCommerceBridgeService $bridge): JsonResponse
    {
        $this->requirePermission('integrations.manage');

        $connection = WooCommerceConnection::query()->firstOrFail();
        $bridge->requestProductSync($connection);

        return response()->json([
            'message' => 'درخواست همگام‌سازی محصولات ارسال شد.',
            'connection' => $this->formatConnection($connection->fresh(), $bridge),
        ]);
    }

    public function syncOrders(Request $request, WooCommerceBridgeService $bridge): JsonResponse
    {
        $this->requirePermission('integrations.manage');

        $data = $request->validate([
            'from_date' => ['nullable', 'string', 'max:32'],
        ]);

        $fromDate = $this->normalizeRequestDate('from_date', $data['from_date'] ?? null);

        $connection = WooCommerceConnection::query()->firstOrFail();
        $bridge->requestOrderSync($connection, $fromDate);

        return response()->json([
            'message' => 'درخواست همگام‌سازی سفارش‌ها ارسال شد.',
            'connection' => $this->formatConnection($connection->fresh(), $bridge),
        ]);
    }

    public function downloadPlugin(WooCommercePluginPackageService $packager): StreamedResponse
    {
        $this->requirePermission('integrations.manage');

        return $packager->downloadResponse();
    }

    protected function formatConnection(WooCommerceConnection $connection, WooCommerceBridgeService $bridge): array
    {
        $pending = $bridge->pendingSyncFlags($connection);

        return [
            'id' => $connection->id,
            'store_url' => $connection->store_url,
            'is_active' => $connection->is_active,
            'order_sync_enabled' => $connection->order_sync_enabled,
            'order_sync_from_date' => $connection->order_sync_from_date?->toDateString(),
            'campaign_id' => $connection->campaign_id,
            'bridge_token' => $connection->webhook_token,
            'bridge_secret' => $connection->webhook_secret,
            'plugin_last_ping_at' => $connection->plugin_last_ping_at?->toIso8601String(),
            'plugin_version' => $connection->plugin_version,
            'plugin_package_version' => WooCommercePluginPackageService::VERSION,
            'plugin_connected' => $connection->plugin_last_ping_at
                ? $connection->plugin_last_ping_at->greaterThan(now()->subDay())
                : false,
            'pending_sync' => $pending,
            'last_sync_at' => $connection->last_sync_at?->toIso8601String(),
            'last_sync_status' => $connection->last_sync_status,
            'last_sync_message' => $connection->last_sync_message,
            'last_order_sync_at' => $connection->last_order_sync_at?->toIso8601String(),
            'last_order_sync_status' => $connection->last_order_sync_status,
            'last_order_sync_message' => $connection->last_order_sync_message,
            'updated_at' => $connection->updated_at?->toIso8601String(),
        ];
    }

    protected function normalizeRequestDate(string $field, mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $parsed = PersianDates::toDateString((string) $value);

        if ($parsed === null) {
            throw ValidationException::withMessages([
                $field => ['تاریخ وارد شده معتبر نیست.'],
            ]);
        }

        return $parsed;
    }
}
