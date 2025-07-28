<?php
/**
 * API Handler Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_API_Handler {
    
    private $api_base_url;
    private $timeout;
    
    public function __construct() {
        $this->api_base_url = rtrim(get_option('grm_api_url', 'http://api.mthfrsupport.org/'), '/');
        $this->timeout = 30; // seconds
    }
    
    public function create_report($upload_id, $order_id, $product_name, $has_subscription = false) {
        try {
            $endpoint = $this->api_base_url . '/backend/api/creation';
            
            $params = array(
                'upload_id' => $upload_id,
                'order_id' => $order_id,
                'product_name' => $product_name,
                'has_subscription' => $has_subscription ? 'true' : 'false'
            );
            
            $url = add_query_arg($params, $endpoint);
            
            GRM_Logger::info('Making API request for report creation', array(
                'url' => $url,
                'params' => $params
            ));
            
            $response = wp_remote_get($url, array(
                'timeout' => $this->timeout,
                'headers' => array(
                    'User-Agent' => 'GRM-Plugin/' . GRM_VERSION
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            GRM_Logger::debug('API response received', array(
                'response_code' => $response_code,
                'response_body' => substr($response_body, 0, 500) // Log first 500 chars
            ));
            
            if ($response_code !== 200) {
                throw new Exception('API returned error code: ' . $response_code);
            }
            
            $result = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API');
            }
            
            return array(
                'success' => isset($result['success']) ? $result['success'] : true,
                'data' => $result,
                'response_code' => $response_code
            );
            
        } catch (Exception $e) {
            GRM_Logger::error('API report creation failed', array(
                'upload_id' => $upload_id,
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            );
        }
    }
    
    public function download_report($upload_id) {
        try {
            $endpoint = $this->api_base_url . '/backend/api/report';
            
            $url = add_query_arg(array('upload_id' => $upload_id), $endpoint);
            
            GRM_Logger::debug('Making API request for report download', array(
                'url' => $url,
                'upload_id' => $upload_id
            ));
            
            $response = wp_remote_get($url, array(
                'timeout' => 20,
                'headers' => array(
                    'User-Agent' => 'GRM-Plugin/' . GRM_VERSION
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                throw new Exception('API returned error code: ' . $response_code);
            }
            
            $pdf_data = wp_remote_retrieve_body($response);
            $content_disposition = wp_remote_retrieve_header($response, 'content-disposition');
            $content_length = wp_remote_retrieve_header($response, 'content-length');
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            
            // Validate PDF data
            if (empty($pdf_data) || substr($pdf_data, 0, 4) !== '%PDF') {
                throw new Exception('Invalid PDF data received from API');
            }
            
            // Extract filename from content-disposition header
            $filename = 'genetic-report.pdf';
            if ($content_disposition && preg_match('/filename="([^"]+)"/', $content_disposition, $matches)) {
                $filename = $matches[1];
            }
            
            return array(
                'success' => true,
                'data' => array(
                    'pdf_data' => base64_encode($pdf_data),
                    'file_name' => $filename,
                    'file_size' => $content_length,
                    'file_type' => $content_type
                )
            );
            
        } catch (Exception $e) {
            GRM_Logger::error('API report download failed', array(
                'upload_id' => $upload_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            );
        }
    }
    
    public function get_report_status($upload_id) {
        try {
            $endpoint = $this->api_base_url . '/backend/api/status';
            
            $url = add_query_arg(array('upload_id' => $upload_id), $endpoint);
            
            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'headers' => array(
                    'User-Agent' => 'GRM-Plugin/' . GRM_VERSION
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                throw new Exception('API returned error code: ' . $response_code);
            }
            
            $result = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API');
            }
            
            return array(
                'success' => true,
                'data' => $result
            );
            
        } catch (Exception $e) {
            GRM_Logger::error('API status check failed', array(
                'upload_id' => $upload_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            );
        }
    }
    
    public function test_connection() {
        try {
            $endpoint = $this->api_base_url . '/backend/api/health';
            
            $response = wp_remote_get($endpoint, array(
                'timeout' => 5,
                'headers' => array(
                    'User-Agent' => 'GRM-Plugin/' . GRM_VERSION
                )
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            return array(
                'success' => $response_code === 200,
                'response_code' => $response_code,
                'response_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    public function validate_upload($upload_id) {
        try {
            $endpoint = $this->api_base_url . '/backend/api/validate';
            
            $url = add_query_arg(array('upload_id' => $upload_id), $endpoint);
            
            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'headers' => array(
                    'User-Agent' => 'GRM-Plugin/' . GRM_VERSION
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                throw new Exception('API returned error code: ' . $response_code);
            }
            
            $result = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API');
            }
            
            return array(
                'success' => isset($result['valid']) ? $result['valid'] : false,
                'data' => $result
            );
            
        } catch (Exception $e) {
            GRM_Logger::error('API upload validation failed', array(
                'upload_id' => $upload_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            );
        }
    }
    
    public function set_api_url($url) {
        $this->api_base_url = rtrim($url, '/');
        update_option('grm_api_url', $this->api_base_url);
    }
    
    public function get_api_url() {
        return $this->api_base_url;
    }
    
    public function set_timeout($timeout) {
        $this->timeout = max(5, intval($timeout)); // Minimum 5 seconds
    }
    
    public function get_timeout() {
        return $this->timeout;
    }
}
            