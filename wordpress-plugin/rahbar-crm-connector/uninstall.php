<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('rahbar_crm_crm_url');
delete_option('rahbar_crm_bridge_token');
delete_option('rahbar_crm_bridge_secret');
delete_option('rahbar_crm_order_from_date');
