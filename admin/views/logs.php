<?php
if (!defined('ABSPATH')) {
    exit;
}

$logs = GRM_Logger::get_logs(50);
$recent_reports = GRM_Database::get_recent_reports(10);
?>

<div class="wrap">
    <h1>Activity Logs</h1>
    
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <form method="post" style="display: inline-block;">
            <button type="submit" name="clear_logs" class="button button-secondary" 
                    onclick="return confirm('Are you sure you want to clear all logs?')">
                Clear All Logs
            </button>
        </form>
        
        <form method="post" style="display: inline-block;">
            <button type="submit" name="test_system" class="button button-primary">
                Run System Test
            </button>
        </form>
        
        <button type="button" class="button button-secondary" onclick="refreshLogs()">
            Refresh Logs
        </button>
    </div>
    
    <div style="display: flex; gap: 20px;">
        <div style="flex: 2;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">Recent Activity Logs</h2>
                </div>
                <div class="inside">
                    <?php if (empty($logs)): ?>
                        <p>No logs available. Try generating a report to see activity here.</p>
                    <?php else: ?>
                        <div style="background: #f1f1f1; font-family: monospace; font-size: 12px; padding: 15px; max-height: 600px; overflow-y: auto; border: 1px solid #ddd;">
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $level_colors = array(
                                    'error' => '#d63638',
                                    'warning' => '#dba617', 
                                    'info' => '#2271b1',
                                    'debug' => '#666'
                                );
                                $color = $level_colors[$log->level] ?? '#666';
                                ?>
                                <div style="margin-bottom: 5px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
                                    <span style="color: #999;"><?php echo $log->created_at; ?></span>
                                    <span style="color: <?php echo $color; ?>; font-weight: bold; margin-left: 10px;">
                                        [<?php echo strtoupper($log->level); ?>]
                                    </span>
                                    <span style="margin-left: 10px;"><?php echo esc_html($log->message); ?></span>
                                    <?php if ($log->context): ?>
                                        <details style="margin-top: 5px; margin-left: 20px;">
                                            <summary style="cursor: pointer; color: #666;">Context</summary>
                                            <pre style="background: #fff; padding: 10px; margin: 5px 0; border: 1px solid #ccc; font-size: 11px;"><?php echo esc_html($log->context); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="flex: 1;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">Debug Information</h2>
                </div>
                <div class="inside">
                    <table class="widefat">
                        <tr>
                            <td><strong>Plugin Version:</strong></td>
                            <td><?php echo GRM_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>WordPress Version:</strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>WooCommerce:</strong></td>
                            <td><?php echo class_exists('WooCommerce') ? 'Active' : 'Inactive'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Current Time:</strong></td>
                            <td><?php echo current_time('mysql'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Site URL:</strong></td>
                            <td><?php echo get_site_url(); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Plugin Directory:</strong></td>
                            <td><?php echo GRM_PLUGIN_DIR; ?></td>
                        </tr>
                    </table>
                    
                    <h4 style="margin-top: 20px;">Database Tables:</h4>
                    <?php
                    global $wpdb;
                    $tables = array(
                        'grg_user_uploads' => $wpdb->prefix . 'grg_user_uploads',
                        'grg_reports' => $wpdb->prefix . 'grg_reports',
                        'grg_logs' => $wpdb->prefix . 'grg_logs'
                    );
                    
                    foreach ($tables as $name => $table) {
                        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                        $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table") : 0;
                        $status = $exists ? 'green' : 'red';
                        echo "<p style='color: $status;'>$name: " . ($exists ? "✓ ($count records)" : "✗ Missing") . "</p>";
                    }
                    ?>
                    
                    <h4 style="margin-top: 20px;">File Permissions:</h4>
                    <?php
                    $paths_to_check = array(
                        'Plugin Directory' => GRM_PLUGIN_DIR,
                        'Data Directory' => GRM_PLUGIN_DIR . 'data/',
                        'Assets Directory' => GRM_PLUGIN_DIR . 'assets/',
                        'WordPress Content' => WP_CONTENT_DIR
                    );
                    
                    foreach ($paths_to_check as $name => $path) {
                        $writable = is_writable($path);
                        $exists = file_exists($path);
                        $status = ($exists && $writable) ? 'green' : 'red';
                        $text = $exists ? ($writable ? '✓ Writable' : '✗ Not Writable') : '✗ Missing';
                        echo "<p style='color: $status;'>$name: $text</p>";
                    }
                    ?>
                </div>
            </div>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle">Recent Reports Summary</h2>
                </div>
                <div class="inside">
                    <?php if (empty($recent_reports)): ?>
                        <p>No reports generated yet.</p>
                    <?php else: ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Type</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_reports, 0, 5) as $report): ?>
                                    <tr>
                                        <td><?php echo $report->id; ?></td>
                                        <td>
                                            <span style="color: <?php 
                                                switch($report->status) {
                                                    case 'completed': echo '#00a32a'; break;
                                                    case 'pending': echo '#dba617'; break; 
                                                    case 'failed': echo '#d63638'; break;
                                                    default: echo '#666';
                                                }
                                            ?>;">
                                                <?php echo ucfirst($report->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($report->report_type); ?></td>
                                        <td><?php echo date('M j H:i', strtotime($report->created_at)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p style="text-align: center; margin-top: 10px;">
                            <a href="<?php echo admin_url('admin.php?page=genetic-reports'); ?>" class="button button-small">
                                View All Reports
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshLogs() {
    location.reload();
}

// Auto-refresh logs every 30 seconds if debug mode is enabled
<?php if (get_option('grm_enable_debug', 0)): ?>
setInterval(function() {
    // Only refresh if user hasn't interacted recently
    if (document.hasFocus() && Date.now() - lastActivity > 30000) {
        location.reload();
    }
}, 30000);

let lastActivity = Date.now();
document.addEventListener('click', function() {
    lastActivity = Date.now();
});
<?php endif; ?>
</script>
