<?php
/**
 * Plugin Name: Rahbar CRM Connector
 * Description: ارسال محصولات و سفارش‌های ووکامرس به Rahbar CRM — بدون نیاز به whitelist IP در هاست.
 * Version: 1.0.1
 * Author: Rahbar CRM
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: rahbar-crm-connector
 */

if (! defined('ABSPATH')) {
    exit;
}

define('RAHBAR_CRM_CONNECTOR_VERSION', '1.0.1');
define('RAHBAR_CRM_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('RAHBAR_CRM_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once RAHBAR_CRM_CONNECTOR_PATH.'includes/class-rahbar-crm-api.php';
require_once RAHBAR_CRM_CONNECTOR_PATH.'includes/class-rahbar-crm-sync.php';
require_once RAHBAR_CRM_CONNECTOR_PATH.'includes/class-rahbar-crm-admin.php';

final class Rahbar_Crm_Connector
{
    private static ?self $instance = null;

    public Rahbar_Crm_Api $api;

    public Rahbar_Crm_Sync $sync;

    public Rahbar_Crm_Admin $admin;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->api = new Rahbar_Crm_Api();
        $this->sync = new Rahbar_Crm_Sync($this->api);
        $this->admin = new Rahbar_Crm_Admin($this->api, $this->sync);

        add_action('plugins_loaded', [$this, 'boot']);
        add_filter('cron_schedules', [$this, 'cron_schedules']);
    }

    public function cron_schedules(array $schedules): array
    {
        $schedules['rahbar_crm_every_minute'] = [
            'interval' => 60,
            'display' => 'Every Minute (Rahbar CRM)',
        ];

        return $schedules;
    }

    public function boot(): void
    {
        if (! class_exists('WooCommerce')) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>Rahbar CRM Connector نیاز به WooCommerce دارد.</p></div>';
            });

            return;
        }

        $this->admin->register();
        $this->sync->register_hooks();

        if (! wp_next_scheduled('rahbar_crm_poll_commands')) {
            wp_schedule_event(time(), 'rahbar_crm_every_minute', 'rahbar_crm_poll_commands');
        }
    }
}

Rahbar_Crm_Connector::instance();
