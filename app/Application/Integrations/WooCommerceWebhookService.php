<?php

namespace App\Application\Integrations;

use App\Infrastructure\Integrations\WooCommerce\WooCommerceClient;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Support\Str;

class WooCommerceWebhookService
{
    public function ensureConnectionSecrets(WooCommerceConnection $connection): WooCommerceConnection
    {
        $updates = [];

        if (! $connection->webhook_token) {
            $updates['webhook_token'] = Str::random(48);
        }

        if (! $connection->getRawOriginal('webhook_secret')) {
            $updates['webhook_secret'] = Str::random(32);
        }

        if ($updates !== []) {
            $connection->fill($updates)->save();
        }

        return $connection->fresh();
    }

    public function webhookUrl(WooCommerceConnection $connection): string
    {
        $connection = $this->ensureConnectionSecrets($connection);
        $baseUrl = rtrim((string) config('app.url'), '/');

        return "{$baseUrl}/api/v1/integrations/woocommerce/webhook/{$connection->webhook_token}";
    }

    public function bridgeBaseUrl(WooCommerceConnection $connection): string
    {
        $connection = $this->ensureConnectionSecrets($connection);
        $baseUrl = rtrim((string) config('app.url'), '/');

        return "{$baseUrl}/api/v1/integrations/woocommerce/bridge/{$connection->webhook_token}";
    }

    public function verifySignature(string $payload, ?string $signature, WooCommerceConnection $connection): bool
    {
        if (! $signature || ! $connection->webhook_secret) {
            return false;
        }

        $secret = $connection->webhook_secret;
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * @return array<int, int>
     */
    public function registerWebhooks(WooCommerceConnection $connection): array
    {
        $connection = $this->ensureConnectionSecrets($connection);
        $client = new WooCommerceClient($connection);
        $deliveryUrl = $this->webhookUrl($connection);
        $secret = $connection->webhook_secret;

        $this->removeRegisteredWebhooks($connection, $client);

        $ids = $client->registerOrderWebhooks($deliveryUrl, $secret);

        $connection->update(['external_webhook_ids' => $ids]);

        return $ids;
    }

    protected function removeRegisteredWebhooks(WooCommerceConnection $connection, WooCommerceClient $client): void
    {
        $existingIds = $connection->external_webhook_ids ?? [];

        foreach ($existingIds as $webhookId) {
            try {
                $client->deleteWebhook((int) $webhookId);
            } catch (\Throwable) {
                // ignore stale webhook ids
            }
        }
    }
}
