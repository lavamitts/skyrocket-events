<?php
/**
 * UI helper functions for the Pavilion Events single event template.
 */

defined('ABSPATH') || exit;

/**
 * Find the exception datetime fields.
 */
function pavilion_ui_get_exception_datetime(
    array $exception,
    string $which,
    DateTimeZone $timezone
) {
    $keys = $which === 'start'
        ? ['replacement_start', 'new_start', 'moved_start', 'start']
        : ['replacement_end', 'new_end', 'moved_end', 'end'];

    foreach ($keys as $key) {
        if (empty($exception[$key])) {
            continue;
        }

        $value = $exception[$key];

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $value,
            $timezone
        );

        if (!$date) {
            try {
                $date = new DateTimeImmutable($value, $timezone);
            } catch (Exception $e) {
                $date = false;
            }
        }

        if ($date) {
            return $date;
        }
    }

    return false;
}

/**
 * Check whether a date belongs to the normal recurrence.
 */
function pavilion_ui_is_scheduled_date(
    string $date,
    ?DateTimeImmutable $start,
    string $frequency,
    ?string $until,
    DateTimeZone $timezone
): bool {
    if (!$start) {
        return false;
    }

    $target = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date,
        $timezone
    );

    if (!$target || $target->format('Y-m-d') !== $date) {
        return false;
    }

    $first = $start->setTime(0, 0);

    if ($target < $first) {
        return false;
    }

    $last = $until
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $until, $timezone)
        : false;

    if ($last && $target > $last) {
        return false;
    }

    if ($frequency === 'none') {
        return $target == $first;
    }

    global $post;
    $event_id = $post ? $post->ID : 0;

    // For monthly_nth, we check month-by-month if the target date matches the calculated Nth occurrence
    if ($frequency === 'monthly_nth') {
        $nth_week = (int) (get_post_meta($event_id, '_pavilion_nth_week', true) ?: 1);
        $nth_weekday = (int) (get_post_meta($event_id, '_pavilion_nth_weekday', true) ?: 2);
        $ym_str = $target->format('Y-m');

        $target_date_str = pavilion_get_nth_occurrence_date($ym_str, $nth_weekday, $nth_week, $timezone);
        return ($target_date_str === $date);
    }

    $candidate = $first;

    for ($i = 0; $i < 10000; $i++) {
        if ($candidate->format('Y-m-d') === $date) {
            return true;
        }

        if ($candidate > $target || ($last && $candidate > $last)) {
            return false;
        }

        switch ($frequency) {
            case 'weekly':
                $candidate = $candidate->modify('+1 week');
                break;

            case 'fortnightly':
                $candidate = $candidate->modify('+2 weeks');
                break;

            case 'monthly':
                $candidate = $candidate->modify('+1 month');
                break;

            default:
                return false;
        }
    }

    return false;
}

/**
 * Generate a Google Calendar link using local times and explicit timezone.
 */
function pavilion_ui_google_calendar_url(
    string $title,
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    string $description
): string {
    $dates = $start->format('Ymd\THis')
        . '/'
        . $end->format('Ymd\THis');

    $timezone_string = wp_timezone_string();

    $params = [
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $dates,
        'ctz' => $timezone_string,
        'details' => wp_strip_all_tags($description),
        'location' => 'Biddenham Pavilion, Deep Spinney, Biddenham, Bedford MK40 4PZ',
    ];

    return 'https://calendar.google.com/calendar/render?'
        . http_build_query($params);
}

/**
 * Generate a download URL for an iCalendar (.ics) file.
 */
function pavilion_ui_ics_download_url(
    int $event_id,
    ?string $occurrence_date = null
): string {
    $args = [
        'action' => 'pavilion_download_ics',
        'event_id' => $event_id,
    ];

    if ($occurrence_date !== null && $occurrence_date !== '') {
        $args['occurrence'] = $occurrence_date;
    }

    return esc_url(
        add_query_arg($args, admin_url('admin-post.php'))
    );
}