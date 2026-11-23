<?php
defined('ABSPATH') || exit;

/**
 * Load the single event template from the plugin directory.
 */
function skyrocket_events_single_template(string $template): string
{
    if (!is_singular('pavilion_event')) {
        return $template;
    }

    $plugin_template = SKYROCKET_EVENTS_PATH . 'templates/single-pavilion_event.php';
    if (file_exists($plugin_template)) {
        return $plugin_template;
    }

    return $template;
}
add_filter('single_template', 'skyrocket_events_single_template');