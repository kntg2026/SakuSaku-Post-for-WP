<?php
class Sakusaku_Post_Handler {

    public function create_draft(array $data): int|WP_Error {
        $post_data = [
            'post_title'   => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status'  => 'draft',
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_type'    => 'post',
        ];

        if (!empty($data['categories'])) {
            $post_data['post_category'] = array_map('intval', (array) $data['categories']);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }

        return $post_id;
    }

    public function update_post(int $post_id, array $data): bool|WP_Error {
        $post_data = ['ID' => $post_id];

        if (isset($data['title'])) {
            $post_data['post_title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['content'])) {
            $post_data['post_content'] = wp_kses_post($data['content']);
        }
        if (isset($data['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($data['excerpt']);
        }
        if (isset($data['categories'])) {
            $post_data['post_category'] = array_map('intval', (array) $data['categories']);
        }

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    public function publish_post(int $post_id): bool|WP_Error {
        $result = wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'publish',
        ], true);

        return is_wp_error($result) ? $result : true;
    }

    public function unpublish_post(int $post_id): bool|WP_Error {
        $result = wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'draft',
        ], true);

        return is_wp_error($result) ? $result : true;
    }

    public function delete_post(int $post_id): bool|WP_Error {
        $result = wp_trash_post($post_id);
        return $result ? true : new WP_Error('delete_failed', 'Failed to trash post');
    }

    public function get_preview_url(int $post_id): string {
        return get_preview_post_link($post_id) ?: '';
    }
}
