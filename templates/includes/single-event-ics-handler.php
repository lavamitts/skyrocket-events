<?php
/**
 * Handle iCalendar (.ics) downloads for Pavilion Events.
 */

defined('ABSPATH') || exit;

/**
 * Handle direct .ics file downloads via admin-post.
 */
function pavilion_ui_handle_ics_download(): void
{
    $event_id = isset($_GET['event_id'])
        ? absint($_GET['event_id'])
        : 0;

    if (!$event_id) {
        wp_die('Invalid event ID.', 'Calendar download error', ['response' => 400]);
    }

    // Ensure the requested event exists.
    $event = get_post($event_id);

    if (!$event || $event->post_type !== 'pavilion_event') {
        wp_die('Event not found.', 'Calendar download error', ['response' => 404]);
    }

    $timezone = wp_timezone();

    // Read the original event schedule.
    $start_value = get_post_meta($event_id, '_pavilion_start', true);
    $end_value = get_post_meta($event_id, '_pavilion_end', true);

    $start = pavilion_ui_ics_parse_datetime($start_value, $timezone);
    $end = pavilion_ui_ics_parse_datetime($end_value, $timezone);

    error_log('WP timezone: ' . wp_timezone_string());
    error_log('Stored start: ' . $start_value);
    error_log('Parsed start: ' . $start->format('Y-m-d H:i:s T P'));

    if (!$start || !$end || $end < $start) {
        wp_die(
            'Invalid event schedule.',
            'Calendar download error',
            ['response' => 500]
        );
    }

    // If an occurrence date was supplied, validate and apply it.
    $occurrence_date = isset($_GET['occurrence'])
        ? sanitize_text_field(wp_unslash($_GET['occurrence']))
        : '';

    if ($occurrence_date !== '') {
        $selected_date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $occurrence_date,
            $timezone
        );

        $date_errors = DateTimeImmutable::getLastErrors();

        if (
            !$selected_date
            || ($date_errors !== false
                && ($date_errors['warning_count'] > 0
                    || $date_errors['error_count'] > 0))
            || $selected_date->format('Y-m-d') !== $occurrence_date
        ) {
            wp_die(
                'Invalid event occurrence date.',
                'Calendar download error',
                ['response' => 400]
            );
        }

        // Preserve the original event duration and local start time.
        $duration = $end->getTimestamp() - $start->getTimestamp();

        $start = $selected_date->setTime(
            (int) $start->format('H'),
            (int) $start->format('i'),
            (int) $start->format('s')
        );

        $end = $start->modify('+' . $duration . ' seconds');
    }

    $title = get_the_title($event_id);

    $content = get_post_field('post_content', $event_id);
    $description = wp_strip_all_tags(strip_shortcodes($content));

    $location = 'Biddenham Pavilion, Deep Spinney, Biddenham, Bedford MK40 4PZ';

    // Build the calendar content before sending headers.
    // $utc = new DateTimeZone('UTC');

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Pavilion Events//UI//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    // New timezone start
    $ics .= "BEGIN:VTIMEZONE\r\n";
    $ics .= "TZID:Europe/London\r\n";
    $ics .= "BEGIN:DAYLIGHT\r\n";
    $ics .= "TZOFFSETFROM:+0000\r\n";
    $ics .= "TZOFFSETTO:+0100\r\n";
    $ics .= "TZNAME:BST\r\n";
    $ics .= "DTSTART:19810329T010000\r\n";
    $ics .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
    $ics .= "END:DAYLIGHT\r\n";
    $ics .= "BEGIN:STANDARD\r\n";
    $ics .= "TZOFFSETFROM:+0100\r\n";
    $ics .= "TZOFFSETTO:+0000\r\n";
    $ics .= "TZNAME:GMT\r\n";
    $ics .= "DTSTART:19961027T020000\r\n";
    $ics .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
    $ics .= "END:STANDARD\r\n";
    $ics .= "END:VTIMEZONE\r\n";
    // New timezone end
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= 'UID:pavilion-event-' . $event_id
        . ($occurrence_date !== '' ? '-' . $occurrence_date : '')
        . '@' . wp_parse_url(home_url(), PHP_URL_HOST) . "\r\n";
    $ics .= 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";

    // $ics .= 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z') . "\r\n";
    // $ics .= 'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z') . "\r\n";

    $timezone = new DateTimeZone('Europe/London');
    $ics .= 'DTSTART;TZID=' . $timezone->getName() . ':' . $start->setTimezone($timezone)->format('Ymd\THis') . "\r\n";
    $ics .= 'DTEND;TZID=' . $timezone->getName() . ':' . $end->setTimezone($timezone)->format('Ymd\THis') . "\r\n";

    $ics .= 'SUMMARY:' . pavilion_ui_ics_escape($title) . "\r\n";
    $ics .= 'LOCATION:' . pavilion_ui_ics_escape($location) . "\r\n";
    $ics .= 'DESCRIPTION:' . pavilion_ui_ics_escape($description) . "\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";

    // Clear all output buffers to prevent HTML contaminating the file.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    nocache_headers();

    header('Content-Type: text/calendar; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="pavilion-event-'
        . $event_id . '.ics"'
    );
    header('Content-Length: ' . strlen($ics));
    header('X-Content-Type-Options: nosniff');

    echo $ics;
    exit;
}

/**
 * Parse a stored event date/time in the WordPress timezone.
 *
 * Accepts the expected Y-m-d\TH:i format, with a fallback for
 * other standard date/time formats.
 */
function pavilion_ui_ics_parse_datetime($value, DateTimeZone $timezone)
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $value,
        $timezone
    );

    $errors = DateTimeImmutable::getLastErrors();

    if (
        $date
        && (
            $errors === false
            || (
                $errors['warning_count'] === 0
                && $errors['error_count'] === 0
            )
        )
        && $date->format('Y-m-d\TH:i') === $value
    ) {
        return $date;
    }

    try {
        return new DateTimeImmutable($value, $timezone);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Escape text according to iCalendar TEXT rules.
 */
function pavilion_ui_ics_escape(string $value): string
{
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(';', '\\;', $value);
    $value = str_replace(',', '\\,', $value);
    $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);

    return $value;
}

// Register handlers for logged-out visitors and logged-in users.
add_action(
    'admin_post_nopriv_pavilion_download_ics',
    'pavilion_ui_handle_ics_download'
);

add_action(
    'admin_post_pavilion_download_ics',
    'pavilion_ui_handle_ics_download'
);
