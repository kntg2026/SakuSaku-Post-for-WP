<?php
class Sakusaku_Category_Sync {

    public function get_categories(): array {
        $categories = get_categories(['hide_empty' => false]);
        $result = [];

        foreach ($categories as $cat) {
            $result[] = [
                'id'     => $cat->term_id,
                'name'   => $cat->name,
                'slug'   => $cat->slug,
                'parent' => $cat->parent,
                'count'  => $cat->count,
            ];
        }

        return $result;
    }

    public function create_category(string $name, int $parent = 0): array|WP_Error {
        $args = [];
        if ($parent > 0) {
            $args['parent'] = $parent;
        }

        $result = wp_insert_term(sanitize_text_field($name), 'category', $args);

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'term_exists') {
                return ['term_id' => $result->get_error_data()];
            }
            return $result;
        }

        return $result;
    }

    public function create_tag(string $name): array|WP_Error {
        $result = wp_insert_term(sanitize_text_field($name), 'post_tag');

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'term_exists') {
                return ['term_id' => $result->get_error_data()];
            }
            return $result;
        }

        return $result;
    }

    public function set_post_tags(int $post_id, array $tags): array|false|WP_Error {
        return wp_set_post_tags($post_id, $tags, false);
    }
}
