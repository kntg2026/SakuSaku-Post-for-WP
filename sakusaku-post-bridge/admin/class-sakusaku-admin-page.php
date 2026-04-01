<?php
class Sakusaku_Admin_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page(): void {
        add_options_page(
            'SakuSaku Post',
            'SakuSaku Post',
            'manage_options',
            'sakusaku-post',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting('sakusaku_settings', 'sakusaku_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('sakusaku_settings', 'sakusaku_service_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ]);
    }

    public function render_page(): void {
        require_once SAKUSAKU_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}
