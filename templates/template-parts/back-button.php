<?php
/**
 * Skyrocket events: Back button.
 */

$breadcrumb = skyrocket_events_get_breadcrumb_data($calendar_settings);
?>

<div class="wp-block-button back-button image-button secondary" style="margin-top:1rem">
    <a href="<?php echo esc_url($breadcrumb['url']); ?>" class="wp-block-button__link">
        Back to
        <?php echo esc_html($breadcrumb['label']); ?>
    </a>
</div>