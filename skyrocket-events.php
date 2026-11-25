<?php
/**
 * Plugin Name: Skyrocket Events
 * Description: Custom event management and calendar.
 * Version: 1.0.9
 * Requires PHP: 8.1
 * Text Domain: skyrocket-events
 */

defined('ABSPATH') || exit;

// Define plugin constants for modular file inclusion
define('SKYROCKET_EVENTS_PATH', plugin_dir_path(__FILE__));
define('SKYROCKET_EVENTS_URL', plugin_dir_url(__FILE__));


// GitHub updater configuration
$github_updater_config = [
    'username' => 'lavamitts',
    'repository' => 'skyrocket-events',
    'plugin_file' => __FILE__,
];

// Updater code
require_once __DIR__ . '/vendor/lavamitts/skyrocket-plugin-updater/src/plugin-updater.php';


// Remove the temporary configuration variable.
unset($github_updater_config);

// Load modular component files
require_once SKYROCKET_EVENTS_PATH . 'includes/helpers.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/settings.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/post-types.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/meta-boxes.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/shortcodes/bootstrapper.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/organisers.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/venues.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/assets.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/include-events-in-search.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/templates.php';
require_once SKYROCKET_EVENTS_PATH . 'includes/single-event-header.php';

/**
 * Load event editor JavaScript.
 */
function skyrocket_events_enqueue_admin_scripts(): void
{
    $screen = get_current_screen();

    if (
        !$screen
        || 'pavilion_event' !== $screen->post_type
        || 'post' !== $screen->base
    ) {
        return;
    }

    wp_enqueue_script(
        'skyrocket-events-admin',
        SKYROCKET_EVENTS_URL . 'assets/js/admin-events.js',
        [],
        '1.0.0',
        true
    );
}
add_action('admin_enqueue_scripts', 'skyrocket_events_enqueue_admin_scripts');