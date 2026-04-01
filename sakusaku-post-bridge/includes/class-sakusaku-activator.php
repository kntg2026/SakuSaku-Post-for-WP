<?php
class Sakusaku_Activator {

    public static function activate(): void {
        // Initialize default options if not set
        if (!get_option('sakusaku_api_key')) {
            add_option('sakusaku_api_key', '');
        }
        if (!get_option('sakusaku_service_url')) {
            add_option('sakusaku_service_url', '');
        }
    }
}
