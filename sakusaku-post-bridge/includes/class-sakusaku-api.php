<?php
class Sakusaku_Api {

    private Sakusaku_Post_Handler $post_handler;
    private Sakusaku_Media_Handler $media_handler;
    private Sakusaku_Category_Sync $category_sync;

    public function __construct() {
        $this->post_handler  = new Sakusaku_Post_Handler();
        $this->media_handler = new Sakusaku_Media_Handler();
        $this->category_sync = new Sakusaku_Category_Sync();
    }

    public function register_routes(): void {
        $namespace = 'sakusaku/v1';
        $auth = [Sakusaku_Auth::class, 'verify_request'];

        // Posts
        register_rest_route($namespace, '/posts', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_post'],
            'permission_callback' => $auth,
        ]);

        register_rest_route($namespace, '/posts/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_post'],
            'permission_callback' => $auth,
        ]);

        register_rest_route($namespace, '/posts/(?P<id>\d+)/publish', [
            'methods'             => 'POST',
            'callback'            => [$this, 'publish_post'],
            'permission_callback' => $auth,
        ]);

        register_rest_route($namespace, '/posts/(?P<id>\d+)/unpublish', [
            'methods'             => 'POST',
            'callback'            => [$this, 'unpublish_post'],
            'permission_callback' => $auth,
        ]);

        register_rest_route($namespace, '/posts/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_post'],
            'permission_callback' => $auth,
        ]);

        // Media
        register_rest_route($namespace, '/media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upload_media'],
            'permission_callback' => $auth,
        ]);

        register_rest_route($namespace, '/posts/(?P<id>\d+)/thumbnail', [
            'methods'             => 'POST',
            'callback'            => [$this, 'set_thumbnail'],
            'permission_callback' => $auth,
        ]);

        // Categories
        register_rest_route($namespace, '/categories', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_categories'],
            'permission_callback' => $auth,
        ]);

        register_rest_route($namespace, '/categories', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_category'],
            'permission_callback' => $auth,
        ]);

        // Tags
        register_rest_route($namespace, '/tags', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_tag'],
            'permission_callback' => $auth,
        ]);

        // Health
        register_rest_route($namespace, '/ping', [
            'methods'             => 'GET',
            'callback'            => [$this, 'ping'],
            'permission_callback' => $auth,
        ]);
    }

    // --- Post endpoints ---

    public function create_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params();
        $post_id = $this->post_handler->create_draft($data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return new WP_REST_Response([
            'wp_post_id'  => $post_id,
            'preview_url' => $this->post_handler->get_preview_url($post_id),
        ], 201);
    }

    public function update_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $result = $this->post_handler->update_post($post_id, $data);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function publish_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param('id');
        $result = $this->post_handler->publish_post($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success'   => true,
            'permalink' => get_permalink($post_id),
        ], 200);
    }

    public function unpublish_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param('id');
        $result = $this->post_handler->unpublish_post($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function delete_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param('id');
        $result = $this->post_handler->delete_post($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    // --- Media endpoints ---

    public function upload_media(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params();
        $attach_id = $this->media_handler->upload_image($data);

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        $meta = $this->media_handler->get_attachment_metadata($attach_id);

        return new WP_REST_Response([
            'attachment_id' => $attach_id,
            'url'           => $this->media_handler->get_attachment_url($attach_id),
            'width'         => $meta['width'] ?? null,
            'height'        => $meta['height'] ?? null,
        ], 201);
    }

    public function set_thumbnail(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $attachment_id = (int) ($data['attachment_id'] ?? 0);

        if (!$attachment_id) {
            return new WP_Error('missing_attachment', 'attachment_id is required', ['status' => 400]);
        }

        $result = $this->media_handler->set_featured_image($post_id, $attachment_id);

        return new WP_REST_Response(['success' => $result], $result ? 200 : 500);
    }

    // --- Category endpoints ---

    public function get_categories(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response($this->category_sync->get_categories(), 200);
    }

    public function create_category(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params();
        $name = $data['name'] ?? '';
        $parent = (int) ($data['parent'] ?? 0);

        if (empty($name)) {
            return new WP_Error('missing_name', 'Category name is required', ['status' => 400]);
        }

        $result = $this->category_sync->create_category($name, $parent);

        if (is_wp_error($result)) {
            return $result;
        }

        $termId = is_array($result) ? ($result['term_id'] ?? null) : $result;

        return new WP_REST_Response(['id' => $termId, 'term_id' => $termId], 201);
    }

    // --- Tag endpoint ---

    public function create_tag(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params();
        $name = $data['name'] ?? '';
        $post_id = (int) ($data['post_id'] ?? 0);

        if (empty($name)) {
            return new WP_Error('missing_name', 'Tag name is required', ['status' => 400]);
        }

        $result = $this->category_sync->create_tag($name);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($post_id && !empty($result['term_id'])) {
            $this->category_sync->set_post_tags($post_id, [$name]);
        }

        return new WP_REST_Response(['tag_id' => $result['term_id']], 201);
    }

    // --- Health ---

    public function ping(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'status'  => 'ok',
            'version' => SAKUSAKU_VERSION,
            'wp'      => get_bloginfo('version'),
            'site'    => get_site_url(),
        ], 200);
    }
}
