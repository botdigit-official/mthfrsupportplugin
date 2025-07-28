<?php
/**
 * Plugin Setup Script
 * 
 * Run this script ONCE to create the plugin directory structure
 * Upload this file to your wp-content/plugins/genetic-report-manager/ directory
 * Then run it by accessing: yourdomain.com/wp-content/plugins/genetic-report-manager/setup-plugin.php
 * 
 * WARNING: This script should be deleted after use for security reasons
 */

// Security check - only run if WordPress is not loaded (direct access)
if (defined('ABSPATH')) {
    die('This script should not be run from within WordPress.');
}

// Get the plugin directory
$plugin_dir = __DIR__;
$plugin_name = 'Genetic Report Manager Pro';

echo "<!DOCTYPE html>
<html>
<head>
    <title>$plugin_name Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>";

echo "<h1>$plugin_name - Directory Setup</h1>";

// Define the directory structure
$directories = array(
    'includes',
    'public', 
    'admin',
    'admin/views',
    'assets',
    'assets/css',
    'assets/js',
    'templates',
    'lookup',
    'languages'
);

// Define files that need to be created (empty placeholders)
$files = array(
    'includes/class-grm-database.php' => '<?php // Database class - replace with provided code',
    'includes/class-grm-logger.php' => '<?php // Logger class - replace with provided code',
    'includes/class-grm-file-handler.php' => '<?php // File handler class - replace with provided code',
    'includes/class-grm-report-generator.php' => '<?php // Report generator class - replace with provided code',
    'includes/class-grm-api-handler.php' => '<?php // API handler class - replace with provided code',
    'public/class-grm-shortcodes.php' => '<?php // Shortcodes class - replace with provided code',
    'public/class-grm-ajax-handler.php' => '<?php // AJAX handler class - replace with provided code',
    'public/class-grm-assets.php' => '<?php // Assets class - replace with provided code',
    'public/class-grm-woocommerce-integration.php' => '<?php // WooCommerce integration - replace with provided code',
    'admin/class-grm-admin.php' => '<?php // Admin class - replace with provided code',
    'admin/class-grm-admin-orders.php' => '<?php // Admin orders class - create this file',
    'admin/class-grm-admin-reports.php' => '<?php // Admin reports class - create this file',
    'admin/views/dashboard.php' => '<!-- Dashboard view - create this template -->',
    'admin/views/settings.php' => '<!-- Settings view - create this template -->',
    'admin/views/reports.php' => '<!-- Reports view - create this template -->',
    'admin/views/uploads.php' => '<!-- Uploads view - create this template -->',
    'admin/views/logs.php' => '<!-- Logs view - create this template -->',
    'assets/css/admin.css' => '/* Admin CSS styles */',
    'assets/css/frontend.css' => '/* Frontend CSS styles */',
    'assets/css/report-styles.css' => '/* Report visualization styles */',
    'assets/js/admin.js' => '/* Admin JavaScript */',
    'assets/js/uploaded-files.js' => '/* Uploaded files JavaScript */',
    'assets/js/result-files.js' => '/* Result files JavaScript */',
    'assets/js/download-report-button.js' => '/* Download button JavaScript */',
    'assets/js/view-files.js' => '/* View files JavaScript */',
    'templates/report-visualization.php' => '<!-- Report visualization template -->',
    'lookup/old_videos_lookup.json' => '{}',
    'lookup/new_urls.json' => '{}',
    'README.txt' => "=== $plugin_name ===\nContributors: mthfrsupport\nDescription: Genetic report management system\nVersion: 2.1.0"
);

echo "<h2>Creating Directory Structure...</h2>";

// Create directories
foreach ($directories as $dir) {
    $dir_path = $plugin_dir . '/' . $dir;
    if (!is_dir($dir_path)) {
        if (mkdir($dir_path, 0755, true)) {
            echo "<div class='success'>✓ Created directory: $dir</div>";
        } else {
            echo "<div class='error'>✗ Failed to create directory: $dir</div>";
        }
    } else {
        echo "<div class='info'>• Directory already exists: $dir</div>";
    }
}

echo "<h2>Creating Placeholder Files...</h2>";

// Create placeholder files
foreach ($files as $file_path => $content) {
    $full_path = $plugin_dir . '/' . $file_path;
    
    if (!file_exists($full_path)) {
        $dir = dirname($full_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (file_put_contents($full_path, $content)) {
            echo "<div class='success'>✓ Created file: $file_path</div>";
        } else {
            echo "<div class='error'>✗ Failed to create file: $file_path</div>";
        }
    } else {
        echo "<div class='info'>• File already exists: $file_path</div>";
    }
}

echo "<h2>Setup Complete!</h2>";
echo "<div class='warning'>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>DELETE THIS SETUP FILE</strong> for security reasons</li>";
echo "<li>Replace the placeholder PHP files with the actual code provided in the artifacts</li>";
echo "<li>Copy your existing JavaScript files from the theme to assets/js/</li>";
echo "<li>Copy your existing CSS files to assets/css/</li>";
echo "<li>Create the admin view templates in admin/views/</li>";
echo "<li>Test the plugin activation in WordPress admin</li>";
echo "</ol>";
echo "</div>";

echo "<h3>Files to Replace with Provided Code:</h3>";
echo "<ul>";
foreach ($files as $file_path => $content) {
    if (strpos($content, '<?php //') === 0) {
        echo "<li><code>$file_path</code></li>";
    }
}
echo "</ul>";

echo "<div class='info'>";
echo "<h3>Plugin Directory Structure Created:</h3>";
echo "<pre>";
echo "genetic-report-manager/\n";
foreach ($directories as $dir) {
    echo "├── $dir/\n";
}
echo "├── genetic-report-manager.php (main plugin file)\n";
echo "├── setup-plugin.php (DELETE THIS FILE)\n";
echo "└── README.txt\n";
echo "</pre>";
echo "</div>";

echo "<div class='error'>";
echo "<p><strong>IMPORTANT SECURITY NOTE:</strong></p>";
echo "<p>Please delete this setup-plugin.php file immediately after running it.</p>";
echo "<p>Leaving setup scripts on your server is a security risk.</p>";
echo "</div>";

echo "</body></html>";
?>