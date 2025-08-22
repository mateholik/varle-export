<?php
/**
 * Cron Handler Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Varle_Export_Cron {
    
    public function __construct() {
        // Hook into cron events
        add_action('varle_export_daily', array($this, 'generate_xml_daily'));
        add_action('varle_export_hourly', array($this, 'generate_xml_hourly'));
        
        // Hook into product save events for auto-generation
        add_action('woocommerce_update_product', array($this, 'schedule_xml_generation'));
        add_action('woocommerce_new_product', array($this, 'schedule_xml_generation'));
        add_action('woocommerce_product_set_stock_status', array($this, 'schedule_xml_generation'));
        
        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
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
     * Schedule XML generation after product changes
     */
    public function schedule_xml_generation($product_id = null) {
        $settings = get_option('varle_export_settings', array());
        
        // Check if auto-generation is enabled
        if (!isset($settings['auto_generate']) || $settings['auto_generate'] !== 'yes') {
            return;
        }
        
        // Prevent duplicate scheduling
        if (!wp_next_scheduled('varle_export_delayed')) {
            // Schedule generation with a 2-minute delay to batch multiple product updates
            wp_schedule_single_event(time() + 120, 'varle_export_delayed');
            add_action('varle_export_delayed', array($this, 'generate_xml'));
        }
    }
    
    /**
     * Generate XML file
     */
    public function generate_xml() {
        // Prevent memory issues
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes
        
        try {
            $generator = new Varle_Export_XML_Generator();
            $result = $generator->generate_xml_file();
            
            if ($result) {
                $this->log_success();
            } else {
                $this->log_error('XML generation returned false');
            }
            
        } catch (Exception $e) {
            $this->log_error('XML generation exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Log successful generation
     */
    private function log_success() {
        update_option('varle_export_last_cron_run', current_time('mysql'));
        update_option('varle_export_last_cron_status', 'success');
        
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
        error_log('Varle Export Error: ' . $message);
        
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
        $subject = sprintf(__('[%s] Varle Export Error', 'varle-export'), get_bloginfo('name'));
        
        $body = sprintf(
            __("There was an error generating the Varle.lt XML export:\n\n%s\n\nTime: %s\n\nPlease check your website and resolve the issue.", 'varle-export'),
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
            'next_scheduled' => wp_next_scheduled('varle_export_daily')
        );
        
        return $status;
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
        if (!isset($_GET['action']) || $_GET['action'] !== 'varle_manual_cron') {
            return;
        }
        
        if (!isset($_GET['key']) || $_GET['key'] !== wp_hash('varle_manual_cron' . NONCE_SALT)) {
            wp_die('Invalid key');
        }
        
        $this->generate_xml();
        
        echo 'Varle XML generation completed at ' . current_time('Y-m-d H:i:s');
        exit;
    }
}

// Initialize manual cron handler
add_action('init', function() {
    $cron = new Varle_Export_Cron();
    $cron->handle_manual_cron();
});
?>