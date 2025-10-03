<?php
class Glint_Linked_Variation_Frontend {
    public function __construct() {
        add_shortcode('gto_linked_product', [$this, 'shortcode_handler']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    public function shortcode_handler($atts) {
        $atts = shortcode_atts(['product_id' => 0], $atts);
        $product_id = absint($atts['product_id']);
        
        return $this->display_linked_product($product_id);
    }

    public function display_linked_product($product_id = 0) {
        if (!$product_id) {
            global $product;
            $product_id = $product ? $product->get_id() : 0;
        }
        
        // Get the linked record ID for this product
        $record_id = get_post_meta($product_id, 'glint_linked_record_id', true);
        if (!$record_id) return '';

        global $wpdb;
        $table_name = $wpdb->prefix . GLINT_LINKED_VAR_TABLE;
        
        // Get the linked variation record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d", $record_id
        ));
        
        if (!$record) return '';
        
        $linked_product_ids = maybe_unserialize($record->product_ids);
        $linked_attributes = maybe_unserialize($record->linked_attributes);
        
        // Get current product data
        $current_product = wc_get_product($product_id);
        $current_attributes = $this->get_product_attributes($current_product, $linked_attributes);
        
        // Get all products in the set
        $products_data = [];
        foreach ($linked_product_ids as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;
            
            $products_data[$id] = [
                'name' => $product->get_name(),
                'permalink' => $product->get_permalink(),
                'thumbnail' => $product->get_image('woocommerce_thumbnail'),
                'attributes' => $this->get_product_attributes($product, $linked_attributes)
            ];
        }
        
        // Get all possible attribute values across products
        $attribute_values = [];
        foreach ($linked_attributes as $attribute) {
            $attribute_values[$attribute] = [];
            
            foreach ($products_data as $data) {
                $value = $data['attributes'][$attribute] ?? '';
                if ($value && !in_array($value, $attribute_values[$attribute])) {
                    $attribute_values[$attribute][] = $value;
                }
            }
        }

        // Pre-cache color swatches for all color values
        $color_swatches = [];
        if (in_array('colour', $linked_attributes)) {
            foreach ($attribute_values['colour'] as $color_value) {
                // Find the first product that has this color value
                foreach ($products_data as $p_id => $product_data) {
                    if ($product_data['attributes']['colour'] === $color_value) {
                        $color_swatches[$color_value] = $product_data['thumbnail'];
                        break;
                    }
                }
            }
        }
        
        ob_start();
        ?>
        <div class="glint-linked-variations">
            
            <?php foreach ($linked_attributes as $attribute) : 
                $taxonomy = get_taxonomy('pa_' . $attribute);
                $attribute_label = $taxonomy ? $taxonomy->labels->singular_name : $attribute;
            ?>
                <div class="glint-attribute glint-attribute-<?php echo esc_attr($attribute); ?>">
                    <label><?php echo esc_html($attribute_label); ?>:</label>
                    <ul class="glint-variation-options">
                        <?php foreach ($attribute_values[$attribute] as $value) : 
                            $is_current = ($current_attributes[$attribute] === $value);
                            $is_selectable = $this->is_option_selectable(
                                $current_attributes, 
                                $attribute, 
                                $value, 
                                $products_data
                            );
                            
                            // Get the correct thumbnail for color attribute
                            $thumbnail = '';
                            if ($attribute === 'colour' || $attribute === 'colours' ) {
                                if ($is_current) {
                                    $thumbnail = $products_data[$product_id]['thumbnail'];
                                } elseif ($is_selectable) {
                                    $linked_product_id = $this->find_matching_product(
                                        $current_attributes, 
                                        $attribute, 
                                        $value, 
                                        $products_data
                                    );
                                    $thumbnail = $products_data[$linked_product_id]['thumbnail'];
                                } else {
                                    $thumbnail = $color_swatches[$value] ?? '';
                                }
                            }
                        ?>
                            <li class="glint-option<?php echo $is_current ? ' glint-option-active' : ''; ?><?php echo !$is_selectable ? ' glint-option-disabled' : ''; ?>">
                                <?php if ($is_current) : ?>
                                    <?php if (($attribute === 'colour' || $attribute === 'colours') && $thumbnail) : ?>
                                        <?php echo $thumbnail; ?>
                                    <?php endif; ?>
                                    <span class="glint-option-value"><?php echo esc_html($value); ?></span>
                                <?php elseif ($is_selectable) : 
                                    $linked_product_id = $this->find_matching_product(
                                        $current_attributes, 
                                        $attribute, 
                                        $value, 
                                        $products_data
                                    );
                                ?>
                                    <a href="<?php echo esc_url($products_data[$linked_product_id]['permalink']); ?>" class="glint-option-value" title="<?php echo esc_attr($products_data[$linked_product_id]['name']); ?>">
                                        <?php if (($attribute === 'colour' || $attribute === 'colours') && $thumbnail) : ?>
                                            <?php echo $thumbnail; ?>
                                        <?php endif; ?>
                                        <span class="glint-option-value"><?php echo esc_html($value); ?></span>
                                    </a>
                                <?php else : ?>
                                    <?php if (($attribute === 'colour' || $attribute === 'colours') && $thumbnail) : ?>
                                        <?php echo $thumbnail; ?>
                                    <?php endif; ?>
                                    <span class="glint-option-value"><?php echo esc_html($value); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_product_attributes($product, $linked_attributes) {
        $attributes = [];
        
        foreach ($linked_attributes as $attribute) {
            $taxonomy = 'pa_' . $attribute;
            $terms = wp_get_post_terms($product->get_id(), $taxonomy, ['fields' => 'names']);
            $attributes[$attribute] = !empty($terms) ? $terms[0] : '';
        }
        
        return $attributes;
    }
    
    private function is_option_selectable($current_attributes, $attribute, $value, $products_data) {
        // Temporarily change the current attribute to the test value
        $test_attributes = $current_attributes;
        $test_attributes[$attribute] = $value;
        
        // Check if any product matches this combination
        foreach ($products_data as $data) {
            $match = true;
            
            foreach ($test_attributes as $attr => $val) {
                if ($data['attributes'][$attr] !== $val) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) return true;
        }
        
        return false;
    }
    
    private function find_matching_product($current_attributes, $attribute, $value, $products_data) {
        // Temporarily change the current attribute to the test value
        $test_attributes = $current_attributes;
        $test_attributes[$attribute] = $value;
        
        // Find the matching product
        foreach ($products_data as $id => $data) {
            $match = true;
            
            foreach ($test_attributes as $attr => $val) {
                if ($data['attributes'][$attr] !== $val) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) return $id;
        }
        
        return 0;
    }
    
    public function enqueue_styles() {
        wp_enqueue_style(
            'glint-linked-frontend-css',
            GLINT_LINKED_VAR_URL . 'assets/css/frontend.css'
        );
    }
}