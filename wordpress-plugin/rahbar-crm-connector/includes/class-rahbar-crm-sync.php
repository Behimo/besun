<?php

if (! defined('ABSPATH')) {
    exit;
}

class Rahbar_Crm_Sync
{
    private Rahbar_Crm_Api $api;

    public function __construct(Rahbar_Crm_Api $api)
    {
        $this->api = $api;
    }

    public function register_hooks(): void
    {
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 20, 1);
        add_action('rahbar_crm_poll_commands', [$this, 'poll_commands']);
        add_action('admin_init', [$this, 'poll_commands']);
    }

    public function poll_commands(): void
    {
        if (! $this->api->is_configured()) {
            return;
        }

        static $polled = false;
        if ($polled && ! wp_doing_cron()) {
            return;
        }
        $polled = true;

        $response = $this->api->fetch_commands();
        if (! $response['ok'] || empty($response['data']['commands'])) {
            return;
        }

        foreach ($response['data']['commands'] as $command) {
            if (! is_array($command) || empty($command['action'])) {
                continue;
            }

            if ($command['action'] === 'sync_products') {
                $result = $this->sync_all_products();
                if ($result['ok']) {
                    $this->api->ack_commands(['sync_products' => true]);
                }
            }

            if ($command['action'] === 'sync_orders') {
                $fromDate = ! empty($command['from_date']) ? (string) $command['from_date'] : '';
                $result = $this->sync_all_orders($fromDate);
                if ($result['ok']) {
                    $this->api->ack_commands(['sync_orders' => true]);
                }
            }
        }
    }

    public function on_order_status_changed(int $order_id): void
    {
        if (! $this->api->is_configured()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $payload = $this->format_order($order);
        $this->api->post('order', $payload);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function test_connection(): array
    {
        $result = $this->api->post('ping', [
            'plugin_version' => RAHBAR_CRM_CONNECTOR_VERSION,
            'site_url' => home_url('/'),
        ]);

        if ($result['ok']) {
            return ['ok' => true, 'message' => $result['data']['message'] ?? 'اتصال برقرار است.'];
        }

        return ['ok' => false, 'message' => $result['error'] ?? 'اتصال ناموفق بود.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sync_all_products(): array
    {
        if (! $this->api->is_configured()) {
            return ['ok' => false, 'message' => 'تنظیمات CRM کامل نیست.'];
        }

        $categories = $this->collect_categories();
        $page = 1;
        $perPage = 50;
        $totalPages = 1;
        $totalCreated = 0;
        $totalUpdated = 0;

        do {
            $query = new WP_Query([
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => $perPage,
                'paged' => $page,
                'fields' => 'ids',
            ]);

            $products = [];
            foreach ($query->posts as $productId) {
                $product = wc_get_product($productId);
                if ($product) {
                    $products[] = $this->format_product($product);
                }
            }

            $totalPages = max(1, (int) $query->max_num_pages);
            $finalize = $page >= $totalPages;

            $result = $this->api->post('products', [
                'categories' => $page === 1 ? $categories : [],
                'products' => $products,
                'page' => $page,
                'total_pages' => $totalPages,
                'finalize' => $finalize,
            ]);

            if (! $result['ok']) {
                return ['ok' => false, 'message' => $result['error'] ?? 'همگام‌سازی محصولات ناموفق بود.'];
            }

            $stats = $result['data']['stats'] ?? [];
            $totalCreated += (int) ($stats['created'] ?? 0);
            $totalUpdated += (int) ($stats['updated'] ?? 0);
            $page++;
        } while ($page <= $totalPages);

        return [
            'ok' => true,
            'message' => sprintf('محصولات همگام شد — ایجاد: %d، به‌روز: %d', $totalCreated, $totalUpdated),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sync_all_orders(?string $fromDateOverride = null): array
    {
        if (! $this->api->is_configured()) {
            return ['ok' => false, 'message' => 'تنظیمات CRM کامل نیست.'];
        }

        $fromDate = $fromDateOverride ?? (string) get_option('rahbar_crm_order_from_date', '');
        $args = [
            'limit' => 20,
            'paginate' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ];

        if ($fromDate !== '') {
            $args['date_created'] = '>'.$fromDate.'T00:00:00';
        }

        $page = 1;
        $totalPages = 1;
        $processed = 0;
        $errors = 0;

        do {
            $args['page'] = $page;
            $results = wc_get_orders($args);
            $orders = [];

            foreach ($results->orders as $order) {
                $orders[] = $this->format_order($order);
            }

            $totalPages = max(1, (int) $results->max_num_pages);
            $finalize = $page >= $totalPages;

            $result = $this->api->post('orders', [
                'orders' => $orders,
                'page' => $page,
                'total_pages' => $totalPages,
                'finalize' => $finalize,
            ]);

            if (! $result['ok']) {
                return ['ok' => false, 'message' => $result['error'] ?? 'همگام‌سازی سفارش‌ها ناموفق بود.'];
            }

            $processed += (int) ($result['data']['processed'] ?? 0);
            $errors += (int) ($result['data']['errors'] ?? 0);
            $page++;
        } while ($page <= $totalPages);

        return [
            'ok' => true,
            'message' => sprintf('سفارش‌ها همگام شد — پردازش: %d، خطا: %d', $processed, $errors),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collect_categories(): array
    {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $categories = [];
        foreach ($terms as $term) {
            $categories[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    private function format_product(WC_Product $product): array
    {
        $categoryIds = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
        $categories = [];
        foreach ($categoryIds as $termId) {
            $term = get_term($termId, 'product_cat');
            if ($term && ! is_wp_error($term)) {
                $categories[] = ['id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
            }
        }

        $imageId = $product->get_image_id();
        $images = [];
        if ($imageId) {
            $src = wp_get_attachment_url($imageId);
            if ($src) {
                $images[] = ['src' => $src];
            }
        }

        foreach ($product->get_gallery_image_ids() as $galleryId) {
            $src = wp_get_attachment_url($galleryId);
            if ($src) {
                $images[] = ['src' => $src];
            }
        }

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'sku' => $product->get_sku(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price' => $product->get_price(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'status' => $product->get_status(),
            'type' => $product->get_type(),
            'permalink' => get_permalink($product->get_id()),
            'parent_id' => $product->get_parent_id(),
            'categories' => $categories,
            'images' => $images,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function format_order(WC_Order $order): array
    {
        $lineItems = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $lineItems[] = [
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : '',
            ];
        }

        return [
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'city' => $order->get_billing_city(),
                'company' => $order->get_billing_company(),
            ],
            'line_items' => $lineItems,
        ];
    }
}
