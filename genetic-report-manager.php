<?php
/**
 * Plugin Name: Genetic Report Manager Pro
 * Plugin URI: https://mthfrsupport.org
 * Description: Complete genetic report management system with file uploads, report generation, and visualization
 * Version: 2.1.0
 * Author: MTHFR Support
 * Author URI: https://mthfrsupport.org
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Text Domain: genetic-report-manager
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GRM_PLUGIN_FILE', __FILE__);
define('GRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRM_VERSION', '2.1.0');
define('GRM_TEXT_DOMAIN', 'genetic-report-manager');

/**
 * Main Genetic Report Manager Class
 */
class GeneticReportManagerPro {
    
    private static $instance = null;
    private $missing_files = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'), 20);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_notices', array($this, 'missing_files_notice'));
    }
    
    public function init() {
        try {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }
            
            // Check if all required files exist
            if (!$this->check_required_files()) {
                return; // Don't initialize if files are missing
            }
            
            $this->load_dependencies();
            $this->init_components();
            
        } catch (Exception $e) {
            error_log('GRM Plugin initialization failed: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'init_error_notice'));
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            GRM_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    private function check_required_files() {
        $required_files = array(
            // file => expected class
            'includes/class-grm-database.php'           => 'GRM_Database',
            'includes/class-grm-logger.php'             => 'GRM_Logger',
            'includes/class-grm-file-handler.php'       => 'GRM_File_Handler',
            'includes/class-grm-report-generator.php'   => 'GRM_Report_Generator',
            'includes/class-grm-api-handler.php'        => 'GRM_API_Handler',

            // Frontend classes
            'public/class-grm-shortcodes.php'           => 'GRM_Shortcodes',
            'public/class-grm-ajax-handler.php'         => 'GRM_Ajax_Handler',
            'public/class-grm-assets.php'               => 'GRM_Assets',
            'public/class-grm-woocommerce-integration.php' => 'GRM_WooCommerce_Integration'
        );
        
        // Add admin files only if in admin
        if (is_admin()) {
            $required_files['admin/class-grm-admin.php']         = 'GRM_Admin';
            $required_files['admin/class-grm-admin-orders.php']  = 'GRM_Admin_Orders';
            $required_files['admin/class-grm-admin-reports.php'] = 'GRM_Admin_Reports';
        }
        
        $this->missing_files = array();
        
        foreach ($required_files as $file => $class) {
            $file_path = GRM_PLUGIN_DIR . $file;

            if (!file_exists($file_path)) {
                $this->missing_files[] = $file;
                continue;
            }

            // Load the file temporarily to verify class exists
            require_once $file_path;
            if (!class_exists($class)) {
                $this->missing_files[] = $file . ' (class ' . $class . ' missing)';
            }
        }

        return empty($this->missing_files);
    }
    
    private function load_dependencies() {
        // Core classes
        require_once GRM_PLUGIN_DIR . 'includes/class-grm-database.php';
        require_once GRM_PLUGIN_DIR . 'includes/class-grm-logger.php';
        require_once GRM_PLUGIN_DIR . 'includes/class-grm-file-handler.php';
        require_once GRM_PLUGIN_DIR . 'includes/class-grm-report-generator.php';
        require_once GRM_PLUGIN_DIR . 'includes/class-grm-api-handler.php';
        
        // Frontend classes
        require_once GRM_PLUGIN_DIR . 'public/class-grm-shortcodes.php';
        require_once GRM_PLUGIN_DIR . 'public/class-grm-ajax-handler.php';
        require_once GRM_PLUGIN_DIR . 'public/class-grm-assets.php';
        require_once GRM_PLUGIN_DIR . 'public/class-grm-woocommerce-integration.php';
        
        // Admin classes
        if (is_admin()) {
            require_once GRM_PLUGIN_DIR . 'admin/class-grm-admin.php';
            require_once GRM_PLUGIN_DIR . 'admin/class-grm-admin-orders.php';
            require_once GRM_PLUGIN_DIR . 'admin/class-grm-admin-reports.php';
        }
    }
    
    private function init_components() {
        // Initialize core components
        GRM_Database::init();
        GRM_Logger::init();
        
        // Initialize frontend components
        new GRM_Shortcodes();
        new GRM_Ajax_Handler();
        new GRM_Assets();
        new GRM_WooCommerce_Integration();

        // Cron: cleanup temporary files
        add_action('grm_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        
        // Initialize admin components
        if (is_admin()) {
            new GRM_Admin();
            new GRM_Admin_Orders();
            new GRM_Admin_Reports();
        }
    }
    
    public function activate() {
        try {
            // Check if all files exist before activation
            if (!$this->check_required_files()) {
                $missing_list = implode(', ', $this->missing_files);
                wp_die(
                    sprintf(
                        'Plugin activation failed: Missing required files: %s. Please ensure all plugin files are uploaded correctly.',
                        $missing_list
                    )
                );
            }
            
            // Verify WooCommerce
            if (!class_exists('WooCommerce')) {
                wp_die('Plugin activation failed: WooCommerce is required for this plugin');
            }
            
            // Load required files for activation
            require_once GRM_PLUGIN_DIR . 'includes/class-grm-database.php';
            require_once GRM_PLUGIN_DIR . 'includes/class-grm-logger.php';
            
            // Initialize database
            GRM_Database::init();
            GRM_Database::create_tables();
            
            // Set default options
            $this->set_default_options();
            
            // Schedule any required cron jobs
            $this->schedule_cron_jobs();
            
            flush_rewrite_rules();
            
        } catch (Exception $e) {
            wp_die('Plugin activation failed: ' . $e->getMessage());
        }
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('grm_process_background_reports');
        wp_clear_scheduled_hook('grm_cleanup_temp_files');
        flush_rewrite_rules();
    }
    
    private function set_default_options() {
        add_option('grm_reportable_products', array(120, 938, 971, 977, 1698));
        add_option('grm_subscription_product', 2152);
        add_option('grm_auto_generate', 1);
        add_option('grm_api_url', 'http://api.mthfrsupport.org/');
        add_option('grm_enable_debug', 0);
        add_option('grm_upload_max_size', 50); // MB
        add_option('grm_allowed_file_types', array('zip'));
        add_option('grm_cleanup_temp_files', 1);
    }
    
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('grm_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'grm_cleanup_temp_files');
        }
    }

    public function cleanup_temp_files() {
        if (!get_option('grm_cleanup_temp_files', 1)) {
            return;
        }

        $handler = new GRM_File_Handler();
        $handler->cleanup_temp_files();
    }
    
    public function missing_files_notice() {
        if (!empty($this->missing_files) && current_user_can('activate_plugins')) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('Genetic Report Manager Pro', GRM_TEXT_DOMAIN) . '</strong><br>';
            echo __('Plugin is missing required files:', GRM_TEXT_DOMAIN) . '<br>';
            echo '<code>' . implode('</code><br><code>', $this->missing_files) . '</code><br>';
            echo __('Please ensure all plugin files are uploaded correctly and reactivate the plugin.', GRM_TEXT_DOMAIN);
            echo '</p></div>';
        }
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . __('Genetic Report Manager Pro', GRM_TEXT_DOMAIN) . '</strong> ';
        echo __('requires WooCommerce to be installed and activated.', GRM_TEXT_DOMAIN);
        echo ' <a href="' . admin_url('plugin-install.php?s=woocommerce&tab=search&type=term') . '">';
        echo __('Install WooCommerce', GRM_TEXT_DOMAIN) . '</a></p></div>';
    }
    
    public function init_error_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . __('Genetic Report Manager Pro', GRM_TEXT_DOMAIN) . '</strong> ';
        echo __('failed to initialize. Please check the error logs.', GRM_TEXT_DOMAIN);
        echo '</p></div>';
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    GeneticReportManagerPro::get_instance();
});
