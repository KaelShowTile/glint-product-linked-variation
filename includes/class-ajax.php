<?php
class Glint_Linked_Variation_Ajax {
    public function __construct() {
        add_action('wp_ajax_glint_linked_variation_search_products', [$this, 'search_products']);
    }

    public function search_products() {
        error_log('ajax handle reached...');

        check_ajax_referer('glint-linked-nonce', 'nonce');

        error_log('Nonce check passed...');

        $search = sanitize_text_field($_GET['q']);
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $search,
            'posts_per_page' => 10,
            'fields' => 'ids'
        ];

        $product_ids = get_posts($args);
        $results = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $results[] = [
                    'id' => $product_id,
                    'text' => $product->get_name()
                ];
            }
        }

        wp_send_json(['results' => $results]);
    }
}