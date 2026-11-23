<?php

defined('ABSPATH') || exit;

/**
 * Enqueue front-end styles for Skyrocket Events.
 */
function skyrocket_events_enqueue_styles(): void
{
    $relative_path = 'assets/css/skyrocket-events.css';
    $file_path = SKYROCKET_EVENTS_PATH . $relative_path;
    $file_url = SKYROCKET_EVENTS_URL . $relative_path;

    $version = file_exists($file_path)
        ? (string) filemtime($file_path)
        : '1.0.1';

    wp_enqueue_style(
        'skyrocket-events',
        $file_url,
        [],
        $version
    );
}
add_action('wp_enqueue_scripts', 'skyrocket_events_enqueue_styles');