<?php
/**
 * AJAX Handler Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_Ajax_Handler {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // File upload handlers
        add_action('wp_ajax_grm_file_upload', array($this, 'handle_file_upload'));
        add_action('wp_ajax_nopriv_grm_file_upload', array($this, 'handle_file_upload'));
        
        // File management handlers
        add_action('wp_ajax_grm_delete_file', array($this, 'handle_delete_file'));
        add_action('wp_ajax_nopriv_grm_delete_file', array($this, 'handle_delete_file'));
        
        add_action('wp_ajax_grm_get_user_uploads', array($this, 'get_user_uploads'));
        add_action('wp_ajax_nopriv_grm_get_user_uploads', array($this, 'get_user_uploads'));
        
        // Report handlers
        add_action('wp_ajax_grm_get_user_result', array($this, 'get_user_results'));
        add_action('wp_ajax_nopriv_grm_get_user_result', array($this, 'get_user_results'));
        
        add_action('wp_ajax_grm_download_pdf', array($this, 'download_pdf'));
        add_action('wp_ajax_nopriv_grm_download_pdf', array($this, 'download_pdf'));
        
        // Report visualization handlers
        add_action('wp_ajax_grm_load_report_visualization', array($this, 'load_report_visualization'));
        add_action('wp_ajax_nopriv_grm_load_report_visualization', array($this, 'load_report_visualization'));
        
        add_action('wp_ajax_grm_reload_results_table', array($this, 'reload_results_table'));
        add_action('wp_ajax_nopriv_grm_reload_results_table', array($this, 'reload_results_table'));
        
        // Admin handlers
        add_action('wp_ajax_grm_admin_download_pdf', array($this, 'admin_download_pdf'));
        add_action('wp_ajax_grm_admin_regenerate_pdf', array($this, 'admin_regenerate_pdf'));
    }
    
    public function handle_file_upload() {
        check_ajax_referer('file_upload', 'security');
        
        try {
            if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
                throw new Exception(__('No file uploaded', GRM_TEXT_DOMAIN));
            }
            
            $user_id = get_current_user_id();
            if ($user_id == 0) {
                $user_id = 'guest';
            }
            
            $file_handler = new GRM_File_Handler();
            $result = $file_handler->handle_upload($_FILES['file'], $user_id);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            GRM_Logger::error('File upload failed: ' . $e->getMessage(), array(
                'user_id' => get_current_user_id(),
                'file_info' => $_FILES['file'] ?? null
            ));
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function handle_delete_file() {
        check_ajax_referer('delete_file_nonce', 'security');
        
        try {
            if (!isset($_POST['upload_id'])) {
                throw new Exception(__('No upload ID provided', GRM_TEXT_DOMAIN));
            }
            
            $upload_id = intval($_POST['upload_id']);
            $user_id = get_current_user_id();
            
            // Verify ownership
            $upload = GRM_Database::get_upload($upload_id);
            if (!$upload || ($upload->user_id != $user_id && $upload->user_id != 'guest')) {
                throw new Exception(__('Permission denied', GRM_TEXT_DOMAIN));
            }
            
            GRM_Database::delete_upload($upload_id);
            
            wp_send_json_success(array(
                'message' => __('File and associated reports deleted successfully', GRM_TEXT_DOMAIN)
            ));
            
        } catch (Exception $e) {
            GRM_Logger::error('File deletion failed: ' . $e->getMessage(), array(
                'upload_id' => $_POST['upload_id'] ?? null,
                'user_id' => get_current_user_id()
            ));
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_user_uploads() {
        check_ajax_referer('get_user_uploads_nonce', 'security');
        
        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                throw new Exception(__('User not logged in', GRM_TEXT_DOMAIN));
            }
            
            $uploads = GRM_Database::get_user_uploads($user_id);
            
            if (empty($uploads)) {
                wp_send_json_error(array('message' => __('No uploads found', GRM_TEXT_DOMAIN)));
                return;
            }
            
            $uploads_data = array_map(function($upload) {
                return array(
                    'upload_id' => $upload->id,
                    'file_name' => $upload->file_name,
                    'format' => $upload->source_type,
                    'created_at' => date('Y-m-d', strtotime($upload->created_at))
                );
            }, $uploads);
            
            wp_send_json_success(array('uploads' => $uploads_data));
            
        } catch (Exception $e) {
            GRM_Logger::error('Get user uploads failed: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function get_user_results() {
        check_ajax_referer('get_user_result_nonce', 'security');
        
        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error(array('message' => __('User not logged in', GRM_TEXT_DOMAIN)));
                return;
            }
            
            $reports = GRM_Database::get_user_reports($user_id, 'completed');
            
            if (empty($reports)) {
                wp_send_json_error(array('message' => __('No reports found', GRM_TEXT_DOMAIN)));
                return;
            }
            
            $results_data = array_map(function($report) {
                return array(
                    'result_id' => $report->id,
                    'report_name' => $report->report_name,
                    'file_name' => basename($report->upload_file_name),
                    'created_at' => date('Y-m-d', strtotime($report->updated_at)),
                    'upload_id' => $report->upload_id
                );
            }, $reports);
            
            wp_send_json_success(array('results' => $results_data));
            
        } catch (Exception $e) {
            GRM_Logger::error('Get user results failed: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function download_pdf() {
        check_ajax_referer('download_pdf', 'security');
        
        try {
            if (!isset($_POST['result_id'])) {
                throw new Exception(__('No result ID provided', GRM_TEXT_DOMAIN));
            }
            
            $result_id = intval($_POST['result_id']);
            $report = GRM_Database::get_report($result_id);
            
            if (!$report || $report->status !== 'completed') {
                throw new Exception(__('Report not found or not completed', GRM_TEXT_DOMAIN));
            }
            
            // Try API first, then file system
            $api_handler = new GRM_API_Handler();
            $pdf_result = $api_handler->download_report($report->upload_id);
            
            if ($pdf_result['success']) {
                wp_send_json_success($pdf_result['data']);
            } else {
                throw new Exception($pdf_result['error']);
            }
            
        } catch (Exception $e) {
            GRM_Logger::error('PDF download failed: ' . $e->getMessage(), array(
                'result_id' => $_POST['result_id'] ?? null
            ));
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function load_report_visualization() {
        check_ajax_referer('load_report_visualization', 'security');
        
        try {
            if (!isset($_POST['result_id'])) {
                throw new Exception(__('No result ID provided', GRM_TEXT_DOMAIN));
            }
            
            $result_id = intval($_POST['result_id']);
            $file_name = sanitize_text_field($_POST['file_name'] ?? '');
            $folder_name = sanitize_text_field($_POST['folder_name'] ?? '');
            
            // Verify report exists and user has access
            $report = GRM_Database::get_report($result_id);
            if (!$report) {
                throw new Exception(__('Report not found', GRM_TEXT_DOMAIN));
            }
            
            echo do_shortcode(sprintf(
                '[grm_report_visualization result_id="%d" file_name="%s" folder_name="%s"]',
                $result_id,
                esc_attr($file_name),
                esc_attr($folder_name)
            ));
            
        } catch (Exception $e) {
            GRM_Logger::error('Load report visualization failed: ' . $e->getMessage());
            echo '<div class="error">' . esc_html($e->getMessage()) . '</div>';
        }
        
        wp_die();
    }
    
    public function reload_results_table() {
        check_ajax_referer('reload_results_table', 'security');
        
        try {
            echo do_shortcode('[grm_user_results_table]');
            
        } catch (Exception $e) {
            GRM_Logger::error('Reload results table failed: ' . $e->getMessage());
            echo '<div class="error">' . esc_html($e->getMessage()) . '</div>';
        }
        
        wp_die();
    }
    
    public function admin_download_pdf() {
        check_ajax_referer('admin_download_pdf', 'security');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', GRM_TEXT_DOMAIN)));
            return;
        }
        
        try {
            if (!isset($_POST['result_id'])) {
                throw new Exception(__('No result ID provided', GRM_TEXT_DOMAIN));
            }
            
            $result_id = intval($_POST['result_id']);
            $report = GRM_Database::get_report($result_id);
            
            if (!$report) {
                throw new Exception(__('Report not found', GRM_TEXT_DOMAIN));
            }
            
            if ($report->status !== 'completed') {
                throw new Exception(__('Report is not completed yet', GRM_TEXT_DOMAIN));
            }
            
            $api_handler = new GRM_API_Handler();
            $pdf_result = $api_handler->download_report($report->upload_id);
            
            if ($pdf_result['success']) {
                wp_send_json_success($pdf_result['data']);
            } else {
                throw new Exception($pdf_result['error']);
            }
            
        } catch (Exception $e) {
            GRM_Logger::error('Admin PDF download failed: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function admin_regenerate_pdf() {
        check_ajax_referer('admin_regenerate_pdf', 'security');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', GRM_TEXT_DOMAIN)));
            return;
        }
        
        try {
            if (!isset($_POST['result_id'])) {
                throw new Exception(__('No result ID provided', GRM_TEXT_DOMAIN));
            }
            
            $result_id = intval($_POST['result_id']);
            $report = GRM_Database::get_report($result_id);
            
            if (!$report) {
                throw new Exception(__('Report not found', GRM_TEXT_DOMAIN));
            }
            
            // Update status to processing
            GRM_Database::update_report($result_id, array(
                'status' => 'processing',
                'error_message' => null
            ));
            
            $report_generator = new GRM_Report_Generator();
            $result = $report_generator->regenerate_report($report_id);
            
            if ($result) {
                wp_send_json_success(array('message' => __('Report regenerated successfully', GRM_TEXT_DOMAIN)));
            } else {
                throw new Exception(__('Failed to regenerate report', GRM_TEXT_DOMAIN));
            }
            
        } catch (Exception $e) {
            GRM_Logger::error('Admin PDF regeneration failed: ' . $e->getMessage());
            
            // Update status to failed
            if (isset($result_id)) {
                GRM_Database::update_report($result_id, array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ));
            }
            
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}