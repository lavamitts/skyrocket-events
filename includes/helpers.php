<?php

function skyrocket_get_site_details(): array
{
    return [
        'title' => get_bloginfo('name'),
        'slug' => sanitize_title(get_bloginfo('name')),
    ];
}

function is_event_private(string $title): bool
{
    if (str_contains(strtolower($title), "private")) {
        return (true);
    } else {
        return (false);
    }
}

/**
 * Get the date string for the nth occurrence of a weekday in a given month.
 * If the nth week exceeds the actual number of weeks in that month, returns null.
 */
function skyrocket_get_nth_occurrence_date(string $year_month, int $weekday, int $n, DateTimeZone $timezone): ?string
{
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $year_month . '-01', $timezone);
    if (!$first) {
        return null;
    }

    $first_weekday = (int) $first->format('N'); // 1 (Mon) to 7 (Sun)
    $day_offset = ($weekday - $first_weekday + 7) % 7;
    $target_day = 1 + $day_offset + ($n - 1) * 7;

    $days_in_month = (int) $first->format('t');
    if ($target_day > $days_in_month) {
        return null;
    }

    return $first->setDate((int) $first->format('Y'), (int) $first->format('m'), $target_day)->format('Y-m-d');
}


/**
 * Render an event's excerpt for list or day calendar views if enabled.
 *
 * @param int|WP_Post $event Event post ID or object.
 * @param string      $current_view Current calendar view ('grid', 'list', or 'day').
 * @param array       $settings Calendar settings array.
 */
function skyrocket_events_render_calendar_excerpt($event, string $current_view, array $settings): void
{
    if (empty($settings['show_excerpt'])) {
        return;
    }

    if (!in_array($current_view, ['list', 'day'], true)) {
        return;
    }

    $excerpt = get_the_excerpt($event);
    if ('' === $excerpt) {
        return;
    }

    echo '<div class="skyrocket-calendar__excerpt">' . wp_kses_post(wpautop($excerpt)) . '</div>';
}