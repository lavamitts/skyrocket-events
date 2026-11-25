<?php
defined('ABSPATH') || exit;

/**
 * Register a post-like category taxonomy for pavilion_event posts.
 */
function skyrocket_events_register_category_taxonomy(): void
{
    $labels = [
        'name' => 'Event Categories',
        'singular_name' => 'Event Category',
        'search_items' => 'Search Event Categories',
        'popular_items' => 'Popular Event Categories',
        'all_items' => 'All Event Categories',
        'parent_item' => 'Parent Event Category',
        'parent_item_colon' => 'Parent Event Category:',
        'edit_item' => 'Edit Event Category',
        'view_item' => 'View Event Category',
        'update_item' => 'Update Event Category',
        'add_new_item' => 'Add New Event Category',
        'new_item_name' => 'New Event Category Name',
        'separate_items_with_commas' => 'Separate event categories with commas',
        'add_or_remove_items' => 'Add or remove event categories',
        'choose_from_most_used' => 'Choose from the most used event categories',
        'not_found' => 'No event categories found.',
        'no_terms' => 'No event categories',
        'items_list_navigation' => 'Event categories list navigation',
        'items_list' => 'Event categories list',
        'back_to_items' => 'Back to Event Categories',
        'menu_name' => 'Event Categories',
    ];

    register_taxonomy(
        'pavilion_event_category',
        ['pavilion_event'],
        [
            'hierarchical' => true,
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => [
                'slug' => 'event-category',
                'with_front' => false,
                'hierarchical' => true,
            ],
        ]
    );
}
add_action('init', 'skyrocket_events_register_category_taxonomy', 0);

/**
 * Ensure the pavilion_event_category taxonomy is bound to pavilion_event.
 */
function skyrocket_events_bind_category_taxonomy(): void
{
    register_taxonomy_for_object_type('pavilion_event_category', 'pavilion_event');
}
add_action('init', 'skyrocket_events_bind_category_taxonomy', 10);