<?php
/**
 * Enhanced XML Generator Class
 * Fixed to prioritize uploads directory and ensure correct URL generation
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/file.php';

class Varle_Export_XML_Generator {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('varle_export_settings', array());
    }

    /**
     * Log helper using WooCommerce logger when available.
     *
     * @param string $level   Log level.
     * @param string $message Message text.
     */
    private function log($level, $message) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, array('source' => 'varle-export'));
        }
    }

    /**
     * Retrieve WordPress filesystem abstraction instance.
     *
     * @return WP_Filesystem_Base|false
     */
    private function get_filesystem() {
        global $wp_filesystem;

        if (!isset($wp_filesystem) || ! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        return $wp_filesystem;
    }

    /**
     * Write file contents via WP_Filesystem.
     *
     * @param string $path     Absolute file path.
     * @param string $contents File contents.
     * @return bool
     */
    private function write_file($path, $contents) {
        $filesystem = $this->get_filesystem();

        if (!$filesystem) {
            return false;
        }

        $chmod = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;

        return (bool) $filesystem->put_contents($path, $contents, $chmod);
    }

    /**
     * Ensure directory permissions allow writing.
     *
     * @param string $directory Directory path.
     * @return bool
     */
    private function ensure_directory_writable($directory) {
        if (wp_is_writable($directory)) {
            return true;
        }

        $filesystem = $this->get_filesystem();
        if (!$filesystem) {
            return false;
        }

        $dir_chmod = defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755;

        if ($filesystem->chmod($directory, $dir_chmod)) {
            return wp_is_writable($directory);
        }

        return false;
    }

    /**
     * Apply secure permissions to generated files.
     *
     * @param string $path File path.
     * @return void
     */
    private function set_file_permissions($path) {
        $filesystem = $this->get_filesystem();

        if (!$filesystem) {
            return;
        }

        $chmod = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
        $filesystem->chmod($path, $chmod);
    }
    
    /**
     * Generate and save XML file with enhanced uploads directory priority
     */
    public function generate_xml_file() {
        try {
            $xml_content = $this->generate_xml_content();
            
            // Check if forced storage method is set
            if (defined('VARLE_FORCE_STORAGE_METHOD')) {
                return $this->use_forced_storage_method($xml_content);
            }
            
            // ENHANCED: Prioritize uploads directory with better error handling
            $methods = array(
                'uploads_directory',    // First choice - uploads/varle-export/
                'database_storage'      // Fallback - database storage
            );
            
            foreach ($methods as $method) {
                $result = $this->try_storage_method($method, $xml_content);
                if ($result) {
                    $this->update_success_options($result, $method);
                    return true;
                }
            }
            
            throw new Exception('All storage methods failed');
            
        } catch (Exception $e) {
            $this->log('error', 'Varle XML generation failed: ' . $e->getMessage());
            update_option('varle_export_last_error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Use forced storage method
     */
    private function use_forced_storage_method($xml_content) {
        $method = VARLE_FORCE_STORAGE_METHOD;
        $method_key = sanitize_key($method);
        $result = $this->try_storage_method($method_key, $xml_content);
        
        if ($result) {
            $this->update_success_options($result, $method_key);
            return true;
        }
        
        throw new Exception(
            sprintf(
                'Forced storage method failed: %s',
                $method_key
            )
        );
    }
    
    /**
     * Try specific storage method
     */
    private function try_storage_method($method, $xml_content) {
        switch ($method) {
            case 'database_storage':
                return $this->store_in_database($xml_content);
                
            case 'uploads_directory':
                return $this->store_in_uploads($xml_content);
                
            case 'root_directory':
                return $this->store_in_root($xml_content);
                
            default:
                return false;
        }
    }
    
    /**
     * Store XML in database (reliable fallback)
     */
    private function store_in_database($xml_content) {
        try {
            // Store XML content in database without autoloading
            if (false === get_option('varle_export_xml_content', false)) {
                add_option('varle_export_xml_content', $xml_content, '', 'no');
            } else {
                update_option('varle_export_xml_content', $xml_content);
            }
            update_option('varle_export_xml_size', strlen($xml_content));
            update_option('varle_export_xml_generated', current_time('mysql'));
            
            $file_url = home_url('/wp-admin/admin-ajax.php?action=varle_serve_xml');
            
            $this->log('info', 'Varle XML stored in database successfully - Size: ' . strlen($xml_content) . ' bytes');
            
            return array(
                'path' => 'database',
                'url' => $file_url,
                'size' => strlen($xml_content)
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Database storage failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store XML in uploads directory - ENHANCED
     */
    private function store_in_uploads($xml_content) {
        try {
            $upload_dir = wp_upload_dir();
            
            if ($upload_dir['error']) {
                throw new Exception('Uploads directory error: ' . $upload_dir['error']);
            }
            
            $filename = $this->get_xml_filename();
            
            // ENHANCED: Always use varle-export subdirectory for clean organization
            $varle_dir = $upload_dir['basedir'] . '/varle-export';
            $varle_url = $upload_dir['baseurl'] . '/varle-export';
            
            $file_path = $varle_dir . '/' . $filename;
            $file_url = $varle_url . '/' . $filename;
            
            // Create directory if it doesn't exist
            if (!file_exists($varle_dir)) {
                $mkdir_result = wp_mkdir_p($varle_dir);
                if (!$mkdir_result) {
                    throw new Exception('Could not create varle-export directory: ' . $varle_dir);
                }
                $this->log('info', 'Varle XML: Created directory ' . $varle_dir);
            }
            
            // Enhanced permission handling
            if (!$this->ensure_directory_writable($varle_dir)) {
                throw new Exception('Varle directory not writable: ' . $varle_dir);
            }
            $this->log('info', 'Varle XML: Ensured writable permissions for ' . $varle_dir);
            
            // Write the file
            if (!$this->write_file($file_path, $xml_content)) {
                throw new Exception('Failed to write file to varle-export directory: ' . $file_path);
            }
            
            // Verify file was written correctly
            if (!file_exists($file_path)) {
                throw new Exception('File was not created: ' . $file_path);
            }
            
            $actual_size = wp_filesize($file_path);
            if (false === $actual_size) {
                $actual_size = strlen($xml_content);
            }
            if ($actual_size !== strlen($xml_content)) {
                $this->log('warning', 'Varle XML: Size mismatch - expected ' . strlen($xml_content) . ', got ' . $actual_size);
            }
            
            $this->set_file_permissions($file_path);
            
            $this->log('info', 'Varle XML stored in varle-export directory successfully: ' . $file_path . ' (' . $actual_size . ' bytes)');
            
            return array(
                'path' => $file_path,
                'url' => $file_url,
                'size' => $actual_size
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Varle-export storage failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store XML in root directory (fallback)
     */
    private function store_in_root($xml_content) {
        try {
            $filename = $this->get_xml_filename();
            $file_path = ABSPATH . $filename;
            $file_url = home_url('/' . $filename);
            
            if (!wp_is_writable(ABSPATH)) {
                throw new Exception('Root directory not writable: ' . ABSPATH);
            }
            
            if (!$this->write_file($file_path, $xml_content)) {
                throw new Exception('Failed to write file to root directory');
            }
            
            $this->set_file_permissions($file_path);
            
            $written_size = wp_filesize($file_path);
            if (false === $written_size) {
                $written_size = strlen($xml_content);
            }
            $this->log('info', 'Varle XML stored in root directory: ' . $file_path . ' (' . $written_size . ' bytes)');
            
            return array(
                'path' => $file_path,
                'url' => $file_url,
                'size' => $written_size
            );
            
        } catch (Exception $e) {
            $this->log('error', 'Root storage failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update success options - ENHANCED
     */
    private function update_success_options($result, $method) {
        update_option('varle_export_last_generated', current_time('mysql'));
        update_option('varle_export_file_url', $result['url']);
        update_option('varle_export_file_path', isset($result['path']) ? $result['path'] : '');
        update_option('varle_export_storage_method', $method);
        update_option('varle_export_file_size', $result['size']);
        
        // Clear any previous errors
        delete_option('varle_export_last_error');
        
        $this->log('info', 'Varle XML generation successful using ' . $method . ' - URL: ' . $result['url'] . ' - Size: ' . $result['size'] . ' bytes');
    }
    
    /**
     * Get XML filename
     */
    private function get_xml_filename() {
        return isset($this->settings['xml_file_name']) ? $this->settings['xml_file_name'] : 'products.xml';
    }
    
    /**
     * Generate XML content (enhanced with better error handling)
     */
    public function generate_xml_content() {
        try {
            $xml = new DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;
            
            // Create root element
            $root = $xml->createElement('root');
            $xml->appendChild($root);
            
            // Create products container
            $products = $xml->createElement('products');
            $root->appendChild($products);
            
            // Add products
            $product_count = $this->add_products_to_xml($xml, $products);
            
            $xml_content = $xml->saveXML();
            
            if (empty($xml_content)) {
                throw new Exception('Generated XML content is empty');
            }
            
            $this->log('info', 'Varle XML content generated successfully - ' . $product_count . ' products, ' . strlen($xml_content) . ' bytes');
            
            return $xml_content;
            
        } catch (Exception $e) {
            $this->log('error', 'XML content generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Add products to XML - enhanced with count return
     */
    private function add_products_to_xml($xml, $products_element) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock'
                )
            )
        );
        
        $product_query = new WP_Query($args);
        $product_count = 0;
        
        if ($product_query->have_posts()) {
            while ($product_query->have_posts()) {
                $product_query->the_post();
                
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                
                if (!$product || !$this->should_include_product($product)) {
                    continue;
                }
                
                $this->add_single_product($xml, $products_element, $product);
                $product_count++;
            }
        }
        
        wp_reset_postdata();
        
        return $product_count;
    }
    
    /**
     * Check if product should be included
     */
    private function should_include_product($product) {
        if (!$product->is_in_stock()) {
            return false;
        }
        
        if (!$product->get_price() || $product->get_price() <= 0) {
            return false;
        }
        
        $exclude = get_post_meta($product->get_id(), '_varle_exclude', true);
        if ($exclude === 'yes') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Add single product to XML
     */
    private function add_single_product($xml, $products_element, $product) {
        $product_element = $xml->createElement('product');
        $products_element->appendChild($product_element);
        
        // Required fields
        $this->add_element($xml, $product_element, 'id', $product->get_id());
        $this->add_categories($xml, $product_element, $product);
        $this->add_cdata_element($xml, $product_element, 'title', $product->get_name());
        
        // Description
        $description = $product->get_description();
        if (empty($description)) {
            $description = $product->get_short_description();
        }
        if (empty($description)) {
            $description = $product->get_name();
        }
        $this->add_cdata_element($xml, $product_element, 'description', $description);
        
        // Price
        $this->add_element($xml, $product_element, 'price', number_format($product->get_price(), 2, '.', ''));
        
        // Delivery text
        $delivery_text = get_post_meta($product->get_id(), '_varle_delivery_text', true);
        if (empty($delivery_text)) {
            $delivery_text = isset($this->settings['delivery_text']) ? $this->settings['delivery_text'] : '2-3 d. d.';
        }
        $this->add_element($xml, $product_element, 'delivery_text', $delivery_text);
        
        // Images
        $this->add_images($xml, $product_element, $product);
        
        // Handle variants or simple quantity
        if ($product->is_type('variable')) {
            $this->add_variants($xml, $product_element, $product);
        } else {
            $stock_qty = $product->get_stock_quantity();
            $this->add_element($xml, $product_element, 'quantity', $stock_qty !== null ? $stock_qty : 0);
            
            $sku = $product->get_sku();
            if ($sku) {
                $this->add_element($xml, $product_element, 'barcode', $sku);
            }
        }
        
        // Barcode format
        $this->add_element($xml, $product_element, 'barcode_format', 'EAN');
        
        // Optional fields
        $this->add_optional_fields($xml, $product_element, $product);
    }
    
    /**
     * Add categories
     */
    private function add_categories($xml, $product_element, $product) {
        $categories_element = $xml->createElement('categories');
        $product_element->appendChild($categories_element);
        
        $product_categories = get_the_terms($product->get_id(), 'product_cat');
        if ($product_categories && !is_wp_error($product_categories)) {
            foreach ($product_categories as $category) {
                $category_path = $this->get_category_path($category);
                $this->add_cdata_element($xml, $categories_element, 'category', $category_path);
            }
        } else {
            $this->add_cdata_element($xml, $categories_element, 'category', 'Prekės');
        }
    }
    
    /**
     * Get category path
     */
    private function get_category_path($category) {
        $path = array();
        $current = $category;
        
        while ($current && !is_wp_error($current)) {
            array_unshift($path, $current->name);
            if ($current->parent) {
                $current = get_term($current->parent, 'product_cat');
            } else {
                break;
            }
        }
        
        return implode(' -> ', $path);
    }
    
    /**
     * Add images
     */
    private function add_images($xml, $product_element, $product) {
        $images_element = $xml->createElement('images');
        $product_element->appendChild($images_element);
        
        $has_images = false;
        
        // Main image
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $this->add_cdata_element($xml, $images_element, 'image', $image_url);
                $has_images = true;
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $gallery_url = wp_get_attachment_image_url($gallery_id, 'full');
            if ($gallery_url) {
                $this->add_cdata_element($xml, $images_element, 'image', $gallery_url);
                $has_images = true;
            }
        }
        
        // Add placeholder if no images
        if (!$has_images) {
            $placeholder_url = wc_placeholder_img_src('full');
            $this->add_cdata_element($xml, $images_element, 'image', $placeholder_url);
        }
    }
    
    /**
     * Add variants for variable products
     */
    private function add_variants($xml, $product_element, $product) {
        $variants_element = $xml->createElement('variants');
        $product_element->appendChild($variants_element);
        
        $variations = $product->get_available_variations();
        
        foreach ($variations as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            
            if (!$variation || !$variation->is_in_stock()) {
                continue;
            }
            
            foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
                $variant_element = $xml->createElement('variant');
                
                $attr_label = str_replace('attribute_', '', $attr_name);
                $attr_label = str_replace('pa_', '', $attr_label);
                $attr_label = ucfirst(str_replace('-', ' ', $attr_label));
                
                $variant_element->setAttribute('group_title', $attr_label);
                
                $this->add_element($xml, $variant_element, 'title', $attr_value);
                
                $stock_qty = $variation->get_stock_quantity();
                $this->add_element($xml, $variant_element, 'quantity', $stock_qty !== null ? $stock_qty : 0);
                
                $this->add_element($xml, $variant_element, 'barcode', $variation->get_sku() ?: '');
                
                $price_diff = $variation->get_price() - $product->get_price();
                $this->add_element($xml, $variant_element, 'price', number_format($price_diff, 2, '.', ''));
                
                $variants_element->appendChild($variant_element);
            }
        }
    }
    
    /**
     * Add optional fields
     */
    private function add_optional_fields($xml, $product_element, $product) {
        // Model (SKU)
        $sku = $product->get_sku();
        if ($sku) {
            $this->add_cdata_element($xml, $product_element, 'model', $sku);
        }
        
        // Weight
        if ($product->get_weight()) {
            $this->add_element($xml, $product_element, 'weight', $product->get_weight());
        }
        
        // Manufacturer
        $manufacturer = $this->get_manufacturer($product);
        if ($manufacturer) {
            $this->add_cdata_element($xml, $product_element, 'manufacturer', $manufacturer);
        }
        
        // Attributes
        $this->add_attributes($xml, $product_element, $product);
        
        // Group
        $group = get_post_meta($product->get_id(), '_varle_group', true);
        if (empty($group)) {
            $group = isset($this->settings['default_group']) ? $this->settings['default_group'] : '0001';
        }
        $this->add_cdata_element($xml, $product_element, 'group', $group);
        
        // Sale price
        if ($product->is_on_sale() && $product->get_regular_price()) {
            $this->add_element($xml, $product_element, 'price_old', number_format($product->get_regular_price(), 2, '.', ''));
        }
        
        // URL
        $this->add_cdata_element($xml, $product_element, 'url', get_permalink($product->get_id()));
        
        // Warranty
        $warranty = get_post_meta($product->get_id(), '_varle_warranty', true);
        if ($warranty) {
            $this->add_element($xml, $product_element, 'warranty', $warranty);
        }
        
        // Product with gift
        $with_gift = get_post_meta($product->get_id(), '_varle_with_gift', true) === 'yes' ? 'True' : 'False';
        $this->add_element($xml, $product_element, 'product_with_gift', $with_gift);
    }
    
    /**
     * Get manufacturer
     */
    private function get_manufacturer($product) {
        $manufacturer_attr = isset($this->settings['manufacturer_attr']) ? $this->settings['manufacturer_attr'] : 'pa_brand';
        return $product->get_attribute($manufacturer_attr);
    }
    
    /**
     * Add product attributes
     */
    private function add_attributes($xml, $product_element, $product) {
        $attributes_element = $xml->createElement('attributes');
        $product_element->appendChild($attributes_element);
        
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute) {
            if ($attribute->get_visible()) {
                $attr_element = $xml->createElement('attribute');
                $attr_element->setAttribute('title', $attribute->get_name());
                $attr_value = $product->get_attribute($attribute->get_name());
                if ($attr_value) {
                    $attr_cdata = $xml->createCDATASection($attr_value);
                    $attr_element->appendChild($attr_cdata);
                    $attributes_element->appendChild($attr_element);
                }
            }
        }
    }
    
    /**
     * Helper to add simple element
     */
    private function add_element($xml, $parent, $name, $value) {
        $element = $xml->createElement($name, htmlspecialchars((string)$value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($element);
    }
    
    /**
     * Helper to add CDATA element
     */
    private function add_cdata_element($xml, $parent, $name, $value) {
        $element = $xml->createElement($name);
        $cdata = $xml->createCDATASection((string)$value);
        $element->appendChild($cdata);
        $parent->appendChild($element);
    }
}

// Add AJAX endpoint for serving XML from database
add_action('wp_ajax_varle_serve_xml', 'varle_serve_xml_from_database');
add_action('wp_ajax_nopriv_varle_serve_xml', 'varle_serve_xml_from_database');

function varle_serve_xml_from_database() {
    $xml_content = get_option('varle_export_xml_content');
    
    if (empty($xml_content)) {
        status_header(404);
        exit(esc_html__('XML file not found', 'varle-export'));
    }
    
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Length: ' . strlen($xml_content));
    header('Cache-Control: public, max-age=3600');
    
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Serving raw XML content.
    echo $xml_content;
    exit;
}
?>
