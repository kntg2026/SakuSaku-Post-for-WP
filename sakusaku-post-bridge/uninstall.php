<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('sakusaku_api_key');
delete_option('sakusaku_service_url');
