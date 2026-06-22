<?php

namespace App\Infrastructure\Integrations\WooCommerce;

use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WooCommerceClient
{
    public function __construct(
        protected WooCommerceConnection $connection,
    ) {}

    public function testConnection(): array
    {
        try {
            $response = $this->client()->get('products', ['per_page' => 1]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($this->connectionErrorMessage($e));
        }

        if (! $response->successful()) {
            throw new \RuntimeException('اتصال به ووکامرس برقرار نشد: '.$this->formatErrorBody($response->body(), $response->status()));
        }

        return $response->json() ?? [];
    }

    public function fetchProducts(int $page = 1, int $perPage = 50): array
    {
        $response = $this->client()->get('products', [
            'page' => $page,
            'per_page' => $perPage,
            'status' => 'any',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت محصولات ووکامرس ناموفق بود: '.$response->body());
        }

        return [
            'items' => $response->json() ?? [],
            'total_pages' => (int) $response->header('X-WP-TotalPages', 1),
            'total' => (int) $response->header('X-WP-Total', 0),
        ];
    }

    public function fetchCategories(int $page = 1, int $perPage = 100): array
    {
        $response = $this->client()->get('products/categories', [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت دسته‌بندی‌های ووکامرس ناموفق بود: '.$response->body());
        }

        return [
            'items' => $response->json() ?? [],
            'total_pages' => (int) $response->header('X-WP-TotalPages', 1),
        ];
    }

    public function fetchOrder(int $orderId): array
    {
        $response = $this->client()->get("orders/{$orderId}");

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت سفارش ووکامرس ناموفق بود: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function fetchOrders(int $page = 1, int $perPage = 20, ?string $after = null): array
    {
        $params = [
            'page' => $page,
            'per_page' => $perPage,
            'orderby' => 'date',
            'order' => 'desc',
            '_fields' => 'id,status,total,currency,billing,line_items,date_created',
        ];

        if ($after) {
            $params['after'] = $after;
        }

        $response = $this->client()->get('orders', $params);

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت سفارش‌های ووکامرس ناموفق بود: '.$response->body());
        }

        return [
            'items' => $response->json() ?? [],
            'total_pages' => (int) $response->header('X-WP-TotalPages', 1),
            'total' => (int) $response->header('X-WP-Total', 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWebhooks(): array
    {
        $response = $this->client()->get('webhooks', ['per_page' => 100]);

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت وب‌هوک‌های ووکامرس ناموفق بود: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function deleteWebhook(int $webhookId): void
    {
        $response = $this->client()->delete("webhooks/{$webhookId}", ['force' => true]);

        if (! $response->successful()) {
            throw new \RuntimeException('حذف وب‌هوک ووکامرس ناموفق بود: '.$response->body());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function registerWebhook(string $topic, string $deliveryUrl, string $secret): array
    {
        $response = $this->client()->post('webhooks', [
            'name' => 'RahbarCRM — '.$topic,
            'topic' => $topic,
            'delivery_url' => $deliveryUrl,
            'secret' => $secret,
            'status' => 'active',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("ثبت وب‌هوک {$topic} ناموفق بود: ".$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<int, int>
     */
    public function registerOrderWebhooks(string $deliveryUrl, string $secret): array
    {
        $topics = ['order.created', 'order.updated'];
        $ids = [];

        foreach ($topics as $topic) {
            $webhook = $this->registerWebhook($topic, $deliveryUrl, $secret);
            if (! empty($webhook['id'])) {
                $ids[] = (int) $webhook['id'];
            }
        }

        return $ids;
    }

    protected function client(): PendingRequest
    {
        $baseUrl = rtrim($this->connection->store_url, '/').'/wp-json/wc/v3/';

        $request = Http::baseUrl($baseUrl)
            ->withHeaders($this->requestHeaders())
            ->withBasicAuth(
                $this->connection->consumer_key,
                $this->connection->consumer_secret,
            )
            ->acceptJson()
            ->timeout(60);

        if (! config('services.woocommerce.verify_ssl', true)) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        $headers = [
            'User-Agent' => (string) config('services.woocommerce.user_agent', 'RahbarCRM/1.0 WooCommerce-API-Client'),
        ];

        $extra = config('services.woocommerce.headers', []);

        if (is_array($extra)) {
            foreach ($extra as $name => $value) {
                if (is_string($name) && (is_string($value) || is_numeric($value))) {
                    $headers[$name] = (string) $value;
                }
            }
        }

        return $headers;
    }

    protected function connectionErrorMessage(ConnectionException $e): string
    {
        $storeUrl = rtrim($this->connection->store_url, '/');
        $message = trim($e->getMessage());

        if (str_contains($message, 'Could not resolve host') || str_contains($message, 'getaddrinfo')) {
            return "آدرس فروشگاه ({$storeUrl}) از سرور CRM قابل دسترسی نیست. آدرس عمومی وردپرس را وارد کنید، نه localhost.";
        }

        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'خطای SSL در اتصال به ووکامرس. گواهی HTTPS فروشگاه را بررسی کنید یا WOOCOMMERCE_VERIFY_SSL=false را در .env تنظیم کنید.';
        }

        return 'ارتباط با فروشگاه برقرار نشد: '.$message;
    }

    protected function formatErrorBody(string $body, int $status): string
    {
        if ($this->isBotProtectionResponse($body, $status)) {
            return $this->botProtectionErrorMessage();
        }

        $decoded = json_decode($body, true);

        if (is_array($decoded) && ! empty($decoded['message'])) {
            return (string) $decoded['message'].' (HTTP '.$status.')';
        }

        $trimmed = trim(strip_tags($body));

        if ($trimmed === '') {
            return 'HTTP '.$status;
        }

        return mb_substr($trimmed, 0, 300).' (HTTP '.$status.')';
    }

    protected function isBotProtectionResponse(string $body, int $status): bool
    {
        if (! in_array($status, [403, 429, 503], true)) {
            return false;
        }

        $lower = strtolower($body);

        $needles = [
            'anti-robot',
            'bn403',
            'bitninja',
            'browser integrity',
            'cf-browser-verification',
            'cloudflare',
            'attention required',
            'captcha',
            'bot access denied',
            'bad bot',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return $status === 403 && str_contains($lower, '<html') && ! str_contains($lower, 'rest');
    }

    protected function botProtectionErrorMessage(): string
    {
        return 'فایروال امنیتی فروشگاه (معمولاً BitNinja) درخواست API سرور CRM را مسدود کرده (HTTP 403). '
            .'در CRM حالت «پلاگین وردپرس» را انتخاب کنید و Rahbar CRM Connector را روی وردپرس نصب کنید — دیگر نیازی به whitelist هاست نیست.';
    }
}
