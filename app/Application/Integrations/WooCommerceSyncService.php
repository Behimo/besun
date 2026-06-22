<?php

namespace App\Application\Integrations;

use App\Application\Quote\QuoteService;
use App\Infrastructure\Integrations\WooCommerce\WooCommerceClient;
use App\Infrastructure\Persistence\Eloquent\Models\Product;
use App\Infrastructure\Persistence\Eloquent\Models\ProductCategory;
use App\Infrastructure\Persistence\Eloquent\Models\WooCommerceConnection;
use Illuminate\Support\Str;

class WooCommerceSyncService
{
    public function sync(WooCommerceConnection $connection): array
    {
        if ($connection->usesPluginBridge()) {
            throw new \RuntimeException(
                'در حالت پلاگین وردپرس، همگام‌سازی از پنل وردپرس انجام می‌شود. پلاگین Rahbar CRM Connector را نصب کرده و از آنجا «همگام‌سازی محصولات» را بزنید.'
            );
        }

        $client = new WooCommerceClient($connection);
        $stats = ['categories' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $categoryMap = $this->syncCategories($client, $connection, $stats);
            $this->syncProducts($client, $connection, $categoryMap, $stats);

            $this->markSyncSuccess($connection, $stats);
        } catch (\Throwable $e) {
            $this->markSyncFailed($connection, $e->getMessage());

            throw $e;
        }

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<int, array<string, mixed>>  $products
     */
    public function ingestFromPlugin(
        WooCommerceConnection $connection,
        array $categories,
        array $products,
        bool $finalize = true,
    ): array {
        $stats = ['categories' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $categoryMap = $this->ingestCategories($connection, $categories, $stats);
        $this->ingestProducts($connection, $products, $categoryMap, $stats);

        if ($finalize) {
            $connection->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => sprintf(
                    'دسته: %d | ایجاد: %d | به‌روز: %d',
                    $stats['categories'],
                    $stats['created'],
                    $stats['updated'],
                ),
            ]);

            app(WooCommerceBridgeService::class)->ackCommands($connection->fresh(), ['sync_products' => true]);
        }

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<string, int>
     */
    public function ingestCategories(WooCommerceConnection $connection, array $categories, array &$stats): array
    {
        $map = [];

        foreach ($categories as $wcCategory) {
            $externalId = (string) ($wcCategory['id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $category = ProductCategory::query()->updateOrCreate(
                [
                    'tenant_id' => $connection->tenant_id,
                    'external_id' => $externalId,
                    'source' => 'woocommerce',
                ],
                [
                    'workspace_id' => $connection->workspace_id,
                    'name' => $wcCategory['name'] ?? 'دسته‌بندی',
                    'slug' => $this->uniqueCategorySlug($wcCategory['slug'] ?? $wcCategory['name'] ?? 'category', $connection->tenant_id, $externalId),
                ],
            );

            $map[$externalId] = $category->id;
            $stats['categories']++;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, int>  $categoryMap
     */
    public function ingestProducts(
        WooCommerceConnection $connection,
        array $products,
        array $categoryMap,
        array &$stats,
    ): void {
        foreach ($products as $wcProduct) {
            try {
                $this->upsertProduct($wcProduct, $connection, $categoryMap, $stats);
            } catch (\Throwable) {
                $stats['errors']++;
            }
        }
    }

    protected function markSyncSuccess(WooCommerceConnection $connection, array $stats): void
    {
        $connection->update([
            'last_sync_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => sprintf(
                'دسته: %d | ایجاد: %d | به‌روز: %d',
                $stats['categories'],
                $stats['created'],
                $stats['updated'],
            ),
        ]);
    }

    protected function markSyncFailed(WooCommerceConnection $connection, string $message): void
    {
        $connection->update([
            'last_sync_at' => now(),
            'last_sync_status' => 'failed',
            'last_sync_message' => $message,
        ]);
    }

    protected function syncCategories(WooCommerceClient $client, WooCommerceConnection $connection, array &$stats): array
    {
        $map = [];
        $page = 1;

        do {
            $result = $client->fetchCategories($page);
            foreach ($result['items'] as $wcCategory) {
                $externalId = (string) ($wcCategory['id'] ?? '');
                if ($externalId === '') {
                    continue;
                }

                $category = ProductCategory::query()->updateOrCreate(
                    [
                        'tenant_id' => $connection->tenant_id,
                        'external_id' => $externalId,
                        'source' => 'woocommerce',
                    ],
                    [
                        'workspace_id' => $connection->workspace_id,
                        'name' => $wcCategory['name'] ?? 'دسته‌بندی',
                        'slug' => $this->uniqueCategorySlug($wcCategory['slug'] ?? $wcCategory['name'] ?? 'category', $connection->tenant_id, $externalId),
                    ],
                );

                $map[$externalId] = $category->id;
                $stats['categories']++;
            }
            $page++;
        } while ($page <= ($result['total_pages'] ?? 1));

        return $map;
    }

    protected function syncProducts(
        WooCommerceClient $client,
        WooCommerceConnection $connection,
        array $categoryMap,
        array &$stats,
    ): void {
        $page = 1;

        do {
            $result = $client->fetchProducts($page);
            foreach ($result['items'] as $wcProduct) {
                try {
                    $this->upsertProduct($wcProduct, $connection, $categoryMap, $stats);
                } catch (\Throwable) {
                    $stats['errors']++;
                }
            }
            $page++;
        } while ($page <= ($result['total_pages'] ?? 1));
    }

    protected function upsertProduct(array $wcProduct, WooCommerceConnection $connection, array $categoryMap, array &$stats): void
    {
        $externalId = (string) ($wcProduct['id'] ?? '');
        if ($externalId === '') {
            $stats['skipped']++;

            return;
        }

        $categoryId = null;
        $wcCategories = $wcProduct['categories'] ?? [];
        if (! empty($wcCategories[0]['id'])) {
            $categoryId = $categoryMap[(string) $wcCategories[0]['id']] ?? null;
        }

        $images = collect($wcProduct['images'] ?? [])->pluck('src')->filter()->values()->all();
        $sku = trim((string) ($wcProduct['sku'] ?? '')) ?: null;

        $payload = [
            'workspace_id' => $connection->workspace_id,
            'woocommerce_connection_id' => $connection->id,
            'product_category_id' => $categoryId,
            'name' => $wcProduct['name'] ?? 'محصول',
            'slug' => $this->uniqueProductSlug($wcProduct['slug'] ?? $wcProduct['name'] ?? 'product', $connection->tenant_id, $externalId),
            'sku' => $sku,
            'description' => $wcProduct['description'] ?? null,
            'short_description' => $wcProduct['short_description'] ?? null,
            'price' => (float) ($wcProduct['regular_price'] ?: $wcProduct['price'] ?: 0),
            'sale_price' => $wcProduct['sale_price'] !== '' ? (float) $wcProduct['sale_price'] : null,
            'stock_quantity' => $wcProduct['stock_quantity'] ?? null,
            'stock_status' => $wcProduct['stock_status'] ?? 'instock',
            'image_url' => $images[0] ?? null,
            'gallery' => count($images) > 1 ? array_slice($images, 1) : null,
            'status' => ($wcProduct['status'] ?? '') === 'publish' ? 'active' : 'draft',
            'source' => 'woocommerce',
            'metadata' => [
                'woocommerce_type' => $wcProduct['type'] ?? 'simple',
                'permalink' => $wcProduct['permalink'] ?? null,
                'parent_external_id' => $wcProduct['parent_id'] ?? null,
            ],
        ];

        $existing = Product::query()
            ->where('tenant_id', $connection->tenant_id)
            ->where('source', 'woocommerce')
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            $existing->update($payload);
            $stats['updated']++;
        } else {
            Product::create(array_merge($payload, [
                'tenant_id' => $connection->tenant_id,
                'external_id' => $externalId,
            ]));
            $stats['created']++;
        }
    }

    protected function uniqueProductSlug(string $name, int $tenantId, string $externalId): string
    {
        $existing = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('source', 'woocommerce')
            ->where('external_id', $externalId)
            ->value('slug');

        if ($existing) {
            return $existing;
        }

        return QuoteService::uniqueSlug($name);
    }

    protected function uniqueCategorySlug(string $name, int $tenantId, string $externalId): string
    {
        $existing = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('source', 'woocommerce')
            ->where('external_id', $externalId)
            ->value('slug');

        if ($existing) {
            return $existing;
        }

        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 1;

        while (ProductCategory::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
