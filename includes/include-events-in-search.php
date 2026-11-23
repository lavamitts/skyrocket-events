<?php
/**
 * Include pavilion_event CPT in frontend searches.
 */
add_action('pre_get_posts', function ($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        $query->set('post_type', ['post', 'page', 'pavilion_event']);
    }
});

add_action('pre_get_posts', function ($query) {
    $type = $query->get('bbc_section_post_type');
    if ($type) {
        $query->set('post_type', $type);
    }
}, PHP_INT_MAX);