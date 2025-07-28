<?php
if (!defined('ABSPATH')) {
    exit;
}

$recent_reports = GRM_Database::get_recent_reports(10);
$stats = array(
    'total_reports' => count($recent_reports),
    'completed' => count(array_filter($recent_reports, function($r) { return $r->status === 'completed'; })),
    'pending' => count(array_filter($recent_reports, function($r) { return $r->status === 'pending'; })),
    'failed' => count(array_filter($recent_reports, function($r) { return $r->status === 'failed'; }))
);
?>

<div class="wrap">
    <h1>Genetic Reports Dashboard</h1>
    
    <div class="grg-dashboard-stats" style="display: flex; gap: 20px; margin-bottom: 30px;">
        <div class="grg-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
            <h3 style="margin: 0 0 10px 0; color: #1d2327;">Total Reports</h3>
            <div style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo $stats['total_reports']; ?></div>
        </div>
        
        <div class="grg-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
            <h3 style="margin: 0 0 10px 0; color: #1d2327;">Completed</h3>
            <div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo $stats['completed']; ?></div>
        </div>
        
        <div class="grg-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
            <h3 style="margin: 0 0 10px 0; color: #1d2327;">Pending</h3>
            <div style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo $stats['pending']; ?></div>
        </div>
        
        <div class="grg-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; flex: 1;">
            <h3 style="margin: 0 0 10px 0; color: #1d2327;">Failed</h3>
            <div style="font-size: 24px; font-weight: bold; color: #d63638;"><?php echo $stats['failed']; ?></div>
        </div>
    </div>
    
    <div class="grg-dashboard-content" style="display: flex; gap: 20px;">
        <div style="flex: 2;">
            <div class="postbox" style="background: #fff;">
                <div class="postbox-header">
                    <h2 class="hndle">Recent Reports</h2>
                </div>
                <div class="inside">
                    <?php if (empty($recent_reports)): ?>
                        <p>No reports generated yet.</p>
                        <p><a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-primary">Go to Orders</a></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Order ID</th>
                                    <th>Report Type</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td><?php echo $report->id; ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('post.php?post=' . $report->order_id . '&action=edit'); ?>">
                                                #<?php echo $report->order_id; ?>
                                            </a>
                                        </td>
                                        <td><?php echo ucfirst($report->report_type); ?></td>
                                        <td>
                                            <span class="grg-status grg-status-<?php echo $report->status; ?>" 
                                                  style="padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;
                                                         <?php 
                                                         switch($report->status) {
                                                             case 'completed': echo 'background: #00a32a; color: white;'; break;
                                                             case 'pending': echo 'background: #dba617; color: white;'; break;
                                                             case 'failed': echo 'background: #d63638; color: white;'; break;
                                                             default: echo 'background: #ddd; color: #666;';
                                                         }
                                                         ?>">
                                                <?php echo ucfirst($report->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($report->created_at)); ?></td>
                                        <td>
                                            <?php if ($report->status === 'completed'): ?>
                                                <button class="button button-small grg-download-report" 
                                                        data-report-id="<?php echo $report->id; ?>">
                                                    Download
                                                </button>
                                            <?php elseif ($report->status === 'failed'): ?>
                                                <button class="button button-small grg-retry-report" 
                                                        data-upload-id="<?php echo $report->upload_id; ?>"
                                                        data-order-id="<?php echo $report->order_id; ?>"
                                                        data-product-id="<?php echo $report->product_id; ?>"
                                                        data-product-name="<?php echo esc_attr($report->report_name); ?>">
                                                    Retry
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="flex: 1;">
            <div class="postbox" style="background: #fff;">
                <div class="postbox-header">
                    <h2 class="hndle">Quick Actions</h2>
                </div>
                <div class="inside">
                    <p><a href="<?php echo admin_url('admin.php?page=genetic-reports-settings'); ?>" class="button button-primary" style="width: 100%; text-align: center; margin-bottom: 10px;">Plugin Settings</a></p>
                    <p><a href="<?php echo admin_url('admin.php?page=genetic-reports-logs'); ?>" class="button button-secondary" style="width: 100%; text-align: center; margin-bottom: 10px;">View Activity Logs</a></p>
                    <p><a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-secondary" style="width: 100%; text-align: center; margin-bottom: 10px;">Manage Orders</a></p>
                </div>
            </div>
            
            <div class="postbox" style="background: #fff; margin-top: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle">System Info</h2>
                </div>
                <div class="inside">
                    <p><strong>Plugin Version:</strong> <?php echo GRM_VERSION; ?></p>
                    <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                    <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                    <p><strong>WooCommerce:</strong> <?php echo class_exists('WooCommerce') ? 'Active' : 'Inactive'; ?></p>
                    
                    <?php
                    global $wpdb;
                    $tables_exist = true;
                    $required_tables = array(
                        $wpdb->prefix . 'grg_user_uploads',
                        $wpdb->prefix . 'grg_reports',
                        $wpdb->prefix . 'grg_logs'
                    );
                    
                    foreach ($required_tables as $table) {
                        if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) {
                            $tables_exist = false;
                            break;
                        }
                    }
                    ?>
                    <p><strong>Database Tables:</strong> 
                        <span style="color: <?php echo $tables_exist ? '#00a32a' : '#d63638'; ?>">
                            <?php echo $tables_exist ? 'OK' : 'Missing'; ?>
                        </span>
                    </p>
                    
                    <?php if (!$tables_exist): ?>
                        <p>
                            <button class="button button-primary" onclick="createTables()">Create Tables</button>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Download report
    $('.grg-download-report').on('click', function() {
        const reportId = $(this).data('report-id');
        
        $.post(ajaxurl, {
            action: 'grg_download_report',
            report_id: reportId,
            nonce: '<?php echo wp_create_nonce('grg_ajax_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                const link = document.createElement('a');
                link.href = 'data:application/pdf;base64,' + response.data.pdf_data;
                link.download = response.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                alert('Download failed: ' + response.data);
            }
        });
    });
    
    // Retry report
    $('.grg-retry-report').on('click', function() {
        const btn = $(this);
        const uploadId = btn.data('upload-id');
        const orderId = btn.data('order-id');
        const productId = btn.data('product-id');
        const productName = btn.data('product-name');
        
        btn.prop('disabled', true).text('Retrying...');
        
        $.post(ajaxurl, {
            action: 'grg_generate_report',
            upload_id: uploadId,
            order_id: orderId,
            product_id: productId,
            product_name: productName,
            nonce: '<?php echo wp_create_nonce('grg_ajax_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Retry failed: ' + response.data);
                btn.prop('disabled', false).text('Retry');
            }
        });
    });
});

function createTables() {
    if (confirm('Create missing database tables?')) {
        window.location.href = '<?php echo admin_url('admin.php?page=genetic-reports&create_tables=1'); ?>';
    }
}

<?php if (isset($_GET['create_tables']) && $_GET['create_tables'] == '1'): ?>
    <?php GRM_Database::create_tables(); ?>
    <script>
        alert('Database tables created successfully!');
        window.location.href = '<?php echo admin_url('admin.php?page=genetic-reports'); ?>';
    </script>
<?php endif; ?>
</script>
