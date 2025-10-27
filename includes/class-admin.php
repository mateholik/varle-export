<?php
/**
 * Simple, Working Admin Interface Class
 * This version is guaranteed to have no syntax errors
 */

if (!defined('ABSPATH')) {
    exit;
}

class Varle_Export_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add product meta boxes
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
        
        // Add AJAX handlers
        add_action('wp_ajax_varle_test_accessibility', array($this, 'test_file_accessibility'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Varle.lt Export', 'varle-export'),
            __('Varle.lt Export', 'varle-export'),
            'manage_woocommerce',
            'varle-export',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('varle_export_settings', 'varle_export_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'varle_export_general',
            __('General Settings', 'varle-export'),
            array($this, 'general_section_callback'),
            'varle_export_settings'
        );
        
        add_settings_field(
            'delivery_text',
            __('Default Delivery Text', 'varle-export'),
            array($this, 'delivery_text_callback'),
            'varle_export_settings',
            'varle_export_general'
        );
        
        add_settings_field(
            'default_group',
            __('Default Group ID', 'varle-export'),
            array($this, 'default_group_callback'),
            'varle_export_settings',
            'varle_export_general'
        );
        
        add_settings_field(
            'manufacturer_attr',
            __('Manufacturer Attribute', 'varle-export'),
            array($this, 'manufacturer_attr_callback'),
            'varle_export_settings',
            'varle_export_general'
        );
        
        add_settings_field(
            'xml_file_name',
            __('XML File Name', 'varle-export'),
            array($this, 'xml_file_name_callback'),
            'varle_export_settings',
            'varle_export_general'
        );
        
        add_settings_field(
            'auto_generate',
            __('Auto Generate XML', 'varle-export'),
            array($this, 'auto_generate_callback'),
            'varle_export_settings',
            'varle_export_general'
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw settings input.
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        if (isset($input['delivery_text'])) {
            $sanitized['delivery_text'] = sanitize_text_field(wp_unslash($input['delivery_text']));
        }

        if (isset($input['default_group'])) {
            $sanitized['default_group'] = sanitize_text_field(wp_unslash($input['default_group']));
        }

        if (isset($input['manufacturer_attr'])) {
            $sanitized['manufacturer_attr'] = sanitize_key(wp_unslash($input['manufacturer_attr']));
        }

        if (isset($input['xml_file_name'])) {
            $sanitized['xml_file_name'] = sanitize_file_name(wp_unslash($input['xml_file_name']));
        }

        if (isset($input['auto_generate']) && 'no' === wp_unslash($input['auto_generate'])) {
            $sanitized['auto_generate'] = 'no';
        } else {
            $sanitized['auto_generate'] = 'yes';
        }

        if (isset($input['error_notifications']) && 'yes' === wp_unslash($input['error_notifications'])) {
            $sanitized['error_notifications'] = 'yes';
        } else {
            $sanitized['error_notifications'] = 'no';
        }

        return $sanitized;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_varle-export' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'varle-export-admin',
            VARLE_EXPORT_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            VARLE_EXPORT_VERSION,
            true
        );
        
        wp_localize_script('varle-export-admin', 'varle_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('varle_export_nonce'),
            'generating_text' => __('Regenerating XML...', 'varle-export'),
            'success_text' => __('XML regenerated successfully!', 'varle-export'),
            'error_text' => __('Error regenerating XML', 'varle-export'),
            'button_text' => __('Regenerate XML File', 'varle-export')
        ));
        
        wp_enqueue_style(
            'varle-export-admin',
            VARLE_EXPORT_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            VARLE_EXPORT_VERSION
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $settings = get_option('varle_export_settings', array());
        $last_generated = get_option('varle_export_last_generated');
        $xml_file_name = isset($settings['xml_file_name']) ? $settings['xml_file_name'] : 'products.xml';
        
        // Get current file info
        $file_url = get_option('varle_export_file_url', '');
        $storage_method = get_option('varle_export_storage_method', 'unknown');
        $last_error = get_option('varle_export_last_error', '');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="varle-export-admin">
                
                <!-- File Status -->
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('File Status', 'varle-export'); ?></h2>
                    <div class="inside">
                        <?php if ($file_url): ?>
                            <div class="varle-status-info">
                                <p><strong><?php esc_html_e('Current XML File:', 'varle-export'); ?></strong></p>
                                <code><?php echo esc_url($file_url); ?></code>
                                <button type="button" class="button-small copy-url-btn" data-url="<?php echo esc_url($file_url); ?>">
                                    <?php esc_html_e('Copy URL', 'varle-export'); ?>
                                </button>
                                
                                <p><strong><?php esc_html_e('Storage Method:', 'varle-export'); ?></strong> 
                                    <?php echo esc_html($this->get_storage_method_display($storage_method)); ?>
                                </p>
                                
                                <?php if ($last_generated): ?>
                                <p><strong><?php esc_html_e('Last Generated:', 'varle-export'); ?></strong> 
                                    <?php echo esc_html(mysql2date('Y-m-d H:i:s', $last_generated)); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-file-notice"><?php esc_html_e('No XML file generated yet.', 'varle-export'); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($last_error): ?>
                            <div class="error-notice">
                                <p><strong><?php esc_html_e('Last Error:', 'varle-export'); ?></strong></p>
                                <p><code><?php echo esc_html($last_error); ?></code></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Export Actions -->
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Export Actions', 'varle-export'); ?></h2>
                    <div class="inside">
                        <p><?php esc_html_e('Generate XML file for Varle.lt product import.', 'varle-export'); ?></p>
                        
                        <div class="varle-actions">
                            <button type="button" id="generate-xml" class="button button-primary">
                                <?php esc_html_e('Regenerate XML File', 'varle-export'); ?>
                            </button>
                            
                            <button type="button" id="download-xml" class="button">
                                <?php esc_html_e('Download XML', 'varle-export'); ?>
                            </button>
                            
                            <?php if ($file_url): ?>
                            <button type="button" id="view-xml" class="button" onclick="window.open('<?php echo esc_url($file_url); ?>', '_blank')">
                                <?php esc_html_e('View XML', 'varle-export'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div id="generation-status" class="varle-status" style="display: none;"></div>
                    </div>
                </div>
                
                <!-- Storage Diagnostics -->
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Storage Diagnostics', 'varle-export'); ?></h2>
                    <div class="inside">
                        <p><?php esc_html_e('Available storage methods on your hosting environment:', 'varle-export'); ?></p>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Storage Method', 'varle-export'); ?></th>
                                    <th><?php esc_html_e('Status', 'varle-export'); ?></th>
                                    <th><?php esc_html_e('Description', 'varle-export'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $this->display_storage_diagnostics(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Settings -->
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Settings', 'varle-export'); ?></h2>
                    <div class="inside">
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('varle_export_settings');
                            do_settings_sections('varle_export_settings');
                            submit_button();
                            ?>
                        </form>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Instructions', 'varle-export'); ?></h2>
                    <div class="inside">
                        <ol>
                            <li><?php esc_html_e('Configure the settings above.', 'varle-export'); ?></li>
                            <li><?php esc_html_e('Click "Regenerate XML File" to create the export file.', 'varle-export'); ?></li>
                            <li><?php esc_html_e('Copy the XML file URL from the File Status section.', 'varle-export'); ?></li>
                            <li><?php esc_html_e('Provide this URL to Varle.lt in their import system.', 'varle-export'); ?></li>
                        </ol>
                        
                        <?php 
                        $auto_generate = isset($settings['auto_generate']) ? $settings['auto_generate'] : 'yes';
                        if ($auto_generate === 'yes'): 
                        ?>
                        <div class="notice notice-info inline">
                            <p><strong><?php esc_html_e('Auto-Generation Enabled:', 'varle-export'); ?></strong> 
                            <?php esc_html_e('XML file will be automatically updated when products are added, modified, or stock changes.', 'varle-export'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Display storage method in readable format
     */
    private function get_storage_method_display($method) {
        $methods = array(
            'uploads_directory' => __('WordPress Uploads Directory', 'varle-export'),
            'plugin_directory' => __('Plugin Directory', 'varle-export'),
            'root_directory' => __('WordPress Root Directory', 'varle-export'),
            'database_storage' => __('Database Storage', 'varle-export'),
            'temp_file' => __('Temporary File Storage', 'varle-export'),
            'unknown' => __('Unknown', 'varle-export')
        );
        
        return isset($methods[$method]) ? $methods[$method] : $methods['unknown'];
    }
    
    /**
     * Display storage diagnostics table
     */
    private function display_storage_diagnostics() {
        $filename = 'products.xml';
        $upload_dir = wp_upload_dir();
        
        $diagnostics = array(
            array(
                'name' => __('WordPress Uploads Directory', 'varle-export'),
                'path' => $upload_dir['basedir'] . '/varle-export/' . $filename,
                'writable' => !$upload_dir['error'] && wp_is_writable($upload_dir['basedir']),
                'description' => __('Most reliable method (Recommended)', 'varle-export')
            ),
            array(
                'name' => __('Plugin Directory', 'varle-export'),
                'path' => VARLE_EXPORT_PLUGIN_PATH . $filename,
                'writable' => wp_is_writable(VARLE_EXPORT_PLUGIN_PATH),
                'description' => __('Works if publicly accessible', 'varle-export')
            ),
            array(
                'name' => __('WordPress Root Directory', 'varle-export'),
                'path' => ABSPATH . $filename,
                'writable' => wp_is_writable(ABSPATH),
                'description' => __('Clean URLs but often blocked', 'varle-export')
            ),
            array(
                'name' => __('Database Storage', 'varle-export'),
                'path' => __('Database', 'varle-export'),
                'writable' => true,
                'description' => __('Always works via custom endpoint', 'varle-export')
            )
        );
        
        foreach ($diagnostics as $diagnostic) {
            $status_class = $diagnostic['writable'] ? 'available' : 'unavailable';
            $status_text = $diagnostic['writable'] ? esc_html__('✓ Available', 'varle-export') : esc_html__('✗ Not Available', 'varle-export');
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($diagnostic['name']) . '</strong><br>';
            echo '<small><code>' . esc_html($diagnostic['path']) . '</code></small></td>';
            printf(
                '<td><span class="status-%s">%s</span></td>',
                esc_attr($status_class),
                $status_text
            );
            echo '<td>' . esc_html($diagnostic['description']) . '</td>';
            echo '</tr>';
        }
    }
    
    /**
     * Settings callbacks
     */
    public function general_section_callback() {
        printf('<p>%s</p>', esc_html__('Configure the general settings for Varle.lt export.', 'varle-export'));
    }
    
    public function delivery_text_callback() {
        $settings = get_option('varle_export_settings');
        $value = isset($settings['delivery_text']) ? $settings['delivery_text'] : '2-3 d. d.';
        echo '<input type="text" name="varle_export_settings[delivery_text]" value="' . esc_attr($value) . '" />';
        printf('<p class="description">%s</p>', esc_html__('Default delivery time for all products', 'varle-export'));
    }
    
    public function default_group_callback() {
        $settings = get_option('varle_export_settings');
        $value = isset($settings['default_group']) ? $settings['default_group'] : '0001';
        echo '<input type="text" name="varle_export_settings[default_group]" value="' . esc_attr($value) . '" />';
        printf('<p class="description">%s</p>', esc_html__('Default group ID for products', 'varle-export'));
    }
    
    public function manufacturer_attr_callback() {
        $settings = get_option('varle_export_settings');
        $value = isset($settings['manufacturer_attr']) ? $settings['manufacturer_attr'] : 'pa_brand';
        
        echo '<select name="varle_export_settings[manufacturer_attr]">';
        printf('<option value="">%s</option>', esc_html__('Select attribute...', 'varle-export'));
        
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            $attr_name = 'pa_' . $attribute->attribute_name;
            $selected = selected($value, $attr_name, false);
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($attr_name),
                $selected,
                esc_html($attribute->attribute_label)
            );
        }
        
        echo '</select>';
        printf('<p class="description">%s</p>', esc_html__('Product attribute to use as manufacturer', 'varle-export'));
    }
    
    public function xml_file_name_callback() {
        $settings = get_option('varle_export_settings');
        $value = isset($settings['xml_file_name']) ? $settings['xml_file_name'] : 'products.xml';
        echo '<input type="text" name="varle_export_settings[xml_file_name]" value="' . esc_attr($value) . '" />';
        printf('<p class="description">%s</p>', esc_html__('Name of the XML file', 'varle-export'));
    }
    
    public function auto_generate_callback() {
        $settings = get_option('varle_export_settings');
        $value = isset($settings['auto_generate']) ? $settings['auto_generate'] : 'yes';
        
        echo '<select name="varle_export_settings[auto_generate]">';
        printf(
            '<option value="yes"%1$s>%2$s</option>',
            selected($value, 'yes', false),
            esc_html__('Yes', 'varle-export')
        );
        printf(
            '<option value="no"%1$s>%2$s</option>',
            selected($value, 'no', false),
            esc_html__('No', 'varle-export')
        );
        echo '</select>';
        printf('<p class="description">%s</p>', esc_html__('Automatically regenerate XML when products are updated', 'varle-export'));
    }
    
    /**
     * Add product fields
     */
    public function add_product_fields() {
        echo '<div class="options_group">';
        echo '<h4>' . esc_html__('Varle.lt Settings', 'varle-export') . '</h4>';
        
        woocommerce_wp_text_input(array(
            'id' => '_varle_group',
            'label' => esc_html__('Varle Group ID', 'varle-export'),
            'description' => esc_html__('Product group identifier for Varle.lt', 'varle-export'),
            'desc_tip' => true,
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_varle_delivery_text',
            'label' => esc_html__('Delivery Text', 'varle-export'),
            'description' => esc_html__('Custom delivery text for this product', 'varle-export'),
            'desc_tip' => true,
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_varle_warranty',
            'label' => esc_html__('Warranty (months)', 'varle-export'),
            'description' => esc_html__('Warranty period in months', 'varle-export'),
            'desc_tip' => true,
            'type' => 'number'
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => '_varle_with_gift',
            'label' => esc_html__('Product with Gift', 'varle-export'),
            'description' => esc_html__('Check if this product comes with a gift', 'varle-export')
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => '_varle_exclude',
            'label' => esc_html__('Exclude from Varle Export', 'varle-export'),
            'description' => esc_html__('Check to exclude this product from Varle.lt export', 'varle-export')
        ));
        
        echo '</div>';
    }
    
    /**
     * Save product fields
     */
    public function save_product_fields($post_id) {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        $fields = array('_varle_group', '_varle_delivery_text', '_varle_warranty', '_varle_with_gift', '_varle_exclude');
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = wp_unslash($_POST[$field]);
                if (is_array($value)) {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($post_id, $field, $value);
            } else {
                delete_post_meta($post_id, $field);
            }
        }
    }
    
    /**
     * AJAX handler for testing file accessibility
     */
    public function test_file_accessibility() {
        check_ajax_referer('varle_export_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'varle-export'));
        }
        
        $file_url = get_option('varle_export_file_url', '');
        
        if (empty($file_url)) {
            wp_send_json_error(esc_html__('No XML file generated yet', 'varle-export'));
        }
        
        $response = wp_remote_get($file_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            wp_send_json_error(
                sprintf(
                    esc_html__('URL not accessible: %s', 'varle-export'),
                    esc_html($response->get_error_message())
                )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            wp_send_json_success(esc_html__('File is publicly accessible', 'varle-export'));
        }

        wp_send_json_error(
            sprintf(
                esc_html__('URL returned HTTP %d', 'varle-export'),
                (int) $response_code
            )
        );
    }
}
?>
