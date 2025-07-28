<?php
/**
 * Report Generator Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('GRM_Report_Generator')) {

class GRM_Report_Generator {
    
    private $api_handler;
    
    public function __construct() {
        if (class_exists('GRM_API_Handler')) {
            $this->api_handler = new GRM_API_Handler();
        }
    }
    
    public function generate_report($upload_id, $order_id, $product_id, $product_name, $has_subscription = false) {
        try {
            if (class_exists('GRM_Logger')) {
                GRM_Logger::info('Starting report generation', array(
                    'upload_id' => $upload_id,
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'has_subscription' => $has_subscription
                ));
            }
            
            // Validate upload exists
            if (!class_exists('GRM_Database')) {
                throw new Exception('Database class not available');
            }
            
            $upload = GRM_Database::get_upload($upload_id);
            if (!$upload) {
                throw new Exception('Upload not found: ' . $upload_id);
            }
            
            // Check if report already exists
            $existing_report = GRM_Database::get_report_by_upload($upload_id, $order_id);
            if ($existing_report && $existing_report->status === 'completed') {
                if (class_exists('GRM_Logger')) {
                    GRM_Logger::info('Report already exists and is completed', array(
                        'report_id' => $existing_report->id,
                        'upload_id' => $upload_id
                    ));
                }
                return $existing_report->id;
            }
            
            // Create or update report record
            $report_id = $this->create_or_update_report_record($upload_id, $order_id, $product_name);
            
            // Set status to processing
            GRM_Database::update_report($report_id, array(
                'status' => 'processing',
                'error_message' => null
            ));
            
            // Make API call to generate report
            if (!$this->api_handler) {
                throw new Exception('API handler not available');
            }
            
            $api_result = $this->api_handler->create_report($upload_id, $order_id, $product_name, $has_subscription);
            
            if (!$api_result['success']) {
                throw new Exception('API call failed: ' . $api_result['error']);
            }
            
            // Update report with success status
            $update_data = array(
                'status' => 'completed',
                'error_message' => null
            );
            
            // Store additional data from API response if available
            if (isset($api_result['data']['report_path'])) {
                $update_data['report_path'] = $api_result['data']['report_path'];
            }
            
            if (isset($api_result['data']['report_type'])) {
                $update_data['report_type'] = $api_result['data']['report_type'];
            }
            
            GRM_Database::update_report($report_id, $update_data);
            
            if (class_exists('GRM_Logger')) {
                GRM_Logger::info('Report generation completed successfully', array(
                    'report_id' => $report_id,
                    'upload_id' => $upload_id,
                    'order_id' => $order_id
                ));
            }
            
            return $report_id;
            
        } catch (Exception $e) {
            if (class_exists('GRM_Logger')) {
                GRM_Logger::error('Report generation failed', array(
                    'upload_id' => $upload_id,
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
            
            // Update report status to failed if we have a report_id
            if (isset($report_id) && class_exists('GRM_Database')) {
                GRM_Database::update_report($report_id, array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ));
            }
            
            return false;
        }
    }
    
    public function regenerate_report($report_id) {
        try {
            if (!class_exists('GRM_Database')) {
                throw new Exception('Database class not available');
            }
            
            $report = GRM_Database::get_report($report_id);
            if (!$report) {
                throw new Exception('Report not found: ' . $report_id);
            }
            
            if (class_exists('GRM_Logger')) {
                GRM_Logger::info('Starting report regeneration', array(
                    'report_id' => $report_id,
                    'upload_id' => $report->upload_id,
                    'order_id' => $report->order_id
                ));
            }
            
            // Set status to processing
            GRM_Database::update_report($report_id, array(
                'status' => 'processing',
                'error_message' => null
            ));
            
            // Get order to determine product name and subscription status
            $order = null;
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($report->order_id);
            }
            
            $product_name = $report->report_name ?: 'Unknown Product';
            $has_subscription = false;
            
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $upload_id_meta = $item->get_meta('_upload_id');
                    if ($upload_id_meta == $report->upload_id) {
                        $product_name = $item->get_name();
                        break;
                    }
                }
                
                // Check for subscription
                $subscription_product = get_option('grm_subscription_product', 2152);
                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $subscription_product) {
                        $has_subscription = true;
                        break;
                    }
                }
            }
            
            // Make API call to regenerate report
            if (!$this->api_handler) {
                throw new Exception('API handler not available');
            }
            
            $api_result = $this->api_handler->create_report(
                $report->upload_id, 
                $report->order_id, 
                $product_name, 
                $has_subscription
            );
            
            if (!$api_result['success']) {
                throw new Exception('API call failed: ' . $api_result['error']);
            }
            
            // Update report with success status
            $update_data = array(
                'status' => 'completed',
                'error_message' => null,
                'report_name' => $product_name
            );
            
            // Store additional data from API response if available
            if (isset($api_result['data']['report_path'])) {
                $update_data['report_path'] = $api_result['data']['report_path'];
            }
            
            if (isset($api_result['data']['report_type'])) {
                $update_data['report_type'] = $api_result['data']['report_type'];
            }
            
            GRM_Database::update_report($report_id, $update_data);
            
            if (class_exists('GRM_Logger')) {
                GRM_Logger::info('Report regeneration completed successfully', array(
                    'report_id' => $report_id,
                    'upload_id' => $report->upload_id,
                    'order_id' => $report->order_id
                ));
            }
            
            return true;
            
        } catch (Exception $e) {
            if (class_exists('GRM_Logger')) {
                GRM_Logger::error('Report regeneration failed', array(
                    'report_id' => $report_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
            
            // Update report status to failed
            if (class_exists('GRM_Database')) {
                GRM_Database::update_report($report_id, array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ));
            }
            
            return false;
        }
    }
    
    private function create_or_update_report_record($upload_id, $order_id, $product_name) {
        // Check if report already exists
        $existing_report = GRM_Database::get_report_by_upload($upload_id, $order_id);
        
        if ($existing_report) {
            // Update existing report
            GRM_Database::update_report($existing_report->id, array(
                'report_name' => $product_name,
                'status' => 'pending',
                'error_message' => null
            ));
            return $existing_report->id;
        } else {
            // Create new report
            return GRM_Database::create_report($upload_id, $order_id, $product_name, $this->determine_report_type($product_name));
        }
    }
    
    private function determine_report_type($product_name) {
        // Map product names to report types
        $type_mapping = array(
            'excipient' => 'Excipient',
            'covid' => 'Covid',
            'variant' => 'Variant',
            'methylation' => 'Methylation'
        );
        
        $product_name_lower = strtolower($product_name);
        
        foreach ($type_mapping as $keyword => $type) {
            if (strpos($product_name_lower, $keyword) !== false) {
                return $type;
            }
        }
        
        return 'Variant'; // Default type
    }
    
    public function get_report_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total reports
        $stats['total'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_reports"
        );
        
        // Reports by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}user_reports GROUP BY status"
        );
        
        $stats['by_status'] = array();
        foreach ($status_counts as $status) {
            $stats['by_status'][$status->status] = $status->count;
        }
        
        // Reports by type
        $type_counts = $wpdb->get_results(
            "SELECT report_type, COUNT(*) as count FROM {$wpdb->prefix}user_reports GROUP BY report_type"
        );
        
        $stats['by_type'] = array();
        foreach ($type_counts as $type) {
            $stats['by_type'][$type->report_type] = $type->count;
        }
        
        // Recent activity (last 30 days)
        $stats['recent'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_reports 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $stats;
    }
}

}