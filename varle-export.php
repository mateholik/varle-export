<?php
/**
 * Plugin Name: Varle.lt Product Export
 * Plugin URI: https://vladis.lt
 * Description: Export WooCommerce products to Varle.lt XML format
 * Version: 1.0.0
 * Author: Vladis
 * Author URI: https://vladis.lt
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: varle-export
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VARLE_EXPORT_VERSION', '1.0.0');
define('VARLE_EXPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VARLE_EXPORT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VARLE_EXPORT_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class VarleExportPlugin {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once VARLE_EXPORT_PLUGIN_PATH . 'includes/class-xml-generator.php';
        require_once VARLE_EXPORT_PLUGIN_PATH . 'includes/class-admin.php';
        require_once VARLE_EXPORT_PLUGIN_PATH . 'includes/class-cron.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin interface
        if (is_admin()) {
            new Varle_Export_Admin();
        }
        
        // Initialize cron jobs
        new Varle_Export_Cron();
        
        // Handle AJAX requests
        add_action('wp_ajax_varle_export_xml', array($this, 'handle_ajax_export'));
        add_action('wp_ajax_varle_generate_xml', array($this, 'handle_ajax_generate'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        add_option('varle_export_settings', array(
            'delivery_text' => '2-3 d. d.',
            'default_group' => '0001',
            'manufacturer_attr' => 'pa_brand',
            'auto_generate' => 'yes',
            'xml_file_name' => 'products.xml',
            'error_notifications' => 'no',
        ));
        
        // Schedule cron job
        if (!wp_next_scheduled('varle_export_daily')) {
            wp_schedule_event(time(), 'daily', 'varle_export_daily');
        }
        
        // Create XML file directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $varle_dir = $upload_dir['basedir'] . '/varle-export/';
        if (!file_exists($varle_dir)) {
            wp_mkdir_p($varle_dir);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('varle_export_daily');
        wp_clear_scheduled_hook('varle_export_hourly');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        printf(
            '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
            esc_html__('Varle.lt Export:', 'varle-export'),
            esc_html__('This plugin requires WooCommerce to be installed and active.', 'varle-export')
        );
    }
    
    /**
     * Handle AJAX export request
     */
    public function handle_ajax_export() {
        // Verify nonce
        check_ajax_referer('varle_export_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions', 'varle-export'));
        }
        
        $generator = new Varle_Export_XML_Generator();
        
        // Set headers for download
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="varle_products_' . gmdate('Y-m-d') . '.xml"');
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentionally returning raw XML content.
        echo $generator->generate_xml_content();
        wp_die();
    }
    
    /**
     * Handle AJAX generate request
     */
    public function handle_ajax_generate() {
        // Verify nonce
        check_ajax_referer('varle_export_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'varle-export'));
        }
        
        $generator = new Varle_Export_XML_Generator();
        $result = $generator->generate_xml_file();
        
        if ($result) {
            $file_url = get_option('varle_export_file_url', '');
            wp_send_json_success(array(
                'message' => esc_html__('XML file generated successfully!', 'varle-export'),
                'file_url' => esc_url_raw($file_url)
            ));
        } else {
            wp_send_json_error(esc_html__('Failed to generate XML file', 'varle-export'));
        }
    }
}

/**
 * Initialize the plugin
 */
function varle_export_init() {
    return VarleExportPlugin::get_instance();
}

// Start the plugin
varle_export_init();

/**
 * Add settings link to plugin page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'varle_export_action_links');

function varle_export_action_links($links) {
    $settings_link = '<a href="admin.php?page=varle-export">' . esc_html__('Settings', 'varle-export') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>
