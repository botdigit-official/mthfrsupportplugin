<?php
/**
 * Database Handler Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_Database {
    
    private static $uploads_table = null;
    private static $reports_table = null;
    
    public static function init() {
        global $wpdb;
        self::$uploads_table = $wpdb->prefix . 'user_uploads';
        self::$reports_table = $wpdb->prefix . 'user_reports';
    }
    
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User uploads table
        $uploads_table_sql = "CREATE TABLE " . $wpdb->prefix . "user_uploads (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id varchar(50) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path text NOT NULL,
            source_type varchar(50) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source_type (source_type)
        ) $charset_collate;";
        
        // User reports table
        $reports_table_sql = "CREATE TABLE " . $wpdb->prefix . "user_reports (
            id int(11) NOT NULL AUTO_INCREMENT,
            upload_id int(11) NOT NULL,
            order_id int(11) DEFAULT NULL,
            report_name varchar(255) DEFAULT NULL,
            report_type varchar(100) DEFAULT NULL,
            report_path text DEFAULT NULL,
            pdf_data longtext DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY upload_id (upload_id),
            KEY order_id (order_id),
            KEY status (status),
            FOREIGN KEY (upload_id) REFERENCES " . $wpdb->prefix . "user_uploads(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($uploads_table_sql);
        dbDelta($reports_table_sql);
        
        // Check if tables were created successfully
        if (!self::verify_tables()) {
            throw new Exception('Failed to create required database tables');
        }
        
        GRM_Logger::log('info', 'Database tables created successfully');
    }
    
    public static function verify_tables() {
        global $wpdb;
        
        $uploads_exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "user_uploads'");
        $reports_exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "user_reports'");
        
        return ($uploads_exists && $reports_exists);
    }
    
    // Upload methods
    public static function create_upload($user_id, $file_name, $file_path, $source_type = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            self::$uploads_table,
            array(
                'user_id' => $user_id,
                'file_name' => $file_name,
                'file_path' => $file_path,
                'source_type' => $source_type
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            throw new Exception('Failed to create upload record: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    public static function get_upload($upload_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$uploads_table . " WHERE id = %d",
                $upload_id
            )
        );
    }
    
    public static function get_user_uploads($user_id, $limit = null, $offset = 0) {
        global $wpdb;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::$uploads_table . " WHERE user_id = %s ORDER BY created_at DESC",
            $user_id
        );
        
        if ($limit) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        return $wpdb->get_results($sql);
    }
    
    public static function delete_upload($upload_id) {
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get upload data first
            $upload = self::get_upload($upload_id);
            if (!$upload) {
                throw new Exception('Upload not found');
            }
            
            // Delete associated reports
            $reports = self::get_reports_by_upload($upload_id);
            foreach ($reports as $report) {
                if (!empty($report->report_path) && file_exists($report->report_path)) {
                    unlink($report->report_path);
                }
            }
            
            // Delete reports from database
            $wpdb->delete(
                self::$reports_table,
                array('upload_id' => $upload_id),
                array('%d')
            );
            
            // Delete upload file
            if (file_exists($upload->file_path)) {
                unlink($upload->file_path);
            }
            
            // Delete upload record
            $result = $wpdb->delete(
                self::$uploads_table,
                array('id' => $upload_id),
                array('%d')
            );
            
            if ($result === false) {
                throw new Exception('Failed to delete upload record');
            }
            
            $wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    // Report methods
    public static function create_report($upload_id, $order_id = null, $report_name = null, $report_type = null) {
        global $wpdb;
        
        $result = $wpdb->insert(
            self::$reports_table,
            array(
                'upload_id' => $upload_id,
                'order_id' => $order_id,
                'report_name' => $report_name,
                'report_type' => $report_type,
                'status' => 'pending'
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            throw new Exception('Failed to create report record: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    public static function update_report($report_id, $data) {
        global $wpdb;
        
        $allowed_fields = array(
            'report_path', 'pdf_data', 'status', 'error_message', 
            'report_name', 'report_type', 'updated_at'
        );
        
        $update_data = array();
        $update_format = array();
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_data[$field] = $value;
                $update_format[] = '%s';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            self::$reports_table,
            $update_data,
            array('id' => $report_id),
            $update_format,
            array('%d')
        );
        
        return $result !== false;
    }
    
    public static function get_report($report_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$reports_table . " WHERE id = %d",
                $report_id
            )
        );
    }
    
    public static function get_report_by_upload($upload_id, $order_id = null) {
        global $wpdb;
        
        $sql = "SELECT * FROM " . self::$reports_table . " WHERE upload_id = %d";
        $params = array($upload_id);
        
        if ($order_id) {
            $sql .= " AND order_id = %d";
            $params[] = $order_id;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 1";
        
        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }
    
    public static function get_reports_by_upload($upload_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$reports_table . " WHERE upload_id = %d ORDER BY created_at DESC",
                $upload_id
            )
        );
    }
    
    public static function get_reports_by_order($order_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, u.file_name as upload_file_name 
                 FROM " . self::$reports_table . " r
                 LEFT JOIN " . self::$uploads_table . " u ON r.upload_id = u.id
                 WHERE r.order_id = %d 
                 ORDER BY r.created_at DESC",
                $order_id
            )
        );
    }
    
    public static function get_user_reports($user_id, $status = null) {
        global $wpdb;
        
        $sql = "SELECT r.*, u.file_name as upload_file_name 
                FROM " . self::$reports_table . " r
                INNER JOIN " . self::$uploads_table . " u ON r.upload_id = u.id
                WHERE u.user_id = %s";
        
        $params = array($user_id);
        
        if ($status) {
            $sql .= " AND r.status = %s";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.updated_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Fetch the most recent reports.
     *
     * @param int $limit Number of reports to return.
     * @return array
     */
    public static function get_recent_reports($limit = 10) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, u.file_name as upload_file_name
                 FROM " . self::$reports_table . " r
                 LEFT JOIN " . self::$uploads_table . " u ON r.upload_id = u.id
                 ORDER BY r.created_at DESC
                 LIMIT %d",
                $limit
            )
        );
    }
    
    public static function get_pending_reports($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$reports_table . " 
                 WHERE status = 'pending' OR status = 'processing'
                 ORDER BY created_at ASC 
                 LIMIT %d",
                $limit
            )
        );
    }
    
    public static function cleanup_old_reports($days = 30) {
        global $wpdb;
        
        $old_reports = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$reports_table . " 
                 WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        
        $deleted_count = 0;
        foreach ($old_reports as $report) {
            if (!empty($report->report_path) && file_exists($report->report_path)) {
                unlink($report->report_path);
            }
            
            $wpdb->delete(
                self::$reports_table,
                array('id' => $report->id),
                array('%d')
            );
            
            $deleted_count++;
        }
        
        GRM_Logger::log('info', "Cleaned up $deleted_count old failed reports");
        return $deleted_count;
    }
}
