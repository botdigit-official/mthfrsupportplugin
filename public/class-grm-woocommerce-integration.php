<?php
/**
 * WooCommerce Integration Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_WooCommerce_Integration {
    
    private $reportable_products = array();
    private $subscription_product = null;
    
    public function __construct() {
        $this->reportable_products = get_option('grm_reportable_products', array(120, 938, 971, 977, 1698));
        $this->subscription_product = get_option('grm_subscription_product', 2152);
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Cart validation
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_cart_addition'), 10, 3);
        
        // Store upload_id with cart item
        add_filter('woocommerce_add_cart_item_data', array($this, 'store_upload_id_with_cart_item'), 10, 3);
        
        // Save upload_id to order item meta during checkout
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_upload_id_to_order_item'), 10, 4);
        
        // Process PDF creation after order completion
        add_action('woocommerce_order_status_completed', array($this, 'process_pdf_creation'), 10, 1);
        
        // Background report processing
        add_action('grm_process_background_reports', array($this, 'process_background_reports'), 10, 2);
    }
    
    public function validate_cart_addition($passed, $product_id, $quantity) {
        // Check if this is a reportable product
        if (!in_array($product_id, $this->reportable_products)) {
            return $passed;
        }
        
        // Remove existing reportable products from cart
        foreach (WC()->cart->get_cart() as $key => $item) {
            if (in_array($item['product_id'], $this->reportable_products)) {
                WC()->cart->remove_cart_item($key);
            }
        }
        
        // Validate upload_id is present
        $upload_id = WC()->session->get('temp_upload_id');
        if (empty($upload_id)) {
            GRM_Logger::warning('Upload ID missing for reportable product', array(
                'product_id' => $product_id,
                'session_data' => WC()->session->get_session_data()
            ));
            
            // Redirect to order report page
            wp_redirect(site_url('/order-report'));
            exit;
        }
        
        // Validate upload exists and belongs to current user
        $upload = GRM_Database::get_upload($upload_id);
        $user_id = get_current_user_id();
        
        if (!$upload) {
            wc_add_notice(__('Invalid upload file. Please upload a file first.', GRM_TEXT_DOMAIN), 'error');
            return false;
        }
        
        if ($upload->user_id != $user_id && $upload->user_id != 'guest') {
            wc_add_notice(__('Access denied to the uploaded file.', GRM_TEXT_DOMAIN), 'error');
            return false;
        }
        
        return $passed;
    }
    
    public function store_upload_id_with_cart_item($cart_item_data, $product_id, $variation_id) {
        GRM_Logger::debug('Storing upload ID with cart item', array(
            'product_id' => $product_id,
            'variation_id' => $variation_id
        ));
        
        $upload_id = WC()->session->get('temp_upload_id');
        
        if ($upload_id && in_array($product_id, $this->reportable_products)) {
            GRM_Logger::info('Upload ID stored in cart item data', array(
                'upload_id' => $upload_id,
                'product_id' => $product_id
            ));
            
            $cart_item_data['upload_id'] = $upload_id;
        }
        
        return $cart_item_data;
    }
    
    public function save_upload_id_to_order_item($item, $cart_item_key, $values, $order) {
        GRM_Logger::debug('Saving upload ID to order item', array(
            'order_id' => $order->get_id(),
            'cart_item_key' => $cart_item_key
        ));
        
        if (isset($values['upload_id'])) {
            $upload_id = $values['upload_id'];
            GRM_Logger::info('Upload ID saved to order item', array(
                'upload_id' => $upload_id,
                'order_id' => $order->get_id(),
                'product_id' => $item->get_product_id()
            ));
            
            $item->add_meta_data('_upload_id', $upload_id);
        }
    }
    
    public function process_pdf_creation($order_id) {
        GRM_Logger::info('Processing PDF creation for completed order', array('order_id' => $order_id));
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Order not found: ' . $order_id);
            }
            
            // Check if order has subscription
            $has_subscription = $this->order_has_product($order, $this->subscription_product);
            
            $items_to_process = array();
            
            foreach ($order->get_items() as $item_id => $item) {
                $upload_id = $item->get_meta('_upload_id');
                $product_id = $item->get_product_id();
                $product_name = $item->get_name();
                
                GRM_Logger::debug('Processing order item', array(
                    'item_id' => $item_id,
                    'product_id' => $product_id,
                    'upload_id' => $upload_id,
                    'product_name' => $product_name
                ));
                
                if ($upload_id && in_array($product_id, $this->reportable_products)) {
                    // Check if report already exists
                    $existing_report = GRM_Database::get_report_by_upload($upload_id, $order_id);
                    
                    if (!$existing_report) {
                        $items_to_process[] = array(
                            'upload_id' => $upload_id,
                            'product_id' => $product_id,
                            'product_name' => $product_name,
                            'has_subscription' => $has_subscription
                        );
                    }
                }
            }
            
            if (!empty($items_to_process)) {
                if (get_option('grm_auto_generate', 1)) {
                    // Schedule background processing
                    wp_schedule_single_event(
                        time() + 60, 
                        'grm_process_background_reports', 
                        array($order_id, $items_to_process)
                    );
                    
                    GRM_Logger::info('Scheduled background report generation', array(
                        'order_id' => $order_id,
                        'items_count' => count($items_to_process)
                    ));
                } else {
                    GRM_Logger::info('Auto-generation disabled, skipping report creation');
                }
            } else {
                GRM_Logger::info('No items to process for report generation');
            }
            
        } catch (Exception $e) {
            GRM_Logger::error('PDF creation processing failed', array(
                'order_id' => $order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    public function process_background_reports($order_id, $items_to_process) {
        GRM_Logger::info('Processing background reports', array(
            'order_id' => $order_id,
            'items_count' => count($items_to_process)
        ));
        
        try {
            $report_generator = new GRM_Report_Generator();
            
            foreach ($items_to_process as $item) {
                try {
                    $result = $report_generator->generate_report(
                        $item['upload_id'],
                        $order_id,
                        $item['product_id'],
                        $item['product_name'],
                        $item['has_subscription']
                    );
                    
                    if ($result) {
                        GRM_Logger::info('Background report generated successfully', array(
                            'upload_id' => $item['upload_id'],
                            'order_id' => $order_id,
                            'product_name' => $item['product_name']
                        ));
                    } else {
                        GRM_Logger::error('Background report generation failed', array(
                            'upload_id' => $item['upload_id'],
                            'order_id' => $order_id,
                            'product_name' => $item['product_name']
                        ));
                    }
                    
                    // Small delay between reports to avoid overwhelming the API
                    sleep(2);
                    
                } catch (Exception $e) {
                    GRM_Logger::error('Background report generation error', array(
                        'upload_id' => $item['upload_id'],
                        'order_id' => $order_id,
                        'error' => $e->getMessage()
                    ));
                }
            }
            
        } catch (Exception $e) {
            GRM_Logger::error('Background report processing failed', array(
                'order_id' => $order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    private function order_has_product($order, $product_id) {
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                return true;
            }
        }
        return false;
    }
    
    public function get_reportable_products() {
        return $this->reportable_products;
    }
    
    public function set_reportable_products($products) {
        $this->reportable_products = array_map('intval', $products);
        update_option('grm_reportable_products', $this->reportable_products);
    }
    
    public function get_subscription_product() {
        return $this->subscription_product;
    }
    
    public function set_subscription_product($product_id) {
        $this->subscription_product = intval($product_id);
        update_option('grm_subscription_product', $this->subscription_product);
    }
}