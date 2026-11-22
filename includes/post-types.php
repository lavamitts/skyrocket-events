<?php
defined('ABSPATH') || exit;

/**
- Register custom post types.
 */
function skyrocket_events_register_post_types(): void
{
    // Register Event Post Type (Remains its own top-level menu)
    register_post_type(
        'pavilion_event',
        [
            'labels' => [
                'name' => 'Events',
                'singular_name' => 'Event',
                'add_new_item' => 'Add New Event',
                'edit_item' => 'Edit Event',
                'new_item' => 'New Event',
                'view_item' => 'View Event',
                'search_items' => 'Search Events',
                'not_found' => 'No events found',
                'menu_name' => 'Events',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false, // Explicitly allow search indexing
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => [
                'slug' => 'events',
                'with_front' => false,
            ],
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => [
                'title',
                'editor',
                'excerpt',
                'thumbnail',
                'revisions',
            ],
        ]
    );

    // Register Venue Post Type (Nested under Events menu)
    register_post_type(
        'pavilion_venue',
        [
            'labels' => [
                'name' => 'Venues',
                'singular_name' => 'Venue',
                'add_new_item' => 'Add New Venue',
                'edit_item' => 'Edit Venue',
                'new_item' => 'New Venue',
                'view_item' => 'View Venue',
                'search_items' => 'Search Venues',
                'not_found' => 'No venues found',
                'menu_name' => 'Venues',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=pavilion_event',
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location-alt',
            'supports' => [
                'title',
                'editor',
                'excerpt',
            ],
        ]
    );

    // Register Organiser Post Type (Nested under Events menu)
    register_post_type(
        'pavilion_organiser',
        [
            'labels' => [
                'name' => 'Organisers',
                'singular_name' => 'Organiser',
                'add_new_item' => 'Add New Organiser',
                'edit_item' => 'Edit Organiser',
                'new_item' => 'New Organiser',
                'view_item' => 'View Organiser',
                'search_items' => 'Search Organisers',
                'not_found' => 'No organisers found',
                'menu_name' => 'Organisers',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=pavilion_event',
            'show_in_rest' => true,
            'supports' => [
                'title',
                'editor',
            ],
        ]
    );
}
add_action('init', 'skyrocket_events_register_post_types');