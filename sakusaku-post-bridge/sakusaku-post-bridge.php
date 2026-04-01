<?php
/**
 * Plugin Name: SakuSaku Post Bridge
 * Plugin URI: https://github.com/kntg2026/SakuSaku-Post-for-WP
 * Description: Bridge between SakuSaku Post SaaS service and WordPress. Receives commands from the external service to create, update, and publish posts.
 * Version: 1.0.0
 * Author: Company Transgate
 * Author URI: https://transgate.co
 * License: GPL-2.0+
 * Text Domain: sakusaku-post-bridge
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SAKUSAKU_VERSION', '1.0.0');
define('SAKUSAKU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAKUSAKU_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes
require_once SAKUSAKU_PLUGIN_DIR . 'includes/class-sakusaku-auth.php';
require_once SAKUSAKU_PLUGIN_DIR . 'includes/class-sakusaku-post-handler.php';
require_once SAKUSAKU_PLUGIN_DIR . 'includes/class-sakusaku-media-handler.php';
require_once SAKUSAKU_PLUGIN_DIR . 'includes/class-sakusaku-category-sync.php';
require_once SAKUSAKU_PLUGIN_DIR . 'includes/class-sakusaku-api.php';
require_once SAKUSAKU_PLUGIN_DIR . 'includes/class-sakusaku-activator.php';

// Admin settings page
if (is_admin()) {
    require_once SAKUSAKU_PLUGIN_DIR . 'admin/class-sakusaku-admin-page.php';
    new Sakusaku_Admin_Page();
}

// Register REST API routes
add_action('rest_api_init', function () {
    $api = new Sakusaku_Api();
    $api->register_routes();
});

// Activation hook
register_activation_hook(__FILE__, ['Sakusaku_Activator', 'activate']);
