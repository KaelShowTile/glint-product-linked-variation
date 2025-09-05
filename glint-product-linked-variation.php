<?php
/*
Plugin Name: CHT Product Linked Variation
Description: Link simple WooCommerce products to display as variable products
Version: 1.0.0
Author: Kael
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('GLINT_LINKED_VAR_DIR', plugin_dir_path(__FILE__));
define('GLINT_LINKED_VAR_URL', plugin_dir_url(__FILE__));
define('GLINT_LINKED_VAR_TABLE', 'glint_linked_variation_product');

// Database table setup
register_activation_hook(__FILE__, function() {
    require_once GLINT_LINKED_VAR_DIR . 'includes/class-db.php';
    Glint_Linked_Variation_DB::create_table();
});

// Load classes
add_action('plugins_loaded', function() {
    if (is_admin()) {
        require_once GLINT_LINKED_VAR_DIR . 'includes/class-admin.php';
        require_once GLINT_LINKED_VAR_DIR . 'includes/class-ajax.php';
        new Glint_Linked_Variation_Admin();
        new Glint_Linked_Variation_Ajax();
    }

    require_once GLINT_LINKED_VAR_DIR . 'includes/class-frontend.php';
    new Glint_Linked_Variation_Frontend();
});

// Define global function
if (!function_exists('display_linked_product')) {
    function display_linked_product($product_id = 0) {
        static $frontend = null;
        
        if (null === $frontend) {
            $frontend = new Glint_Linked_Variation_Frontend();
        }
        
        return $frontend->display_linked_product($product_id);
    }
}