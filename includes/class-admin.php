<?php
class Glint_Linked_Variation_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_glint_save_linked_variation', [$this, 'save_linked_variation']);
        add_action('admin_post_glint_delete_linked_variation', [$this, 'delete_linked_variation']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Linked Variations',
            'Linked Variations',
            'manage_options',
            'glint-linked-variations',
            [$this, 'render_admin_page'],
            'dashicons-admin-links'
        );
    }

    public function enqueue_assets($hook) {
        if ('toplevel_page_glint-linked-variations' !== $hook) return;

        // CSS
        wp_enqueue_style(
            'glint-linked-admin-css',
            GLINT_LINKED_VAR_URL . 'assets/css/admin.css'
        );

        // JS
        wp_enqueue_script(
            'glint-linked-admin-js',
            GLINT_LINKED_VAR_URL . 'assets/js/admin.js',
            ['jquery', 'select2'],
            '1.0',
            true
        );

        // Localize script
        wp_localize_script('glint-linked-admin-js', 'glintLinkedVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('glint-linked-nonce')
        ]);

        // Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    }

    public function render_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . GLINT_LINKED_VAR_TABLE;
        $records = $wpdb->get_results("SELECT * FROM $table_name");
        $attributes = wc_get_attribute_taxonomies();
        
        // Check if editing a record
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $editing_record = null;
        $editing_product_ids = [];
        $editing_attributes = [];
        
        if ($edit_id) {
            $editing_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
            if ($editing_record) {
                $editing_product_ids = maybe_unserialize($editing_record->product_ids);
                $editing_attributes = maybe_unserialize($editing_record->linked_attributes);
            }
        }
        ?>
        <div class="wrap">
            <h1>Linked Product Variations</h1>

            <div class="plugin-explaination">
                <h4>Usage:</h4>
                <p>Shortcode: [gto_linked_product product_id="123"]</p>
                <p>PHP: <b>echo</b> display_linked_product($product_id);</p>
            </div>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="glint_save_linked_variation">
                <?php wp_nonce_field('glint_save_linked_variation', 'glint_nonce'); ?>
                
                <?php if ($edit_id): ?>
                    <input type="hidden" name="record_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                
                <h2><?php echo $edit_id ? 'Edit Linked Variation' : 'Add New Linked Variation'; ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label>Variation Title</label></th>
                        <td>
                            <input type="text" name="variation_title" class="regular-text" required 
                                value="<?php echo $editing_record ? esc_attr($editing_record->variation_title) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Linked Products</label></th>
                        <td>
                            <select name="product_ids[]" class="glint-product-search" multiple="multiple" style="width:100%;" required>
                                <?php
                                if ($editing_record && $editing_product_ids) {
                                    foreach ($editing_product_ids as $product_id) {
                                        $product = wc_get_product($product_id);
                                        if ($product) {
                                            echo '<option value="' . esc_attr($product_id) . '" selected="selected">' . esc_html($product->get_name()) . '</option>';
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Linked Attributes</label></th>
                        <td class="glp-attribute">
                            <fieldset>
                                <?php foreach ($attributes as $attribute): 
                                    $attr_name = $attribute->attribute_name;
                                    $checked = $editing_record && in_array($attr_name, $editing_attributes) ? 'checked' : '';
                                ?>
                                    <label>
                                        <input type="checkbox" name="linked_attributes[]" value="<?php echo esc_attr($attr_name); ?>" <?php echo $checked; ?>>
                                        <?php echo esc_html($attribute->attribute_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <div class="glp-buttons">
                    <?php submit_button($edit_id ? 'Update Linked Variation' : 'Save Linked Variation'); ?>
                    <?php if ($edit_id): ?>
                        <a href="<?php echo admin_url('admin.php?page=glint-linked-variations'); ?>" class="button button-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <hr>
            
            <h2>Existing Linked Variations</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;">ID</th>
                        <th style="width: 20%;">Title</th>
                        <th>Products</th>
                        <th style="width: 20%;">Attributes</th>
                        <th style="width: 5%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): 
                        $product_ids = maybe_unserialize($record->product_ids);
                        $attributes = maybe_unserialize($record->linked_attributes);
                    ?>
                        <tr>
                            <td><?php echo $record->id; ?></td>
                            <td><?php echo esc_html($record->variation_title); ?></td>
                            <td>
                                <?php 
                                foreach ($product_ids as $product_id) {
                                    $product = wc_get_product($product_id);
                                    if ($product) {
                                        if (current_user_can('edit_post', $product_id)) {
                                            $edit_url = admin_url('post.php?post=' . $product_id . '&action=edit');
                                            echo '<span class="glint-product-tag"><a href="' . $edit_url . '">' . esc_html($product->get_name()) . '</a></span>';
                                        }else{
                                            echo '<span class="glint-product-tag">' . esc_html($product->get_name()) . '</span>';
                                        }   
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                foreach ($attributes as $attr) {
                                    echo '<span class="glint-attr-tag">' . esc_html($attr) . '</span>';
                                }
                                ?>
                            </td>
                            <td class="glp-table-btns">
                                <a href="<?php echo admin_url('admin.php?page=glint-linked-variations&edit=' . $record->id); ?>" class="button button-link">Edit</a>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="glint_delete_linked_variation">
                                    <input type="hidden" name="record_id" value="<?php echo $record->id; ?>">
                                    <?php wp_nonce_field('glint_delete_linked_variation', 'glint_nonce'); ?>
                                    <button type="submit" class="button button-link glint-delete-link">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_linked_variation() {
        // Verify nonce and capabilities
        if (!isset($_POST['glint_nonce']) || 
            !wp_verify_nonce($_POST['glint_nonce'], 'glint_save_linked_variation') || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . GLINT_LINKED_VAR_TABLE;

        // Prepare data
        $title = sanitize_text_field($_POST['variation_title']);
        $product_ids = array_map('intval', $_POST['product_ids']);
        $attributes = isset($_POST['linked_attributes']) ? array_map('sanitize_text_field', $_POST['linked_attributes']) : [];
        
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;

        if ($record_id) {

            // Get previous product IDs
            $previous_product_ids = $wpdb->get_var(
                $wpdb->prepare("SELECT product_ids FROM $table_name WHERE id = %d", $record_id)
            );
            $previous_product_ids = maybe_unserialize($previous_product_ids);
            
            // Update record
            $wpdb->update($table_name, [
                'variation_title' => $title,
                'product_ids' => maybe_serialize($product_ids),
                'linked_attributes' => maybe_serialize($attributes)
            ], ['id' => $record_id]);
            
            // Update product meta
            $products_to_remove = array_diff($previous_product_ids, $product_ids);
            $products_to_add = array_diff($product_ids, $previous_product_ids);
            
            // Remove meta from products no longer in this set
            foreach ($products_to_remove as $product_id) {
                delete_post_meta($product_id, 'glint_linked_record_id');
            }
            
            // Add meta to new products in this set
            foreach ($products_to_add as $product_id) {
                update_post_meta($product_id, 'glint_linked_record_id', $record_id);
            }
        } else {
            // Create new record
            $wpdb->insert($table_name, [
                'variation_title' => $title,
                'product_ids' => maybe_serialize($product_ids),
                'linked_attributes' => maybe_serialize($attributes)
            ]);

            // Update product meta
            $record_id = $wpdb->insert_id;
            foreach ($product_ids as $product_id) {
                update_post_meta($product_id, 'glint_linked_record_id', $record_id);
            }
        }

        wp_redirect(admin_url('admin.php?page=glint-linked-variations'));
        exit;
    }

    public function delete_linked_variation() {
        // Verify nonce and capabilities
        if (!isset($_POST['glint_nonce']) || 
            !wp_verify_nonce($_POST['glint_nonce'], 'glint_delete_linked_variation') || 
            !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . GLINT_LINKED_VAR_TABLE;
        $record_id = intval($_POST['record_id']);

        // Get product IDs before deletion
        $product_ids = $wpdb->get_var(
            $wpdb->prepare("SELECT product_ids FROM $table_name WHERE id = %d", $record_id)
        );
        $product_ids = maybe_unserialize($product_ids);

        // Delete record
        $wpdb->delete($table_name, ['id' => $record_id]);

        // Remove postmeta
        foreach ($product_ids as $product_id) {
            delete_post_meta($product_id, 'glint_linked_record_id');
        }

        wp_redirect(admin_url('admin.php?page=glint-linked-variations'));
        exit;
    }
}