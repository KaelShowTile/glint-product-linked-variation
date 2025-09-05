<?php
class Glint_Linked_Variation_DB {
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . GLINT_LINKED_VAR_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            variation_title varchar(255) NOT NULL,
            product_ids text NOT NULL,
            linked_attributes text NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}