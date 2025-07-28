<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Generated Reports', GRM_TEXT_DOMAIN); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Upload', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Order', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Name', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Status', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Created', GRM_TEXT_DOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($reports)) : ?>
            <tr><td colspan="6"><?php esc_html_e('No reports found.', GRM_TEXT_DOMAIN); ?></td></tr>
        <?php else : foreach ($reports as $report) : ?>
            <tr>
                <td><?php echo intval($report->id); ?></td>
                <td><?php echo esc_html($report->upload_file_name); ?></td>
                <td><?php echo intval($report->order_id); ?></td>
                <td><?php echo esc_html($report->report_name); ?></td>
                <td><?php echo esc_html($report->status); ?></td>
                <td><?php echo esc_html($report->created_at); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

