<?php
/**
 * Admin Main Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Genetic Report Manager', GRM_TEXT_DOMAIN),
            __('GRM Reports', GRM_TEXT_DOMAIN),
            'manage_woocommerce',
            'grm-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-analytics',
            58
        );
        
        add_submenu_page(
            'grm-dashboard',
            __('Dashboard', GRM_TEXT_DOMAIN),
            __('Dashboard', GRM_TEXT_DOMAIN),
            'manage_woocommerce',
            'grm-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'grm-dashboard',
            __('Settings', GRM_TEXT_DOMAIN),
            __('Settings', GRM_TEXT_DOMAIN),
            'manage_woocommerce',
            'grm-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'grm-dashboard',
            __('Reports', GRM_TEXT_DOMAIN),
            __('All Reports', GRM_TEXT_DOMAIN),
            'manage_woocommerce',
            'grm-reports',
            array($this, 'reports_page')
        );
        
        add_submenu_page(
            'grm-dashboard',
            __('Uploads', GRM_TEXT_DOMAIN),
            __('All Uploads', GRM_TEXT_DOMAIN),
            'manage_woocommerce',
            'grm-uploads',
            array($this, 'uploads_page')
        );
        
        add_submenu_page(
            'grm-dashboard',
            __('Logs', GRM_TEXT_DOMAIN),
            __('System Logs', GRM_TEXT_DOMAIN),
            'manage_woocommerce',
            'grm-logs',
            array($this, 'logs_page')
        );
    }
    
    public function init_settings() {
        register_setting('grm_settings', 'grm_reportable_products');
        register_setting('grm_settings', 'grm_subscription_product');
        register_setting('grm_settings', 'grm_auto_generate');
        register_setting('grm_settings', 'grm_api_url');
        register_setting('grm_settings', 'grm_enable_debug');
        register_setting('grm_settings', 'grm_upload_max_size');
        register_setting('grm_settings', 'grm_allowed_file_types');
        register_setting('grm_settings', 'grm_cleanup_temp_files');
    }
    
    public function dashboard_page() {
        $report_generator = new GRM_Report_Generator();
        $stats = $report_generator->get_report_statistics();
        
        // Get recent reports
        global $wpdb;
        $recent_reports = $wpdb->get_results(
            "SELECT r.*, u.file_name as upload_file_name 
             FROM {$wpdb->prefix}user_reports r
             LEFT JOIN {$wpdb->prefix}user_uploads u ON r.upload_id = u.id
             ORDER BY r.created_at DESC 
             LIMIT 10"
        );
        
        include GRM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        include GRM_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    public function reports_page() {
        global $wpdb;
        
        $per_page = 25;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_reports = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_reports"
        );
        
        // Get reports for current page
        $reports = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, u.file_name as upload_file_name, u.user_id 
                 FROM {$wpdb->prefix}user_reports r
                 LEFT JOIN {$wpdb->prefix}user_uploads u ON r.upload_id = u.id
                 ORDER BY r.created_at DESC 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        $total_pages = ceil($total_reports / $per_page);
        
        include GRM_PLUGIN_DIR . 'admin/views/reports.php';
    }
    
    public function uploads_page() {
        global $wpdb;
        
        $per_page = 25;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_uploads = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_uploads"
        );
        
        // Get uploads for current page
        $uploads = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_uploads 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        $total_pages = ceil($total_uploads / $per_page);
        
        include GRM_PLUGIN_DIR . 'admin/views/uploads.php';
    }
    
    public function logs_page() {
        $log_lines = GRM_Logger::get_log_contents(200);
        
        if (isset($_POST['clear_logs'])) {
            GRM_Logger::clear_logs();
            add_settings_error('grm_logs', 'logs_cleared', __('Logs cleared successfully.', GRM_TEXT_DOMAIN), 'success');
        }
        
        if (isset($_POST['toggle_debug'])) {
            $current_debug = get_option('grm_enable_debug', 0);
            $new_debug = $current_debug ? 0 : 1;
            update_option('grm_enable_debug', $new_debug);
            GRM_Logger::enable_debug($new_debug);
            
            $message = $new_debug ? 
                __('Debug logging enabled.', GRM_TEXT_DOMAIN) : 
                __('Debug logging disabled.', GRM_TEXT_DOMAIN);
            add_settings_error('grm_logs', 'debug_toggled', $message, 'success');
        }
        
        include GRM_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    private function save_settings() {
        check_admin_referer('grm_settings_nonce');
        
        // Sanitize and save settings
        $reportable_products = array_map('intval', array_filter($_POST['grm_reportable_products'] ?? array()));
        update_option('grm_reportable_products', $reportable_products);
        
        $subscription_product = intval($_POST['grm_subscription_product'] ?? 0);
        update_option('grm_subscription_product', $subscription_product);
        
        $auto_generate = isset($_POST['grm_auto_generate']) ? 1 : 0;
        update_option('grm_auto_generate', $auto_generate);
        
        $api_url = esc_url_raw($_POST['grm_api_url'] ?? '');
        update_option('grm_api_url', $api_url);
        
        $enable_debug = isset($_POST['grm_enable_debug']) ? 1 : 0;
        update_option('grm_enable_debug', $enable_debug);
        GRM_Logger::enable_debug($enable_debug);
        
        $upload_max_size = max(1, intval($_POST['grm_upload_max_size'] ?? 50));
        update_option('grm_upload_max_size', $upload_max_size);
        
        $allowed_file_types = array_map('sanitize_text_field', $_POST['grm_allowed_file_types'] ?? array('zip'));
        update_option('grm_allowed_file_types', $allowed_file_types);
        
        $cleanup_temp_files = isset($_POST['grm_cleanup_temp_files']) ? 1 : 0;
        update_option('grm_cleanup_temp_files', $cleanup_temp_files);
        
        add_settings_error('grm_settings', 'settings_saved', __('Settings saved successfully.', GRM_TEXT_DOMAIN), 'success');
    }
    
    public function admin_notices() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('Genetic Report Manager Pro', GRM_TEXT_DOMAIN) . '</strong> ';
            echo __('requires WooCommerce to be installed and activated.', GRM_TEXT_DOMAIN);
            echo '</p></div>';
        }
        
        // Check database tables
        if (!GRM_Database::verify_tables()) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('Genetic Report Manager Pro', GRM_TEXT_DOMAIN) . '</strong> ';
            echo __('database tables are missing. Please deactivate and reactivate the plugin.', GRM_TEXT_DOMAIN);
            echo '</p></div>';
        }
        
        // Test API connection
        if (current_user_can('manage_woocommerce') && isset($_GET['page']) && strpos($_GET['page'], 'grm-') === 0) {
            $api_handler = new GRM_API_Handler();
            $connection_test = $api_handler->test_connection();
            
            if (!$connection_test['success']) {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>' . __('API Connection Warning:', GRM_TEXT_DOMAIN) . '</strong> ';
                echo sprintf(__('Cannot connect to the report generation API. Error: %s', GRM_TEXT_DOMAIN), $connection_test['error']);
                echo '</p></div>';
            }
        }
    }
}
