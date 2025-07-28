<?php
if (!defined('ABSPATH')) {
    exit;
}

$reportable_products = get_option('grg_reportable_products', array(120, 938, 971, 977, 1698));
$subscription_product = get_option('grg_subscription_product', 2152);
$enable_debug = get_option('grg_enable_debug', 0);
$auto_generate = get_option('grg_auto_generate', 0);
?>

<div class="wrap">
    <h1>Genetic Reports Settings</h1>
    
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="grg_reportable_products">Reportable Product IDs</label>
                </th>
                <td>
                    <input type="text" 
                           id="grg_reportable_products" 
                           name="grg_reportable_products" 
                           value="<?php echo esc_attr(implode(',', $reportable_products)); ?>" 
                           class="regular-text" />
                    <p class="description">
                        Comma-separated list of WooCommerce product IDs that can generate genetic reports.
                        <br>Example: 120,938,971,977,1698
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="grg_subscription_product">Subscription Product ID</label>
                </th>
                <td>
                    <input type="number" 
                           id="grg_subscription_product" 
                           name="grg_subscription_product" 
                           value="<?php echo esc_attr($subscription_product); ?>" 
                           class="small-text" />
                    <p class="description">
                        Product ID that indicates a subscription order with additional features.
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Debug Mode</th>
                <td>
                    <fieldset>
                        <label for="grg_enable_debug">
                            <input type="checkbox" 
                                   id="grg_enable_debug" 
                                   name="grg_enable_debug" 
                                   value="1" 
                                   <?php checked($enable_debug, 1); ?> />
                            Enable debug logging
                        </label>
                        <p class="description">
                            When enabled, detailed debugging information will be logged for troubleshooting.
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Auto Generation</th>
                <td>
                    <fieldset>
                        <label for="grg_auto_generate">
                            <input type="checkbox" 
                                   id="grg_auto_generate" 
                                   name="grg_auto_generate" 
                                   value="1" 
                                   <?php checked($auto_generate, 1); ?> />
                            Automatically generate reports when orders are completed
                        </label>
                        <p class="description">
                            When enabled, reports will be generated automatically when an order status changes to "completed".
                            <br><strong>Note:</strong> This feature requires upload_id to be present in order items.
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <hr>
        
        <h2>Report Types Configuration</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Available Report Types</th>
                <td>
                    <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                        <h4 style="margin-top: 0;">Variant Report</h4>
                        <p>Comprehensive genetic variant analysis including MTHFR, COMT, detoxification pathways, and more.</p>
                        
                        <h4>Methylation Report</h4>
                        <p>Focused analysis of methylation and methionine/homocysteine pathways.</p>
                        
                        <h4>COVID-19 Report</h4>
                        <p>Analysis of genetic variants related to COVID-19 susceptibility and immune response.</p>
                    </div>
                    <p class="description">
                        Report type is automatically determined based on the product name:
                        <br>• Products containing "covid" → COVID-19 Report
                        <br>• Products containing "meth" → Methylation Report  
                        <br>• All other products → Variant Report
                    </p>
                </td>
            </tr>
        </table>
        
        <hr>
        
        <h2>File Paths</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Database Files</th>
                <td>
                    <?php
                    $genetic_db = GRM_PLUGIN_DIR . 'data/genetic-database.xlsx';
                    $meth_db = GRM_PLUGIN_DIR . 'data/meth-database.xlsx';
                    ?>
                    <p>
                        <strong>Genetic Database:</strong> 
                        <code><?php echo esc_html($genetic_db); ?></code>
                        <span style="color: <?php echo file_exists($genetic_db) ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo file_exists($genetic_db) ? '✓ Found' : '✗ Missing'; ?>
                        </span>
                    </p>
                    <p>
                        <strong>Methylation Database:</strong> 
                        <code><?php echo esc_html($meth_db); ?></code>
                        <span style="color: <?php echo file_exists($meth_db) ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo file_exists($meth_db) ? '✓ Found' : '✗ Missing'; ?>
                        </span>
                    </p>
                    <p class="description">
                        If database files are missing, the plugin will use sample data for testing.
                        Upload your genetic variant database files to the <code>/data/</code> directory.
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Asset Files</th>
                <td>
                    <?php
                    $logo = GRM_PLUGIN_DIR . 'assets/images/report-logo.png';
                    $gene_img = GRM_PLUGIN_DIR . 'assets/images/gene.png';
                    ?>
                    <p>
                        <strong>Report Logo:</strong> 
                        <code><?php echo esc_html($logo); ?></code>
                        <span style="color: <?php echo file_exists($logo) ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo file_exists($logo) ? '✓ Found' : '✗ Missing'; ?>
                        </span>
                    </p>
                    <p>
                        <strong>Gene Illustration:</strong> 
                        <code><?php echo esc_html($gene_img); ?></code>
                        <span style="color: <?php echo file_exists($gene_img) ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo file_exists($gene_img) ? '✓ Found' : '✗ Missing'; ?>
                        </span>
                    </p>
                    <p class="description">
                        Upload your custom logo and gene illustration images to the <code>/assets/images/</code> directory.
                    </p>
                </td>
            </tr>
        </table>
        
        <hr>
        
        <h2>System Status</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Requirements Check</th>
                <td>
                    <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                        <?php
                        $checks = array(
                            'PHP Version (7.4+)' => version_compare(PHP_VERSION, '7.4.0', '>='),
                            'WordPress (5.0+)' => version_compare(get_bloginfo('version'), '5.0', '>='),
                            'WooCommerce Active' => class_exists('WooCommerce'),
                            'PHP GD Extension' => extension_loaded('gd'),
                            'PHP ZIP Extension' => extension_loaded('zip'),
                            'Write Permissions' => is_writable(WP_CONTENT_DIR)
                        );
                        
                        foreach ($checks as $check => $status) {
                            $color = $status ? '#00a32a' : '#d63638';
                            $icon = $status ? '✓' : '✗';
                            echo "<p style='color: $color;'>$icon $check</p>";
                        }
                        ?>
                    </div>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save Settings'); ?>
    </form>
    
    <hr>
    
    <h2>Dangerous Actions</h2>
    <div style="background: #ffeaa7; padding: 15px; border: 1px solid #fdcb6e; border-radius: 4px;">
        <h3 style="margin-top: 0; color: #e17055;">⚠️ Use with Caution</h3>
        
        <p>
            <button type="button" class="button button-secondary" onclick="recreateTables()">
                Recreate Database Tables
            </button>
            <span style="margin-left: 10px; color: #666;">
                This will delete all existing reports and logs.
            </span>
        </p>
        
        <p>
            <button type="button" class="button button-secondary" onclick="clearAllData()">
                Clear All Report Data
            </button>
            <span style="margin-left: 10px; color: #666;">
                This will delete all reports but keep the table structure.
            </span>
        </p>
    </div>
