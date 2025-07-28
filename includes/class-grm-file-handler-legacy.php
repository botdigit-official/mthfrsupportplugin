<?php
/**
 * File Handler Class
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
    
    public function __construct() {
        $this->allowed_types = get_option('grm_allowed_file_types', array('zip'));
        $this->max_file_size = get_option('grm_upload_max_size', 50) * 1024 * 1024;
    }
    
    public function handle_upload($uploaded_file, $user_id) {
        try {
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
                throw new Exception(__('Failed to move uploaded file', GRM_TEXT_DOMAIN));
            }
            
            // Create database record
            $upload_id = GRM_Database::create_upload($user_id, $unique_filename, $target_file, $source_type);
            
            GRM_Logger::info('File uploaded successfully', array(
                'upload_id' => $upload_id,
                'user_id' => $user_id,
                'filename' => $unique_filename,
                'source_type' => $source_type
            ));
            
            return array(
                'upload_id' => $upload_id,
                'folder_name' => $unique_filename,
                'source_type' => $source_type,
                'created_at' => current_time('Y-m-d')
            );
            
        } catch (Exception $e) {
            GRM_Logger::error('File upload failed: ' . $e->getMessage(), array(
                'user_id' => $user_id,
                'filename' => $uploaded_file['name'] ?? 'unknown'
            ));
            throw $e;
        }
    }
    
    private function validate_file($uploaded_file) {
        // Check for upload errors
        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->get_upload_error_message($uploaded_file['error']));
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
                $this->max_file_size / 1024 / 1024
            ));
        }
        
        // Check MIME type
        $allowed_mimes = array('application/zip', 'application/x-zip-compressed');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $uploaded_file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_mimes)) {
            throw new Exception(__('Invalid file format detected', GRM_TEXT_DOMAIN));
        }
    }
    
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
    
    private function get_user_upload_dir($user_id) {
        $upload_dir = wp_upload_dir();
        $user_uploads_dir = $upload_dir['basedir'] . '/user_uploads/' . $user_id;
        
        if (!file_exists($user_uploads_dir)) {
            if (!wp_mkdir_p($user_uploads_dir)) {
                throw new Exception(__('Failed to create upload directory', GRM_TEXT_DOMAIN));
            }
        }
        
        return $user_uploads_dir;
    }
    
    private function generate_unique_filename($original_name, $upload_dir) {
        $filename = sanitize_file_name($original_name);
        $target_file = $upload_dir . '/' . $filename;
        
        if (!file_exists($target_file)) {
            return $filename;
        }
        
        $path_info = pathinfo($filename);
        $counter = 1;
        
        do {
            $new_filename = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
            $target_file = $upload_dir . '/' . $new_filename;
            $counter++;
        } while (file_exists($target_file));
        
        return $new_filename;
    }
    
    private function analyze_file_content($file_path) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($file_path) !== TRUE) {
                throw new Exception(__('Failed to open ZIP file', GRM_TEXT_DOMAIN));
            }
            
            // Find the .txt file inside the ZIP
            $txt_file_content = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $file_name = $zip->getNameIndex($i);
                
                if (pathinfo($file_name, PATHINFO_EXTENSION) === 'txt') {
                    $txt_file_content = $zip->getFromName($file_name);
                    break;
                }
            }
            
            $zip->close();
            
            if (!$txt_file_content) {
                throw new Exception(__('No .txt file found inside the ZIP', GRM_TEXT_DOMAIN));
            }
            
            return $this->determine_source_type($txt_file_content);
            
        } catch (Exception $e) {
            GRM_Logger::error('File analysis failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function determine_source_type($file_contents) {
        $lines = preg_split('/\r\n|\r|\n/', $file_contents);
        $cleaned_lines = array();
        
        foreach ($lines as $line) {
            // Replace commas with tabs and remove quotes
            $new_line = str_replace(',', "\t", $line);
            $new_line = str_replace('"', '', $new_line);
            $cleaned_lines[] = $new_line;
        }
        
        $source_type = null;
        $header_found = false;
        $valid_snp_count = 0;
        
        foreach ($cleaned_lines as $i => $line) {
            $line = trim($line);
            if ($line === '') continue;
            
            // MyHeritage: RSID CHROMOSOME POSITION RESULT
            if (preg_match('/^RSID\s+CHROMOSOME\s+POSITION\s+RESULT$/i', $line)) {
                $source_type = 'myheritage';
                $header_found = true;
                continue;
            }
            
            // Ancestry: rsid chromosome position allele1 allele2
            if (preg_match('/^rsid\s+chromosome\s+position\s+allele1\s+allele2$/i', $line)) {
                $source_type = 'ancestry';
                $header_found = true;
                continue;
            }
            
            // 23andMe: # rsid chromosome position genotype
            if (preg_match('/^#\s*rsid\s+chromosome\s+position\s+genotype$/i', $line)) {
                $source_type = '23andme';
                $header_found = true;
                continue;
            }
            
            // Skip before header
            if (!$header_found) continue;
            
            // Check for valid SNP data lines
            $fields = preg_split('/\t|,/', $line);
            
            if ($source_type === 'myheritage' && count($fields) === 4 && preg_match('/^rs\d+$/i', $fields[0])) {
                $valid_snp_count++;
            }
            
            if ($source_type === 'ancestry' && count($fields) === 5 && preg_match('/^rs\d+$/i', $fields[0])) {
                $valid_snp_count++;
            }
            
            if ($source_type === '23andme' && count($fields) === 4 && preg_match('/^rs\d+$/i', $fields[0])) {
                $valid_snp_count++;
            }
            
            if ($valid_snp_count >= 3) break;
        }
        
        if (!$header_found || !$source_type || $valid_snp_count < 3) {
            throw new Exception(__('Invalid or unsupported raw DNA file format', GRM_TEXT_DOMAIN));
        }
        
        return $source_type;
    }
    
    public function cleanup_temp_files($days = 7) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/grm-temp';
        
        if (!file_exists($temp_dir)) {
            return 0;
        }
        
        $deleted_count = 0;
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        GRM_Logger::info("Cleaned up $deleted_count temporary files");
        return $deleted_count;
    }
    
    public function get_file_info($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        return array(
            'size' => filesize($file_path),
            'modified' => filemtime($file_path),
            'readable' => is_readable($file_path),
            'writable' => is_writable($file_path)
        );
    }
}