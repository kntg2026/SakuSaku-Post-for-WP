<?php
class Sakusaku_Auth {
    public static function verify_request(WP_REST_Request $request): bool|WP_Error {
        $key = $request->get_header('X-Sakusaku-Api-Key');
        $stored = get_option('sakusaku_api_key', '');

        if (empty($stored)) {
            return new WP_Error('not_configured', 'API key not configured', ['status' => 500]);
        }

        if (empty($key) || !hash_equals($stored, $key)) {
            return new WP_Error('unauthorized', 'Invalid API key', ['status' => 401]);
        }

        return true;
    }
}
