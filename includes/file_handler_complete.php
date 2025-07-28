<?php
/**
 * File Handler Class
 * 
 * Handles file uploads, validation, and processing for genetic data files
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_File_Handler {
    
    private $allowed_types = array('zip');
    private $max_file_size = 52428800; // 50MB in bytes
    private $upload_errors = array();
    
    public function __construct() {
        $this->allowed_types = get_option('grm_allowed_file_types', array('zip'));
        $this->max_file_size = get_option('grm_upload_max_size', 50) * 1024 * 1024;
    }
    
    /**
     * Handle file upload process
     * 
     * @param array $uploaded_file $_FILES array element
     * @param string|int $user_id User ID or 'guest'
     * @return array Upload result with success/error data
     * @throws Exception
     */
    public function handle_upload($uploaded_file, $user_id) {
        try {
            GRM_Logger::info('Starting file upload process', array(
                'user_id' => $user_id,
                'filename' => $uploaded_file['name'] ?? 'unknown',
                'size' => $uploaded_file['size'] ?? 0
            ));
            
            // Validate file
            $this->validate_file($uploaded_file);
            
            // Create upload directory
            $upload_dir = $this->get_user_upload_dir($user_id);
            
            // Generate unique filename
            $unique_filename = $this->generate_unique_filename($uploaded_file['name'], $upload_dir);
            $target_file = $upload_dir . '/' . $unique_filename;
            
            // Analyze file content to determine source type
            $source_type = $this->analyze_file_content($uploaded_file['tmp_name']);
            
            // Move uploaded file
            if (!move_uploaded_file($uploaded_file['tmp_name'], $target_file)) {
                throw new Exception(__('Failed to move uploaded file to destination', GRM_TEXT_DOMAIN));
            }
            
            // Set proper file permissions
            chmod($target_file, 0644);
            
            // Create database record
            $upload_id = GRM_Database::create_upload($user_id, $unique_filename, $target_file, $source_type);
            
            GRM_Logger::info('File uploaded successfully', array(
                'upload_id' => $upload_id,
                'user_id' => $user_id,
                'filename' => $unique_filename,
                'source_type' => $source_type,
                'file_size' => filesize($target_file)
            ));
            
            return array(
                'success' => true,
                'upload_id' => $upload_id,
                'folder_name' => $unique_filename,
                'source_type' => $source_type,
                'created_at' => current_time('Y-m-d'),
                'file_size' => filesize($target_file)
            );
            
        } catch (Exception $e) {
            GRM_Logger::error('File upload failed', array(
                'user_id' => $user_id,
                'filename' => $uploaded_file['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'error_code' => $uploaded_file['error'] ?? 'unknown'
            ));
            
            // Clean up any partially uploaded file
            if (isset($target_file) && file_exists($target_file)) {
                unlink($target_file);
            }
            
            throw $e;
        }
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $uploaded_file $_FILES array element
     * @throws Exception
     */
    private function validate_file($uploaded_file) {
        // Check for upload errors
        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->get_upload_error_message($uploaded_file['error']));
        }
        
        // Check if file was actually uploaded
        if (!is_uploaded_file($uploaded_file['tmp_name'])) {
            throw new Exception(__('File was not uploaded properly', GRM_TEXT_DOMAIN));
        }
        
        // Check file extension
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            throw new Exception(sprintf(
                __('Invalid file type. Allowed types: %s', GRM_TEXT_DOMAIN),
                implode(', ', $this->allowed_types)
            ));
        }
        
        // Check file size
        if ($uploaded_file['size'] > $this->max_file_size) {
            throw new Exception(sprintf(
                __('File too large. Maximum size: %s MB', GRM_TEXT_DOMAIN),
                round($this->max_file_size / 1024 / 1024, 1)
            ));
        }
        
        // Check for empty file
        if ($uploaded_file['size'] == 0) {
            throw new Exception(__('Uploaded file is empty', GRM_TEXT_DOMAIN));
        }
        
        // Check MIME type
        $this->validate_mime_type($uploaded_file['tmp_name']);
        
        // Additional security checks
        $this->security_scan($uploaded_file);
    }
    
    /**
     * Validate MIME type
     * 
     * @param string $file_path Temporary file path
     * @throws Exception
     */
    private function validate_mime_type($file_path) {
        $allowed_mimes = array(
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
            'multipart/x-zip'
        );
        
        // Check with finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_mimes)) {
                throw new Exception(sprintf(
                    __('Invalid file format detected: %s', GRM_TEXT_DOMAIN),
                    $mime_type
                ));
            }
        }
        
        // Additional check with file command if available
        if (function_exists('exec') && !empty(shell_exec('which file'))) {
            $output = shell_exec('file -b --mime-type ' . escapeshellarg($file_path));
            $mime_type = trim($output);
            
            if (!in_array($mime_type, $allowed_mimes)) {
                GRM_Logger::warning('MIME type mismatch detected', array(
                    'detected_mime' => $mime_type,
                    'allowed_mimes' => $allowed_mimes
                ));
            }
        }
    }
    
    /**
     * Perform security scans on uploaded file
     * 
     * @param array $uploaded_file
     * @throws Exception
     */
    private function security_scan($uploaded_file) {
        $file_path = $uploaded_file['tmp_name'];
        
        // Check for suspicious file patterns
        $suspicious_patterns = array(
            '<?php',
            '<%',
            '<script',
            'eval(',
            'base64_decode',
            'file_get_contents',
            'file_put_contents',
            'fopen',
            'fwrite'
        );
        
        // Read first 1KB for pattern matching
        $file_content = file_get_contents($file_path, false, null, 0, 1024);
        
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($file_content, $pattern) !== false) {
                throw new Exception(__('Suspicious content detected in uploaded file', GRM_TEXT_DOMAIN));
            }
        }
        
        // Check file signature (magic bytes)
        $file_header = bin2hex(substr($file_content, 0, 4));
        $zip_signatures = array('504b0304', '504b0506', '504b0708');
        
        if (!in_array($file_header, $zip_signatures)) {
            throw new Exception(__('File signature does not match ZIP format', GRM_TEXT_DOMAIN));
        }
    }
    
    /**
     * Get upload error message
     * 
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('File is too large', GRM_TEXT_DOMAIN);
            case UPLOAD_ERR_PARTIAL:
                return __('File was only partially uploaded', GRM_TEXT_DOMAIN);
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', GRM_TEXT_DOMAIN);
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing temporary directory', GRM_TEXT_DOMAIN);
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', GRM_TEXT_DOMAIN);
            case UPLOAD_ERR_EXTENSION:
                return __('Upload stopped by extension', GRM_TEXT_DOMAIN);
            default:
                return __('Unknown upload error', GRM_TEXT_DOMAIN);
        }
    }
    
    /**
     * Get user upload directory
     * 
     * @param string|int $user_id
     * @return string Directory path
     * @throws Exception
     */
    private function get_user_upload_dir($user_id) {
        $upload_dir = wp_upload_dir();
        $user_uploads_dir = $upload_dir['basedir'] . '/user_uploads/' . $user_id;
        
        if (!file_exists($user_uploads_dir)) {
            if (!wp_mkdir_p($user_uploads_dir)) {
                throw new Exception(__('Failed to create upload directory', GRM_TEXT_DOMAIN));
            }
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "<Files ~ \"\\.(zip)$\">\n";
            $htaccess_content .= "allow from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($user_uploads_dir . '/.htaccess', $htaccess_content);
            
            // Create index.php for security
            file_put_contents($user_uploads_dir . '/index.php', '<?php // Silence is golden');
        }
        
        return $user_uploads_dir;
    }
    
    /**
     * Generate unique filename
     * 
     * @param string $original_name
     * @param string $upload_dir
     * @return string Unique filename
     */
    private function generate_unique_filename($original_name, $upload_dir) {
        // Sanitize filename
        $filename = sanitize_file_name($original_name);
        
        // Remove any remaining special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        $target_file = $upload_dir . '/' . $filename;
        
        if (!file_exists($target_file)) {
            return $filename;
        }
        
        $path_info = pathinfo($filename);
        $name = $path_info['filename'];
        $ext = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
        $counter = 1;
        
        do {
            $new_filename = $name . '_' . $counter . $ext;
            $target_file = $upload_dir . '/' . $new_filename;
            $counter++;
        } while (file_exists($target_file) && $counter < 1000);
        
        if ($counter >= 1000) {
            throw new Exception(__('Unable to generate unique filename', GRM_TEXT_DOMAIN));
        }
        
        return $new_filename;
    }
    
    /**
     * Analyze file content to determine source type
     * 
     * @param string $file_path Temporary file path
     * @return string Source type (23andme, ancestry, myheritage)
     * @throws Exception
     */
    private function analyze_file_content($file_path) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== TRUE) {
                throw new Exception(__('Failed to open ZIP file for analysis', GRM_TEXT_DOMAIN));
            }
            
            // Find the .txt file inside the ZIP
            $txt_file_content = null;
            $txt_file_name = null;
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $file_name = $zip->getNameIndex($i);
                
                if (pathinfo($file_name, PATHINFO_EXTENSION) === 'txt') {
                    $txt_file_content = $zip->getFromName($file_name);
                    $txt_file_name = $file_name;
                    break;
                }
            }
            
            $zip->close();
            
            if (!$txt_file_content) {
                throw new Exception(__('No .txt file found inside the ZIP archive', GRM_TEXT_DOMAIN));
            }
            
            GRM_Logger::debug('Analyzing DNA file content', array(
                'txt_file_name' => $txt_file_name,
                'content_length' => strlen($txt_file_content)
            ));
            
            return $this->determine_source_type($txt_file_content);
            
        } catch (Exception $e) {
            GRM_Logger::error('File analysis failed', array(
                'file_path' => $file_path,
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }
    
    /**
     * Determine source type from file content
     * 
     * @param string $file_contents
     * @return string Source type
     * @throws Exception
     */
    private function determine_source_type($file_contents) {
        $lines = preg_split('/\r\n|\r|\n/', $file_contents);
        $cleaned_lines = array();
        
        // Clean up lines
        foreach ($lines as $line) {
            // Replace commas with tabs and remove quotes
            $new_line = str_replace(',', "\t", $line);
            $new_line = str_replace('"', '', $new_line);
            $cleaned_lines[] = trim($new_line);
        }
        
        $source_type = null;
        $header_found = false;
        $valid_snp_count = 0;
        $total_lines_checked = 0;
        
        foreach ($cleaned_lines as $i => $line) {
            if (empty($line) || $line[0] === '#' && !$header_found) {
                continue; // Skip empty lines and comments before header
            }
            
            $total_lines_checked++;
            
            // Check for different source type headers
            if (!$header_found) {
                // MyHeritage: RSID CHROMOSOME POSITION RESULT
                if (preg_match('/^RSID\s+CHROMOSOME\s+POSITION\s+RESULT$/i', $line)) {
                    $source_type = 'myheritage';
                    $header_found = true;
                    GRM_Logger::debug('Detected MyHeritage format');
                    continue;
                }
                
                // Ancestry: rsid chromosome position allele1 allele2
                if (preg_match('/^rsid\s+chromosome\s+position\s+allele1\s+allele2$/i', $line)) {
                    $source_type = 'ancestry';
                    $header_found = true;
                    GRM_Logger::debug('Detected Ancestry format');
                    continue;
                }
                
                // 23andMe: # rsid chromosome position genotype
                if (preg_match('/^#\s*rsid\s+chromosome\s+position\s+genotype$/i', $line)) {
                    $source_type = '23andme';
                    $header_found = true;
                    GRM_Logger::debug('Detected 23andMe format');
                    continue;
                }
                
                // If we've checked many lines without finding a header, it's likely invalid
                if ($total_lines_checked > 50) {
                    break;
                }
                continue;
            }
            
            // Validate data lines after header is found
            $fields = preg_split('/\s+/', $line);
            
            // Check for valid SNP data based on source type
            if ($source_type === 'myheritage' && count($fields) >= 4 && preg_match('/^rs\d+$/i', $fields[0])) {
                $valid_snp_count++;
            } elseif ($source_type === 'ancestry' && count($fields) >= 5 && preg_match('/^rs\d+$/i', $fields[0])) {
                $valid_snp_count++;
            } elseif ($source_type === '23andme' && count($fields) >= 4 && preg_match('/^rs\d+$/i', $fields[0])) {
                $valid_snp_count++;
            }
            
            // Stop checking after finding enough valid SNPs or checking enough lines
            if ($valid_snp_count >= 5 || $total_lines_checked > 100) {
                break;
            }
        }
        
        GRM_Logger::debug('File analysis results', array(
            'source_type' => $source_type,
            'header_found' => $header_found,
            'valid_snp_count' => $valid_snp_count,
            'total_lines_checked' => $total_lines_checked
        ));
        
        if (!$header_found || !$source_type) {
            throw new Exception(__('Could not identify DNA file format. Please ensure this is a valid raw DNA data file from 23andMe, AncestryDNA, or MyHeritage.', GRM_TEXT_DOMAIN));
        }
        
        if ($valid_snp_count < 3) {
            throw new Exception(sprintf(
                __('File appears to be %s format but contains insufficient valid SNP data (%d SNPs found, minimum 3 required).', GRM_TEXT_DOMAIN),
                $source_type,
                $valid_snp_count
            ));
        }
        
        return $source_type;
    }
    
    /**
     * Delete uploaded file and cleanup
     * 
     * @param int $upload_id
     * @return bool Success status
     */
    public function delete_upload_file($upload_id) {
        try {
            $upload = GRM_Database::get_upload($upload_id);
            if (!$upload) {
                throw new Exception('Upload record not found');
            }
            
            // Delete physical file
            if (file_exists($upload->file_path)) {
                if (!unlink($upload->file_path)) {
                    throw new Exception('Failed to delete physical file');
                }
            }
            
            // Clean up empty directories
            $dir = dirname($upload->file_path);
            if (is_dir($dir) && count(scandir($dir)) == 2) { // Only . and ..
                rmdir($dir);
            }
            
            GRM_Logger::info('Upload file deleted successfully', array(
                'upload_id' => $upload_id,
                'file_path' => $upload->file_path
            ));
            
            return true;
            
        } catch (Exception $e) {
            GRM_Logger::error('Failed to delete upload file', array(
                'upload_id' => $upload_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Cleanup temporary files older than specified days
     * 
     * @param int $days Age in days
     * @return int Number of files cleaned up
     */
    public function cleanup_temp_files($days = 7) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/grm-temp';
        
        if (!file_exists($temp_dir)) {
            return 0;
        }
        
        $deleted_count = 0;
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                if ($file->isFile() && $file->getMTime() < $cutoff_time) {
                    if (unlink($file->getPathname())) {
                        $deleted_count++;
                    }
                }
            }
            
            GRM_Logger::info("Cleaned up $deleted_count temporary files older than $days days");
            
        } catch (Exception $e) {
            GRM_Logger::error('Temp file cleanup failed', array(
                'temp_dir' => $temp_dir,
                'error' => $e->getMessage()
            ));
        }
        
        return $deleted_count;
    }
    
    /**
     * Get file information
     * 
     * @param string $file_path
     * @return array|false File information or false if file doesn't exist
     */
    public function get_file_info($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        return array(
            'size' => filesize($file_path),
            'size_formatted' => size_format(filesize($file_path)),
            'modified' => filemtime($file_path),
            'modified_formatted' => date('Y-m-d H:i:s', filemtime($file_path)),
            'readable' => is_readable($file_path),
            'writable' => is_writable($file_path),
            'mime_type' => function_exists('mime_content_type') ? mime_content_type($file_path) : 'unknown'
        );
    }
    
    /**
     * Validate file integrity
     * 
     * @param string $file_path
     * @return bool True if file is valid
     */
    public function validate_file_integrity($file_path) {
        try {
            if (!file_exists($file_path)) {
                return false;
            }
            
            // Test ZIP file integrity
            $zip = new ZipArchive();
            $result = $zip->open($file_path, ZipArchive::CHECKCONS);
            
            if ($result !== TRUE) {
                GRM_Logger::warning('ZIP file integrity check failed', array(
                    'file_path' => $file_path,
                    'zip_error' => $result
                ));
                return false;
            }
            
            $zip->close();
            return true;
            
        } catch (Exception $e) {
            GRM_Logger::error('File integrity validation failed', array(
                'file_path' => $file_path,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get upload statistics
     * 
     * @return array Statistics
     */
    public function get_upload_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total uploads
        $stats['total_uploads'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_uploads"
        );
        
        // Uploads by source type
        $source_types = $wpdb->get_results(
            "SELECT source_type, COUNT(*) as count 
             FROM {$wpdb->prefix}user_uploads 
             GROUP BY source_type"
        );
        
        $stats['by_source_type'] = array();
        foreach ($source_types as $type) {
            $stats['by_source_type'][$type->source_type] = $type->count;
        }
        
        // Recent uploads (last 30 days)
        $stats['recent_uploads'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_uploads 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Storage usage
        $upload_dir = wp_upload_dir();
        $user_uploads_dir = $upload_dir['basedir'] . '/user_uploads';
        
        if (is_dir($user_uploads_dir)) {
            $stats['storage_used'] = $this->get_directory_size($user_uploads_dir);
            $stats['storage_used_formatted'] = size_format($stats['storage_used']);
        } else {
            $stats['storage_used'] = 0;
            $stats['storage_used_formatted'] = '0 B';
        }
        
        return $stats;
    }
    
    /**
     * Calculate directory size recursively
     * 
     * @param string $directory
     * @return int Size in bytes
     */
    private function get_directory_size($directory) {
        $size = 0;
        
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            GRM_Logger::error('Failed to calculate directory size', array(
                'directory' => $directory,
                'error' => $e->getMessage()
            ));
        }
        
        return $size;
    }
}