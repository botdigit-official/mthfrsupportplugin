<?php
/**
 * Logger class for Genetic Report Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRG_Logger {
    
    private static $logs = array();
    private static $max_logs = 500; // Maximum logs to keep in database
    
    /**
     * Log a message with level and context
     */
    public static function log($level, $message, $context = null) {
        try {
            global $wpdb;
            
            $timestamp = current_time('mysql');
            $context_json = is_array($context) ? wp_json_encode($context) : $context;
            
            // Store in memory for immediate access
            self::$logs[] = array(
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'timestamp' => $timestamp
            );
            
            // Log to WordPress error log if enabled
            if (get_option('grg_enable_debug', 0)) {
                $log_entry = "[$timestamp] GRG[$level]: $message";
                if ($context) {
                    $log_entry .= " | Context: " . $context_json;
                }
                error_log($log_entry);
            }
            
            // Store in database (using existing log table if available)
            $log_table = self::get_log_table();
            if ($log_table) {
                $wpdb->insert(
                    $log_table,
                    array(
                        'level' => $level,
                        'message' => $message,
                        'context' => $context_json,
                        'created_at' => $timestamp
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                
                // Clean old logs periodically
                if (rand(1, 100) === 1) { // 1% chance
                    self::cleanup_old_logs();
                }
            }
            
        } catch (Exception $e) {
            // Fallback to WordPress error log
            error_log("GRG Logger Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get the appropriate log table name
     */
    private static function get_log_table() {
        global $wpdb;
        
        // Check for existing log tables in order of preference
        $possible_tables = array(
            'wpub_logs',
            $wpdb->prefix . 'grg_logs',
            $wpdb->prefix . 'logs'
        );
        
        foreach ($possible_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                return $table;
            }
        }
        
        // Create logs table if none exists
        return self::create_log_table();
    }
    
    /**
     * Create logs table
     */
    private static function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'grg_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return $table_name;
    }
    
    /**
     * Get recent logs
     */
    public static function get_logs($limit = 100, $level = null) {
        global $wpdb;
        
        $log_table = self::get_log_table();
        if (!$log_table) {
            return array();
        }
        
        $where_clause = '';
        $params = array($limit);
        
        if ($level) {
            $where_clause = 'WHERE level = %s';
            array_unshift($params, $level);
        }
        
        $query = "SELECT * FROM $log_table $where_clause ORDER BY created_at DESC LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Get logs for specific context
     */
    public static function get_logs_by_context($context_key, $context_value, $limit = 50) {
        global $wpdb;
        
        $log_table = self::get_log_table();
        if (!$log_table) {
            return array();
        }
        
        $query = "SELECT * FROM $log_table 
                  WHERE context LIKE %s 
                  ORDER BY created_at DESC 
                  LIMIT %d";
        
        $search_pattern = '%"' . $context_key . '":' . (is_numeric($context_value) ? $context_value : '"' . $context_value . '"') . '%';
        
        return $wpdb->get_results($wpdb->prepare($query, $search_pattern, $limit));
    }
    
    /**
     * Clear all logs
     */
    public static function clear_logs() {
        global $wpdb;
        
        $log_table = self::get_log_table();
        if ($log_table) {
            $wpdb->query("TRUNCATE TABLE $log_table");
        }
        
        self::$logs = array();
        self::log('info', 'Logs cleared by admin');
    }
    
    /**
     * Cleanup old logs
     */
    private static function cleanup_old_logs() {
        global $wpdb;
        
        $log_table = self::get_log_table();
        if (!$log_table) {
            return;
        }
        
        // Keep only the most recent logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $log_table 
             WHERE id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM $log_table 
                     ORDER BY created_at DESC 
                     LIMIT %d
                 ) temp
             )",
            self::$max_logs
        ));
    }
    
    /**
     * Get log statistics
     */
    public static function get_log_stats() {
        global $wpdb;
        
        $log_table = self::get_log_table();
        if (!$log_table) {
            return array();
        }
        
        $stats = $wpdb->get_results("
            SELECT 
                level,
                COUNT(*) as count,
                MAX(created_at) as latest
            FROM $log_table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY level
            ORDER BY count DESC
        ");
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        return array(
            'total_24h' => $total,
            'by_level' => $stats
        );
    }
    
    /**
     * Log API request/response
     */
    public static function log_api_call($url, $request_data, $response, $response_code, $execution_time = null) {
        $context = array(
            'url' => $url,
            'request_data' => $request_data,
            'response_code' => $response_code,
            'response_preview' => substr($response, 0, 500),
            'response_length' => strlen($response),
            'execution_time' => $execution_time
        );
        
        $level = ($response_code >= 200 && $response_code < 300) ? 'info' : 'error';
        $message = "API Call: $url | Response Code: $response_code";
        
        if ($execution_time) {
            $message .= " | Time: {$execution_time}s";
        }
        
        self::log($level, $message, $context);
    }
    
    /**
     * Log report generation process
     */
    public static function log_report_generation($upload_id, $order_id, $step, $status, $details = null) {
        $context = array(
            'upload_id' => $upload_id,
            'order_id' => $order_id,
            'step' => $step,
            'details' => $details
        );
        
        $level = ($status === 'success') ? 'info' : (($status === 'error') ? 'error' : 'warning');
        $message = "Report Generation [$step]: Upload $upload_id, Order $order_id - $status";
        
        self::log($level, $message, $context);
    }
    
    /**
     * Get memory for debugging
     */
    public static function get_memory_logs() {
        return self::$logs;
    }
    
    /**
     * Export logs as CSV
     */
    public static function export_logs_csv($limit = 1000) {
        $logs = self::get_logs($limit);
        
        if (empty($logs)) {
            return false;
        }
        
        $csv_content = "Timestamp,Level,Message,Context\n";
        
        foreach ($logs as $log) {
            $context = $log->context ? str_replace('"', '""', $log->context) : '';
            $message = str_replace('"', '""', $log->message);
            
            $csv_content .= sprintf(
                '"%s","%s","%s","%s"' . "\n",
                $log->created_at,
                $log->level,
                $message,
                $context
            );
        }
        
        return $csv_content;
    }
    
    /**
     * Log database operation
     */
    public static function log_database_operation($operation, $table, $data, $result) {
        $context = array(
            'operation' => $operation,
            'table' => $table,
            'data_keys' => is_array($data) ? array_keys($data) : null,
            'result' => $result,
            'affected_rows' => is_numeric($result) ? $result : null
        );
        
        $level = $result ? 'info' : 'error';
        $message = "Database $operation on $table: " . ($result ? 'Success' : 'Failed');
        
        self::log($level, $message, $context);
    }
    
    /**
     * Log user action
     */
    public static function log_user_action($action, $user_id = null, $details = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        $username = $user ? $user->user_login : 'Unknown';
        
        $context = array(
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'details' => $details,
            'ip_address' => self::get_client_ip()
        );
        
        self::log('info', "User Action: $username performed $action", $context);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}