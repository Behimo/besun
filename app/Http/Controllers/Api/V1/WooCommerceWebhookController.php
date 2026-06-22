<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Integrations\WooCommerceWebhookService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use App\Jobs\ProcessWooCommerceOrderJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooCommerceWebhookController extends Controller
{
    public function handle(
        Request $request,
        string $token,
        WooCommerceWebhookService $webhookService,
    ): JsonResponse {
        $connection = WooCommerceConnection::withoutGlobalScopes()
            ->where('webhook_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            return response()->json(['message' => 'اتصال یافت نشد.'], 404);
        }

        if (! $connection->order_sync_enabled) {
            return response()->json(['message' => 'همگام‌سازی سفارش غیرفعال است.'], 422);
        }

        $tenant = Tenant::find($connection->tenant_id);
        if (! $tenant || ! $tenant->hasModule('mod-integrations')) {
            return response()->json(['message' => 'ماژول یکپارچگی فعال نیست.'], 402);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-WC-Webhook-Signature');

        if (! $webhookService->verifySignature($payload, $signature, $connection)) {
            return response()->json(['message' => 'امضای وب‌هوک نامعتبر است.'], 401);
        }

        $orderPayload = json_decode($payload, true);
        if (! is_array($orderPayload) || empty($orderPayload['id'])) {
            return response()->json(['message' => 'بدنه وب‌هوک نامعتبر است.'], 422);
        }

        ProcessWooCommerceOrderJob::dispatch($connection->id, $orderPayload);

        return response()->json(['message' => 'queued']);
    }
}
