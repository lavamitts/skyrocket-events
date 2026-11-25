<?php
defined('ABSPATH') || exit;

/**
 * Human-readable labels for repeat frequencies.
 *
 * @return array<string, string>
 */
function skyrocket_events_frequency_labels(): array
{
    return [
        'none' => 'Does not repeat',
        'weekly' => 'Weekly',
        'fortnightly' => 'Fortnightly',
        'monthly' => 'Monthly (same date)',
        'monthly_nth' => 'Monthly (nth weekday)',
        'yearly' => 'Yearly',
    ];
}

/**
 * Format stored local datetime strings in UK format for admin display.
 */
function skyrocket_events_format_admin_datetime(string $value): string
{
    if ($value === '') {
        return '—';
    }

    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
    if (!$dt) {
        return esc_html($value);
    }

    return esc_html($dt->format('d/m/Y H:i'));
}

/**
 * Register custom columns for the pavilion_event admin list table.
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function skyrocket_events_manage_columns(array $columns): array
{
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'title') {
            $new_columns['skyrocket_repeat_type'] = 'Repeat type';
            $new_columns['skyrocket_start'] = 'Event start date / time';
            $new_columns['skyrocket_end'] = 'Event end date / time';
            $new_columns['skyrocket_all_day'] = 'All day event?';
        }
    }

    return $new_columns;
}
add_filter('manage_pavilion_event_posts_columns', 'skyrocket_events_manage_columns');

/**
 * Render cell values for custom pavilion_event columns.
 */
function skyrocket_events_render_column(string $column, int $post_id): void
{
    switch ($column) {
        case 'skyrocket_repeat_type':
            $frequency = (string) (get_post_meta($post_id, '_pavilion_frequency', true) ?: 'none');
            $labels = skyrocket_events_frequency_labels();
            echo esc_html($labels[$frequency] ?? $frequency);
            break;

        case 'skyrocket_start':
            $start = (string) get_post_meta($post_id, '_pavilion_start', true);
            $is_all_day = get_post_meta($post_id, '_pavilion_all_day', true) === '1';
            if ($is_all_day && $start !== '') {
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, wp_timezone());
                echo $dt ? esc_html($dt->format('d/m/Y')) : esc_html($start);
            } else {
                echo skyrocket_events_format_admin_datetime($start);
            }
            break;

        case 'skyrocket_end':
            $end = (string) get_post_meta($post_id, '_pavilion_end', true);
            $is_all_day = get_post_meta($post_id, '_pavilion_all_day', true) === '1';
            if ($is_all_day && $end !== '') {
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, wp_timezone());
                echo $dt ? esc_html($dt->format('d/m/Y')) : esc_html($end);
            } else {
                echo skyrocket_events_format_admin_datetime($end);
            }
            break;

        case 'skyrocket_all_day':
            $is_all_day = get_post_meta($post_id, '_pavilion_all_day', true) === '1';
            echo esc_html($is_all_day ? 'Yes' : 'No');
            break;
    }
}
add_action('manage_pavilion_event_posts_custom_column', 'skyrocket_events_render_column', 10, 2);

/**
 * Mark custom pavilion_event columns as sortable.
 *
 * @param array<string, string|array<int, string|bool>> $sortable
 * @return array<string, string|array<int, string|bool>>
 */
function skyrocket_events_sortable_columns(array $sortable): array
{
    $sortable['skyrocket_repeat_type'] = 'skyrocket_repeat_type';
    $sortable['skyrocket_start'] = 'skyrocket_start';
    $sortable['skyrocket_end'] = 'skyrocket_end';
    $sortable['skyrocket_all_day'] = 'skyrocket_all_day';

    return $sortable;
}
add_filter('manage_edit-pavilion_event_sortable_columns', 'skyrocket_events_sortable_columns');

/**
 * Sort pavilion_event admin queries by custom post meta while retaining posts without meta.
 */
function skyrocket_events_sort_admin_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') !== 'pavilion_event') {
        return;
    }

    $orderby = $query->get('orderby');

    $meta_map = [
        'skyrocket_repeat_type' => ['key' => '_pavilion_frequency', 'type' => 'CHAR'],
        'skyrocket_start' => ['key' => '_pavilion_start', 'type' => 'CHAR'],
        'skyrocket_end' => ['key' => '_pavilion_end', 'type' => 'CHAR'],
        'skyrocket_all_day' => ['key' => '_pavilion_all_day', 'type' => 'CHAR'],
    ];

    if (!is_string($orderby) || !isset($meta_map[$orderby])) {
        return;
    }

    $config = $meta_map[$orderby];

    // Use a named meta_query clause with EXISTS + NOT EXISTS so events lacking the meta key
    // are still returned rather than filtered out by an inner join.
    $existing_meta_query = $query->get('meta_query');
    $meta_query = is_array($existing_meta_query) ? $existing_meta_query : [];

    $meta_query['skyrocket_sort_clause'] = [
        'relation' => 'OR',
        [
            'key' => $config['key'],
            'compare' => 'EXISTS',
            'type' => $config['type'],
        ],
        [
            'key' => $config['key'],
            'compare' => 'NOT EXISTS',
        ],
    ];

    $query->set('meta_query', $meta_query);
    $query->set('orderby', 'skyrocket_sort_clause');
}
add_action('pre_get_posts', 'skyrocket_events_sort_admin_query');
