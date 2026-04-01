<?php
class Sakusaku_Media_Handler {

    public function upload_image(array $file_data): int|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $filename = sanitize_file_name($file_data['filename'] ?? 'image.jpg');
        $bits     = base64_decode($file_data['data'] ?? '');

        if (empty($bits)) {
            return new WP_Error('empty_file', 'No image data provided');
        }

        $upload = wp_upload_bits($filename, null, $bits);

        if (!empty($upload['error'])) {
            return new WP_Error('upload_failed', $upload['error']);
        }

        $filetype = wp_check_filetype($upload['file']);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => pathinfo($filename, PATHINFO_FILENAME),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $file_data['post_id'] ?? 0);

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $metadata);

        if (!empty($file_data['alt_text'])) {
            update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($file_data['alt_text']));
        }

        return $attach_id;
    }

    public function set_featured_image(int $post_id, int $attachment_id): bool {
        return set_post_thumbnail($post_id, $attachment_id);
    }

    public function get_attachment_url(int $attachment_id): string {
        return wp_get_attachment_url($attachment_id) ?: '';
    }

    public function get_attachment_metadata(int $attachment_id): array {
        $meta = wp_get_attachment_metadata($attachment_id);
        return $meta ?: [];
    }
}
