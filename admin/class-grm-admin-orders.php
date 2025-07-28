<?php
/**
 * Admin Orders Helper
 *
 * Adds a simple meta box to WooCommerce orders listing associated reports.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRM_Admin_Orders {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'grm_order_reports',
            __('Genetic Reports', GRM_TEXT_DOMAIN),
            array($this, 'render_reports_meta_box'),
            'shop_order',
            'side'
        );
    }

    public function render_reports_meta_box($post) {
        $order_id = $post->ID;
        $reports = GRM_Database::get_reports_by_order($order_id);
        if (empty($reports)) {
            echo '<p>' . esc_html__('No reports for this order.', GRM_TEXT_DOMAIN) . '</p>';
            return;
        }
        echo '<ul>';
        foreach ($reports as $report) {
            echo '<li>' . esc_html($report->report_name) . ' (' . esc_html($report->status) . ')</li>';
        }
        echo '</ul>';
    }
}

