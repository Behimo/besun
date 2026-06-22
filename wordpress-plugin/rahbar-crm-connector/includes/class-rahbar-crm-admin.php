<?php

if (! defined('ABSPATH')) {
    exit;
}

class Rahbar_Crm_Admin
{
    private Rahbar_Crm_Api $api;

    private Rahbar_Crm_Sync $sync;

    public function __construct(Rahbar_Crm_Api $api, Rahbar_Crm_Sync $sync)
    {
        $this->api = $api;
        $this->sync = $sync;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_rahbar_crm_test', [$this, 'handle_test']);
        add_action('admin_post_rahbar_crm_sync_products', [$this, 'handle_sync_products']);
        add_action('admin_post_rahbar_crm_sync_orders', [$this, 'handle_sync_orders']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            'Rahbar CRM',
            'Rahbar CRM',
            'manage_woocommerce',
            'rahbar-crm-connector',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('rahbar_crm_connector', 'rahbar_crm_crm_url', [
            'type' => 'string',
            'sanitize_callback' => static fn ($value) => esc_url_raw(rtrim((string) $value, '/')),
        ]);
        register_setting('rahbar_crm_connector', 'rahbar_crm_bridge_token', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('rahbar_crm_connector', 'rahbar_crm_bridge_secret', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('rahbar_crm_connector', 'rahbar_crm_order_from_date', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->api->get_settings();
        $notice = $this->consume_notice();
        ?>
        <div class="wrap rahbar-crm-wrap">
            <h1>Rahbar CRM Connector</h1>
            <p class="rahbar-crm-lead">اتصال ووکامرس به Rahbar CRM — بدون نیاز به whitelist IP در هاست.</p>

            <?php if ($notice) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="rahbar-crm-steps">
                <h2>راه‌اندازی</h2>
                <ol>
                    <li>در CRM: یکپارچه‌سازی → ووکامرس → حالت «پلاگین وردپرس» → ذخیره</li>
                    <li>Bridge Token و Secret را از CRM کپی کنید</li>
                    <li>در این صفحه تنظیمات را وارد و «تست اتصال» بزنید</li>
                    <li>«همگام‌سازی محصولات» و «همگام‌سازی سفارش‌ها» را اجرا کنید</li>
                </ol>
            </div>

            <form method="post" action="options.php" class="rahbar-crm-settings">
                <?php settings_fields('rahbar_crm_connector'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rahbar_crm_crm_url">آدرس CRM</label></th>
                        <td>
                            <input type="url" class="regular-text" id="rahbar_crm_crm_url" name="rahbar_crm_crm_url" value="<?php echo esc_attr($settings['crm_url']); ?>" placeholder="https://crm.example.com" />
                            <p class="description">آدرس عمومی CRM — بدون اسلش انتهایی</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rahbar_crm_bridge_token">Bridge Token</label></th>
                        <td>
                            <input type="text" class="regular-text code" id="rahbar_crm_bridge_token" name="rahbar_crm_bridge_token" value="<?php echo esc_attr($settings['bridge_token']); ?>" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rahbar_crm_bridge_secret">Bridge Secret</label></th>
                        <td>
                            <input type="password" class="regular-text code" id="rahbar_crm_bridge_secret" name="rahbar_crm_bridge_secret" value="<?php echo esc_attr($settings['bridge_secret']); ?>" autocomplete="new-password" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rahbar_crm_order_from_date">سفارش‌ها از تاریخ</label></th>
                        <td>
                            <input type="date" id="rahbar_crm_order_from_date" name="rahbar_crm_order_from_date" value="<?php echo esc_attr(get_option('rahbar_crm_order_from_date', '')); ?>" />
                            <p class="description">فقط برای همگام‌سازی دستی سفارش‌ها</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr />
            <h2>عملیات</h2>
            <p class="rahbar-crm-actions">
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rahbar_crm_test'), 'rahbar_crm_action')); ?>">تست اتصال</a>
                <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rahbar_crm_sync_products'), 'rahbar_crm_action')); ?>">همگام‌سازی محصولات</a>
                <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rahbar_crm_sync_orders'), 'rahbar_crm_action')); ?>">همگام‌سازی سفارش‌ها</a>
            </p>
            <p class="description">سفارش‌های جدید با تغییر وضعیت، خودکار به CRM ارسال می‌شوند.</p>
        </div>
        <style>
            .rahbar-crm-wrap .rahbar-crm-lead { font-size: 14px; margin-bottom: 16px; }
            .rahbar-crm-steps { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; margin: 16px 0 24px; max-width: 760px; }
            .rahbar-crm-steps ol { margin: 8px 0 0 18px; }
            .rahbar-crm-actions .button { margin-left: 6px; }
        </style>
        <?php
    }

    public function handle_test(): void
    {
        $this->guard();
        $result = $this->sync->test_connection();
        $this->redirect($result['message'], $result['ok'] ? 'success' : 'error');
    }

    public function handle_sync_products(): void
    {
        $this->guard();
        $result = $this->sync->sync_all_products();
        $this->redirect($result['message'], $result['ok'] ? 'success' : 'error');
    }

    public function handle_sync_orders(): void
    {
        $this->guard();
        $result = $this->sync->sync_all_orders();
        $this->redirect($result['message'], $result['ok'] ? 'success' : 'error');
    }

    private function guard(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('rahbar_crm_action');
    }

    /**
     * @return array{message: string, type: string}|null
     */
    private function consume_notice(): ?array
    {
        $key = 'rahbar_crm_notice_'.get_current_user_id();
        $notice = get_transient($key);

        if (! is_array($notice) || empty($notice['message'])) {
            return null;
        }

        delete_transient($key);

        return [
            'message' => (string) $notice['message'],
            'type' => in_array($notice['type'] ?? '', ['success', 'error', 'warning', 'info'], true)
                ? (string) $notice['type']
                : 'success',
        ];
    }

    private function redirect(string $message, string $type): void
    {
        set_transient('rahbar_crm_notice_'.get_current_user_id(), [
            'message' => $message,
            'type' => $type,
        ], 30);

        wp_safe_redirect(admin_url('admin.php?page=rahbar-crm-connector'));
        exit;
    }
}
