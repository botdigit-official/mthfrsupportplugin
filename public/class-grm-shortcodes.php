<?php
/**
 * Shortcodes Handler Class
 * 
 * @package GeneticReportManager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_Shortcodes {
    
    public function __construct() {
        $this->init_shortcodes();
    }
    
    private function init_shortcodes() {
        // Report visualization shortcodes
        add_shortcode('grm_report_visualization', array($this, 'report_visualization_shortcode'));
        
        // Table shortcodes
        add_shortcode('grm_user_uploads_table', array($this, 'user_uploads_table_shortcode'));
        add_shortcode('grm_user_orders_table', array($this, 'user_orders_table_shortcode'));
        add_shortcode('grm_user_results_table', array($this, 'user_results_table_shortcode'));
        
        // Download button shortcode
        add_shortcode('grm_user_result_download_button', array($this, 'download_button_shortcode'));
        
        // WooCommerce integration shortcodes
        add_shortcode('grm_add_to_cart_button', array($this, 'add_to_cart_button_shortcode'));
        add_shortcode('grm_product_price', array($this, 'product_price_shortcode'));
        add_shortcode('grm_product_name', array($this, 'product_name_shortcode'));
        
        // Utility shortcodes
        add_shortcode('grm_file_name_display', array($this, 'file_name_display_shortcode'));
        add_shortcode('grm_all_orders', array($this, 'all_orders_shortcode'));
    }
    
    public function report_visualization_shortcode($atts) {
        global $wpdb;
        
        $atts = shortcode_atts(array(
            'result_id' => 0,
            'file_name' => '',
            'folder_name' => ''
        ), $atts);
        
        $result_id = intval($atts['result_id']);
        $file_name = sanitize_text_field($atts['file_name']);
        $folder_name = sanitize_text_field($atts['folder_name']);
        
        if (!$result_id) {
            return '<div class="error">' . __('Invalid result ID', GRM_TEXT_DOMAIN) . '</div>';
        }
        
        // Get report info
        $report = GRM_Database::get_report($result_id);
        if (!$report) {
            return '<div class="error">' . __('Report not found', GRM_TEXT_DOMAIN) . '</div>';
        }
        
        // Get report configurations
        $report_configs = array(
            'Excipient' => array(
                'enable_tags' => false,
                'enable_pathway' => false,
                'default_pathway' => null
            ),
            'Covid' => array(
                'enable_tags' => false,
                'enable_pathway' => false,
                'default_pathway' => null
            ),
            'Variant' => array(
                'enable_tags' => true,
                'enable_pathway' => true,
                'default_pathway' => 'Liver_detox'
            ),
            'Methylation' => array(
                'enable_tags' => true,
                'enable_pathway' => false,
                'default_pathway' => 'Methylation'
            )
        );
        
        $config = $report_configs[$report->report_type] ?? $report_configs['Excipient'];
        
        ob_start();
        include GRM_PLUGIN_DIR . 'templates/report-visualization.php';
        return ob_get_clean();
    }
    
    public function user_uploads_table_shortcode($atts) {
        ob_start();
        ?>
        <table id="grm-view-files" class="display">
            <thead>
                <tr>
                    <th data-sort="file_name"><?php _e('File Name', GRM_TEXT_DOMAIN); ?> <span class="sort-icon"></span></th>
                    <th data-sort="file_type"><?php _e('Format', GRM_TEXT_DOMAIN); ?><span class="sort-icon"></span></th>
                    <th data-sort="created_at"><?php _e('Upload Date', GRM_TEXT_DOMAIN); ?><span class="sort-icon"></span></th>
                    <th><?php _e('Actions', GRM_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- AJAX content will be loaded here -->
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    public function user_orders_table_shortcode($atts) {
        ob_start();
        ?>
        <table id="grm-uploaded-files" class="display">
            <thead>
                <tr>
                    <th data-sort="file_name"><?php _e('File Name', GRM_TEXT_DOMAIN); ?> <span class="sort-icon"></span></th>
                    <th data-sort="file_type"><?php _e('Format', GRM_TEXT_DOMAIN); ?><span class="sort-icon"></span></th>
                    <th data-sort="created_at"><?php _e('Upload Date', GRM_TEXT_DOMAIN); ?><span class="sort-icon"></span></th>
                    <th><?php _e('Actions', GRM_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- AJAX content will be loaded here -->
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    public function user_results_table_shortcode($atts) {
        ob_start();
        ?>
        <table id="grm-result-files" class="display">
            <thead>
                <tr>
                    <th data-sort="file_name"><?php _e('Report Name', GRM_TEXT_DOMAIN); ?> <span class="sort-icon"></span></th>
                    <th data-sort="file_type"><?php _e('Based on', GRM_TEXT_DOMAIN); ?> <span class="sort-icon"></span></th>
                    <th data-sort="created_at"><?php _e('Date', GRM_TEXT_DOMAIN); ?> <span class="sort-icon"></span></th>
                    <th><?php _e('Actions', GRM_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- AJAX content will be loaded here -->
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    public function download_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'result_id' => '',
            'file_name' => '',
            'folder_name' => ''
        ), $atts);
        
        $result_id = esc_attr($atts['result_id']);
        $file_name = esc_html($atts['file_name']);
        $folder_name = esc_html($atts['folder_name']);
        
        ob_start();
        ?>
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h3 style="margin-bottom: 5px;"><?php echo $file_name; ?></h3>
                <p style="margin-top: 0;"><?php echo $folder_name; ?></p>
            </div>
            <div>
                <button id="grm-download-btn" data-result-id="<?php echo $result_id; ?>" 
                        style="color: white; background-color: var(--e-global-color-primary); padding: 10px 20px; cursor: pointer; border: none; border-radius: 4px;">
                    <?php _e('Download Report', GRM_TEXT_DOMAIN); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_to_cart_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts);
        
        if (empty($atts['id'])) {
            return '';
        }
        
        // Store upload_id in session before adding to cart
        if (isset($_GET['upload_id'])) {
            WC()->session->set('temp_upload_id', intval($_GET['upload_id']));
        }
        
        $url = wc_get_cart_url() . '?add-to-cart=' . $atts['id'];
        
        return sprintf(
            '<a href="%s" class="grm-add-to-cart-button ajax_add_to_cart" data-product_id="%s" 
             style="display:block; text-align:center; background-color: #007cba; color: white; 
             padding: 10px 20px; text-decoration: none; border-radius: 5px; 
             transition: background-color 0.3s ease;">
                %s
            </a>',
            esc_url($url),
            esc_attr($atts['id']),
            __('Select', GRM_TEXT_DOMAIN)
        );
    }
    
    public function product_price_shortcode($atts) {
        $atts = shortcode_atts(array('id' => ''), $atts);
        
        if (empty($atts['id'])) {
            return '';
        }
        
        $price = get_post_meta($atts['id'], '_price', true);
        return $price ? wc_price($price) : '';
    }
    
    public function product_name_shortcode($atts) {
        $atts = shortcode_atts(array('id' => ''), $atts);
        
        if (empty($atts['id'])) {
            return '';
        }
        
        $product = wc_get_product($atts['id']);
        return $product ? $product->get_name() : __('Product not found', GRM_TEXT_DOMAIN);
    }
    
    public function file_name_display_shortcode($atts) {
        $user_id = get_current_user_id();
        if ($user_id == 0) {
            wp_redirect(home_url(), 301);
            exit();
        }
        
        if (isset($_GET['upload_id'])) {
            $upload_id = intval($_GET['upload_id']);
            
            $upload = GRM_Database::get_upload($upload_id);
            
            if ($upload && $upload->user_id == $user_id) {
                return esc_html(basename($upload->file_path));
            } else {
                $report_url = site_url('/order-report');
                wp_redirect($report_url, 301);
                exit();
            }
        } else {
            $report_url = site_url('/order-report');
            wp_redirect($report_url, 301);
            exit();
        }
    }
    
    public function all_orders_shortcode($atts) {
        $user_id = get_current_user_id();
        
        if ($user_id == 0) {
            return do_shortcode('[woocommerce_my_account]');
        }
        
        $args = array(
            'customer' => $user_id,
            'limit' => -1,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        );
        
        $orders = wc_get_orders($args);
        
        if (empty($orders)) {
            return '<p>' . __('You have no orders yet.', GRM_TEXT_DOMAIN) . '</p>';
        }
        
        ob_start();
        ?>
        <table class="grm-orders-table">
            <thead>
                <tr>
                    <th><?php _e('Order #', GRM_TEXT_DOMAIN); ?></th>
                    <th><?php _e('Date', GRM_TEXT_DOMAIN); ?></th>
                    <th><?php _e('Status', GRM_TEXT_DOMAIN); ?></th>
                    <th><?php _e('Total', GRM_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?php echo $order->get_order_number(); ?></td>
                    <td><?php echo wc_format_datetime($order->get_date_created()); ?></td>
                    <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                    <td><?php echo $order->get_formatted_order_total(); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
            