</div>

<script>
function recreateTables() {
    if (confirm('This will delete ALL existing reports and logs. Are you sure?')) {
        if (confirm('This action cannot be undone. Continue?')) {
            window.location.href = '<?php echo admin_url('admin.php?page=genetic-reports-settings&recreate_tables=1'); ?>';
        }
    }
}

function clearAllData() {
    if (confirm('This will delete all report data. Are you sure?')) {
        window.location.href = '<?php echo admin_url('admin.php?page=genetic-reports-settings&clear_data=1'); ?>';
    }
}

<?php if (isset($_GET['recreate_tables']) && $_GET['recreate_tables'] == '1'): ?>
    <?php
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'grg_user_uploads',
        $wpdb->prefix . 'grg_reports', 
        $wpdb->prefix . 'grg_logs'
    );
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    GRM_Database::create_tables();
    ?>
    <script>
        alert('Database tables recreated successfully!');
        window.location.href = '<?php echo admin_url('admin.php?page=genetic-reports-settings'); ?>';
    </script>
<?php endif; ?>

<?php if (isset($_GET['clear_data']) && $_GET['clear_data'] == '1'): ?>
    <?php
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . "grg_reports");
    $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . "grg_user_uploads");
    GRM_Database::clear_logs();
    ?>
    <script>
        alert('All report data cleared successfully!');
        window.location.href = '<?php echo admin_url('admin.php?page=genetic-reports-settings'); ?>';
    </script>
<?php endif; ?>
</script>
