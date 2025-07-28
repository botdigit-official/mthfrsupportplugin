<?php
/**
 * Assets Manager Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function enqueue_frontend_assets() {
        // Only enqueue on specific pages
        if (!$this->should_enqueue_frontend_assets()) {
            return;
        }
        
        // Core libraries
        $this->enqueue_core_libraries();
        
        // Plugin specific assets
        $this->enqueue_plugin_assets();
        
        // Localize scripts
        $this->localize_frontend_scripts();
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on order edit pages and plugin admin pages
        if (!$this->should_enqueue_admin_assets($hook)) {
            return;
        }
        
        wp_enqueue_script(
            'grm-admin-js',
            GRM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GRM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'grm-admin-css',
            GRM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GRM_VERSION
        );
        
        wp_localize_script('grm-admin-js', 'grm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('grm_admin_nonce'),
            'text' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', GRM_TEXT_DOMAIN),
                'confirm_regenerate' => __('Are you sure you want to regenerate this report?', GRM_TEXT_DOMAIN),
                'downloading' => __('Downloading...', GRM_TEXT_DOMAIN),
                'regenerating' => __('Regenerating...', GRM_TEXT_DOMAIN),
                'processing' => __('Processing...', GRM_TEXT_DOMAIN),
                'error' => __('Error', GRM_TEXT_DOMAIN),
                'success' => __('Success', GRM_TEXT_DOMAIN)
            )
        ));
    }
    
    private function should_enqueue_frontend_assets() {
        global $post;
        
        // Check if we're on specific pages that need our assets
        $target_pages = array('order-report', 'view-report', 'sterlings-app');
        
        if (is_page($target_pages)) {
            return true;
        }
        
        // Check if any of our shortcodes are present in the post content
        if ($post && has_shortcode($post->post_content, 'grm_report_visualization')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'grm_user_uploads_table')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'grm_user_results_table')) {
            return true;
        }
        
        return false;
    }
    
    private function should_enqueue_admin_assets($hook) {
        // Load on order edit pages
        if (strpos($hook, 'post.php') !== false || strpos($hook, 'wc-orders') !== false) {
            global $post_type;
            if ($post_type === 'shop_order') {
                return true;
            }
        }
        
        // Load on our plugin admin pages
        if (strpos($hook, 'grm_') !== false) {
            return true;
        }
        
        return false;
    }
    
    private function enqueue_core_libraries() {
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
            array(),
            '6.0.0-beta3'
        );
        
        // SweetAlert2
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            array(),
            '11',
            true
        );
        
        // DataTables
        wp_enqueue_style(
            'datatables-css',
            'https://cdn.datatables.net/2.2.1/css/dataTables.dataTables.css',
            array(),
            '2.2.1'
        );
        
        wp_enqueue_script(
            'datatables-js',
            'https://cdn.datatables.net/2.2.1/js/dataTables.js',
            array('jquery'),
            '2.2.1',
            true
        );
        
        // DataTables extensions
        $this->enqueue_datatables_extensions();
        
        // Select2
        wp_enqueue_style(
            'select2-css',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
            array(),
            '4.0.13'
        );
        
        wp_enqueue_script(
            'select2-js',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
            array('jquery'),
            '4.0.13',
            true
        );
        
        // SimpleBar
        wp_enqueue_style(
            'simplebar-css',
            'https://cdn.jsdelivr.net/npm/simplebar@6.3.0/dist/simplebar.min.css',
            array(),
            '6.3.0'
        );
        
        wp_enqueue_script(
            'simplebar-js',
            'https://cdn.jsdelivr.net/npm/simplebar@6.3.0/dist/simplebar.min.js',
            array(),
            '6.3.0',
            true
        );
    }
    
    private function enqueue_datatables_extensions() {
        // Row Group
        wp_enqueue_script(
            'datatables-rowgroup-js',
            'https://cdn.datatables.net/rowgroup/1.5.1/js/dataTables.rowGroup.min.js',
            array('datatables-js'),
            '1.5.1',
            true
        );
        
        // Responsive
        wp_enqueue_style(
            'datatables-responsive-css',
            'https://cdn.datatables.net/responsive/3.0.3/css/responsive.dataTables.min.css',
            array('datatables-css'),
            '3.0.3'
        );
        
        wp_enqueue_script(
            'datatables-responsive-js',
            'https://cdn.datatables.net/responsive/3.0.3/js/dataTables.responsive.min.js',
            array('datatables-js'),
            '3.0.3',
            true
        );
        
        // Scroller
        wp_enqueue_style(
            'datatables-scroller-css',
            'https://cdn.datatables.net/scroller/2.4.3/css/scroller.dataTables.min.css',
            array('datatables-css'),
            '2.4.3'
        );
        
        wp_enqueue_script(
            'datatables-scroller-js',
            'https://cdn.datatables.net/scroller/2.4.3/js/dataTables.scroller.min.js',
            array('datatables-js'),
            '2.4.3',
            true
        );
        
        // Scroll Resize
        wp_enqueue_script(
            'datatables-scroll-resize-js',
            'https://cdn.datatables.net/plug-ins/2.2.1/features/scrollResize/dataTables.scrollResize.min.js',
            array('datatables-js'),
            '2.2.1',
            true
        );
    }
    
    private function enqueue_plugin_assets() {
        // Plugin CSS
        wp_enqueue_style(
            'grm-report-styles',
            GRM_PLUGIN_URL . 'assets/css/report-styles.css',
            array(),
            GRM_VERSION
        );
        
        wp_enqueue_style(
            'grm-frontend-styles',
            GRM_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            GRM_VERSION
        );
        
        // Plugin JavaScript files based on current page
        if (is_page('order-report')) {
            wp_enqueue_script(
                'grm-uploaded-files-js',
                GRM_PLUGIN_URL . 'assets/js/uploaded-files.js',
                array('jquery', 'datatables-js'),
                GRM_VERSION,
                true
            );
        }
        
        if (is_page('view-report') || is_page('sterlings-app')) {
            wp_enqueue_script(
                'grm-result-files-js',
                GRM_PLUGIN_URL . 'assets/js/result-files.js',
                array('jquery', 'datatables-js'),
                GRM_VERSION,
                true
            );
            
            wp_enqueue_script(
                'grm-download-report-js',
                GRM_PLUGIN_URL . 'assets/js/download-report-button.js',
                array('jquery'),
                GRM_VERSION,
                true
            );
        }
        
        if (is_page('sterlings-app')) {
            wp_enqueue_script(
                'grm-view-files-js',
                GRM_PLUGIN_URL . 'assets/js/view-files.js',
                array('jquery', 'datatables-js'),
                GRM_VERSION,
                true
            );
        }
    }
    
    private function localize_frontend_scripts() {
        // Common AJAX data for all scripts
        $ajax_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('file_upload'),
            'delete_file_nonce' => wp_create_nonce('delete_file_nonce'),
            'get_user_uploads_nonce' => wp_create_nonce('get_user_uploads_nonce'),
            'get_user_result_nonce' => wp_create_nonce('get_user_result_nonce'),
            'load_report_visualization' => wp_create_nonce('load_report_visualization'),
            'reload_results_table' => wp_create_nonce('reload_results_table'),
            'download_pdf' => wp_create_nonce('download_pdf')
        );
        
        // Localize for uploaded files
        if (wp_script_is('grm-uploaded-files-js', 'enqueued')) {
            wp_localize_script('grm-uploaded-files-js', 'uploadData', $ajax_data);
        }
        
        // Localize for result files
        if (wp_script_is('grm-result-files-js', 'enqueued')) {
            wp_localize_script('grm-result-files-js', 'resultData', $ajax_data);
        }
        
        // Localize for download button
        if (wp_script_is('grm-download-report-js', 'enqueued')) {
            wp_localize_script('grm-download-report-js', 'downloadResult', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'download_pdf' => wp_create_nonce('download_pdf')
            ));
        }
        
        // Localize for view files
        if (wp_script_is('grm-view-files-js', 'enqueued')) {
            wp_localize_script('grm-view-files-js', 'viewData', $ajax_data);
        }
    }
}