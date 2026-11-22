<?php
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