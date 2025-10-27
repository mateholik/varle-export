<?php
/**
 * Enhanced Cron Handler Class
 * Fixed to properly trigger XML regeneration on product updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class Varle_Export_Cron {
    
    public function __construct() {
        // Hook into cron events
        add_action('varle_export_daily', array($this, 'generate_xml_daily'));
        add_action('varle_export_hourly', array($this, 'generate_xml_hourly'));
        add_action('varle_export_delayed', array($this, 'generate_xml'));
        
        // Hook into product save events for auto-generation - ENHANCED
        add_action('woocommerce_update_product', array($this, 'schedule_xml_generation'), 10, 1);
        add_action('woocommerce_new_product', array($this, 'schedule_xml_generation'), 10, 1);
        add_action('woocommerce_product_set_stock_status', array($this, 'schedule_xml_generation'), 10, 3);
        
        // Additional hooks for comprehensive product change detection
        add_action('woocommerce_process_product_meta', array($this, 'schedule_xml_generation'), 20, 1);
        add_action('woocommerce_product_bulk_edit_save', array($this, 'schedule_xml_generation'), 10, 1);
        add_action('save_post', array($this, 'handle_product_save'), 10, 2);
        
        // Stock quantity changes
        add_action('woocommerce_product_set_stock', array($this, 'schedule_xml_generation'), 10, 1);
        add_action('woocommerce_variation_set_stock', array($this, 'schedule_xml_generation'), 10, 1);
        
        // Price changes
        add_action('woocommerce_product_object_updated_props', array($this, 'handle_product_props_update'), 10, 2);
        
        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        
        // Admin notices for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_notices', array($this, 'debug_notices'));
        }
    }

    /**
     * Log message helper.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log($level, $message) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, array('source' => 'varle-export'));
        }
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_interval($schedules) {
        $schedules['varle_hourly'] = array(
            'interval' => 3600, // 1 hour
            'display'  => esc_html__('Every Hour for Varle', 'varle-export'),
        );
        
        $schedules['varle_every_30min'] = array(
            'interval' => 1800, // 30 minutes
            'display'  => esc_html__('Every 30 Minutes for Varle', 'varle-export'),
        );
        
        return $schedules;
    }
    
    /**
     * Daily XML generation
     */
    public function generate_xml_daily() {
        $this->generate_xml();
    }
    
    /**
     * Hourly XML generation
     */
    public function generate_xml_hourly() {
        $this->generate_xml();
    }
    
    /**
     * Enhanced product save handler
     */
    public function handle_product_save($post_id, $post) {
        // Only process product posts
        if ($post->post_type !== 'product') {
            return;
        }
        
        // Only process published products
        if ($post->post_status !== 'publish') {
            return;
        }
        
        $this->schedule_xml_generation($post_id);
    }
    
    /**
     * Handle product property updates (prices, etc.)
     */
    public function handle_product_props_update($product, $updated_props) {
        // Check if important properties were updated
        $important_props = array('regular_price', 'sale_price', 'price', 'stock_quantity', 'stock_status');
        
        foreach ($important_props as $prop) {
            if (in_array($prop, $updated_props)) {
                $this->schedule_xml_generation($product->get_id());
                break;
            }
        }
    }
    
    /**
     * Schedule XML generation after product changes - ENHANCED
     */
    public function schedule_xml_generation($product_id = null) {
        $settings = get_option('varle_export_settings', array());
        
        // Check if auto-generation is enabled
        if (!isset($settings['auto_generate']) || $settings['auto_generate'] !== 'yes') {
            $this->log('notice', 'Varle XML auto-generation is disabled');
            return;
        }
        
        // Log the trigger for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('debug', 'Varle XML generation triggered by hook: ' . current_filter() . ' for product ID: ' . $product_id);
        }
        
        // Clear any existing scheduled generation to prevent duplicates
        wp_clear_scheduled_hook('varle_export_delayed');
        
        // Schedule generation with a 1-minute delay to batch multiple product updates
        $scheduled = wp_schedule_single_event(time() + 60, 'varle_export_delayed');
        
        if ($scheduled === false) {
            $this->log('error', 'Varle XML: Failed to schedule delayed generation');
            // Fallback: generate immediately
            $this->generate_xml();
        } else {
            $this->log('info', 'Varle XML: Scheduled delayed generation for 1 minute from now');
            update_option('varle_export_generation_scheduled', current_time('mysql'));
        }
    }
    
    /**
     * Generate XML file - ENHANCED
     */
    public function generate_xml() {
        // Prevent memory issues
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        
        // Log start of generation
        $this->log('info', 'Varle XML: Starting generation at ' . current_time('Y-m-d H:i:s'));
        
        try {
            $generator = new Varle_Export_XML_Generator();
            $result = $generator->generate_xml_file();
            
            if ($result) {
                $this->log_success();
                $this->log('info', 'Varle XML: Generation completed successfully');
            } else {
                $this->log_error('XML generation returned false');
                $this->log('error', 'Varle XML: Generation failed - returned false');
            }
            
        } catch (Exception $e) {
            $this->log_error('XML generation exception: ' . $e->getMessage());
            $this->log('error', 'Varle XML: Generation failed with exception - ' . $e->getMessage());
        }
        
        // Clear the scheduled flag
        delete_option('varle_export_generation_scheduled');
    }
    
    /**
     * Log successful generation - ENHANCED
     */
    private function log_success() {
        update_option('varle_export_last_cron_run', current_time('mysql'));
        update_option('varle_export_last_cron_status', 'success');
        update_option('varle_export_last_generated', current_time('mysql')); // This ensures "Last Generated" updates
        
        // Clear any previous errors
        delete_option('varle_export_last_error');
        
        // Clean up old logs
        $this->cleanup_logs();
    }
    
    /**
     * Log error
     */
    private function log_error($message) {
        update_option('varle_export_last_cron_run', current_time('mysql'));
        update_option('varle_export_last_cron_status', 'error');
        update_option('varle_export_last_error', $message);
        
        // Log to WordPress error log
        $this->log('error', 'Varle Export Error: ' . $message);
        
        // Send email notification if configured
        $this->maybe_send_error_notification($message);
    }
    
    /**
     * Send error notification email
     */
    private function maybe_send_error_notification($message) {
        $settings = get_option('varle_export_settings', array());
        
        if (!isset($settings['error_notifications']) || $settings['error_notifications'] !== 'yes') {
            return;
        }
        
        $admin_email = get_option('admin_email');
        /* translators: %s: website name. */
        $subject = sprintf(__('[%s] Varle Export Error', 'varle-export'), get_bloginfo('name'));
        
        /* translators: 1: error message, 2: generation time. */
        $body = sprintf(
            __("There was an error generating the Varle.lt XML export:\n\n%1\$s\n\nTime: %2\$s\n\nPlease check your website and resolve the issue.", 'varle-export'),
            $message,
            current_time('Y-m-d H:i:s')
        );
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Clean up old logs and options
     */
    private function cleanup_logs() {
        // Keep only last 10 error logs (if you implement detailed logging)
        // This is a placeholder for more advanced logging functionality
    }
    
    /**
     * Check if cron is working properly
     */
    public function is_cron_working() {
        $last_run = get_option('varle_export_last_cron_run');
        
        if (!$last_run) {
            return false;
        }
        
        $last_run_time = strtotime($last_run);
        $current_time = current_time('timestamp');
        
        // If last run was more than 25 hours ago, cron might not be working
        return ($current_time - $last_run_time) < (25 * 3600);
    }
    
    /**
     * Get cron status for admin display
     */
    public function get_cron_status() {
        $status = array(
            'last_run' => get_option('varle_export_last_cron_run'),
            'last_status' => get_option('varle_export_last_cron_status'),
            'last_error' => get_option('varle_export_last_error'),
            'is_working' => $this->is_cron_working(),
            'next_scheduled' => wp_next_scheduled('varle_export_daily'),
            'generation_scheduled' => get_option('varle_export_generation_scheduled')
        );
        
        return $status;
    }
    
    /**
     * Debug notices for development
     */
    public function debug_notices() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        $scheduled = get_option('varle_export_generation_scheduled');
        if ($scheduled) {
            echo '<div class="notice notice-info is-dismissible">';
            printf(
                '<p><strong>%s</strong> %s</p>',
                esc_html__('Varle Export Debug:', 'varle-export'),
                esc_html(
                    sprintf(
                        /* translators: %s: scheduled time. */
                        __('XML generation scheduled at %s', 'varle-export'),
                        $scheduled
                    )
                )
            );
            echo '</div>';
        }
        
        $last_run = get_option('varle_export_last_cron_run');
        $last_status = get_option('varle_export_last_cron_status');
        
        if ($last_run && $last_status === 'success') {
            $time_diff = current_time('timestamp') - strtotime($last_run);
            if ($time_diff < 300) { // Less than 5 minutes ago
                echo '<div class="notice notice-success is-dismissible">';
                printf(
                    '<p><strong>%s</strong> %s</p>',
                    esc_html__('Varle Export:', 'varle-export'),
                    esc_html(
                        sprintf(
                            /* translators: %s: human readable time difference. */
                            __('XML was regenerated %s ago', 'varle-export'),
                            human_time_diff(strtotime($last_run))
                        )
                    )
                );
                echo '</div>';
            }
        }
    }
    
    /**
     * Manual cron setup (if WP cron is disabled)
     */
    public function get_manual_cron_command() {
        $cron_url = add_query_arg(array(
            'action' => 'varle_manual_cron',
            'key' => wp_hash('varle_manual_cron' . NONCE_SALT)
        ), home_url('/'));
        
        return $cron_url;
    }
    
    /**
     * Handle manual cron execution
     */
    public function handle_manual_cron() {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Access controlled via hashed key.
        if ('varle_manual_cron' !== $action) {
            return;
        }
        
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Custom hash provides request validation.
        if ($key !== wp_hash('varle_manual_cron' . NONCE_SALT)) {
            wp_die(esc_html__('Invalid key', 'varle-export'));
        }
        
        $this->generate_xml();
        
        /* translators: %s: generation time. */
        $completion_message = sprintf(
            __('Varle XML generation completed at %s', 'varle-export'),
            current_time('Y-m-d H:i:s')
        );

        printf('%s', esc_html($completion_message));
        exit;
    }
    
    /**
     * Force immediate XML generation (for testing)
     */
    public function force_generate_now($product_id = null) {
        $this->log('notice', 'Varle XML: Force generation triggered for product ' . $product_id);
        $this->generate_xml();
    }
}

// Initialize manual cron handler
add_action('init', function() {
    $cron = new Varle_Export_Cron();
    $cron->handle_manual_cron();
});

// Add action for immediate generation when needed
add_action('varle_force_xml_generation', function($product_id = null) {
    $cron = new Varle_Export_Cron();
    $cron->force_generate_now($product_id);
});
