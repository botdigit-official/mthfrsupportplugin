<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('User Uploads', GRM_TEXT_DOMAIN); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('User', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('File', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Source', GRM_TEXT_DOMAIN); ?></th>
                <th><?php esc_html_e('Created', GRM_TEXT_DOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($uploads)) : ?>
            <tr><td colspan="5"><?php esc_html_e('No uploads found.', GRM_TEXT_DOMAIN); ?></td></tr>
        <?php else : foreach ($uploads as $upload) : ?>
            <tr>
                <td><?php echo intval($upload->id); ?></td>
                <td><?php echo esc_html($upload->user_id); ?></td>
                <td><?php echo esc_html($upload->file_name); ?></td>
                <td><?php echo esc_html($upload->source_type); ?></td>
                <td><?php echo esc_html($upload->created_at); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

