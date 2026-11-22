<?php
/**
 * Render upcoming events list.
 *
 * Usage: [skyrocket_upcoming_events] or [pavilion_upcoming_events]
 * Optional: [skyrocket_upcoming_events count="6"]
 */

defined('ABSPATH') || exit;

function skyrocket_events_upcoming_shortcode($atts): string
{
    $atts = shortcode_atts(
        [
            'count' => 6,
        ],
        $atts,
        'skyrocket_upcoming_events'
    );

    $limit = max(1, (int) $atts['count']);
    $timezone = wp_timezone();
    $today = new DateTimeImmutable('today', $timezone);

    // Look ahead comfortably to capture recurring events (e.g., up to 1 year)
    $first = $today;
    $last = $today->modify('+1 year');
    $month_end_datetime = $last->setTime(23, 59, 59);

    $events = get_posts([
        'post_type' => 'pavilion_event',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);

    $all_occurrences = [];

    foreach ($events as $event) {
        $start_value = get_post_meta($event->ID, '_pavilion_start', true);
        $end_value = get_post_meta($event->ID, '_pavilion_end', true);

        if (!skyrocket_events_valid_datetime($end_value)) {
            continue;
        }

        $frequency = get_post_meta($event->ID, '_pavilion_frequency', true) ?: 'none';

        if ($frequency !== 'monthly_nth' && !skyrocket_events_valid_datetime($start_value)) {
            continue;
        }

        $original_start = $frequency === 'monthly_nth' ? null : DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $start_value,
            $timezone
        );

        $original_end = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $end_value,
            $timezone
        );

        if (!$original_end) {
            continue;
        }

        $duration = 3600;
        if ($original_start && $original_end > $original_start) {
            $duration = $original_end->getTimestamp() - $original_start->getTimestamp();
        }

        $until_value = get_post_meta($event->ID, '_pavilion_until', true);
        $exceptions = get_post_meta($event->ID, '_pavilion_exceptions', true);

        if (!is_array($exceptions)) {
            $exceptions = [];
        }

        $until = $until_value
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $until_value, $timezone)
            : null;

        if ($frequency === 'monthly_nth') {
            $nth_week = (int) (get_post_meta($event->ID, '_pavilion_nth_week', true) ?: 1);
            $nth_weekday = (int) (get_post_meta($event->ID, '_pavilion_nth_weekday', true) ?: 2);
            $start_time_str = $start_value ? (DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone)?->format('H:i') ?: '09:00') : '09:00';

            $sequence_start = $start_value ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone) : null;
            $sequence_start_date = $sequence_start ? $sequence_start->setTime(0, 0) : null;

            $iter_month = $first->modify('first day of this month');
            while ($iter_month <= $last) {
                $ym_str = $iter_month->format('Y-m');
                $target_date_str = skyrocket_get_nth_occurrence_date($ym_str, $nth_weekday, $nth_week, $timezone);

                if ($target_date_str) {
                    $occurrence_date = DateTimeImmutable::createFromFormat('!Y-m-d', $target_date_str, $timezone);
                    if ($occurrence_date && $occurrence_date >= $first && $occurrence_date <= $last) {
                        if ($sequence_start_date && $occurrence_date < $sequence_start_date) {
                            $iter_month = $iter_month->modify('+1 month');
                            continue;
                        }
                        if (!$until || $occurrence_date <= $until) {
                            $original_date_key = $occurrence_date->format('Y-m-d');
                            $exception = $exceptions[$original_date_key] ?? null;

                            if (is_array($exception)) {
                                $action = $exception['action'] ?? '';
                                if ($action === 'cancel') {
                                    $iter_month = $iter_month->modify('+1 month');
                                    continue;
                                }
                                if ($action === 'move') {
                                    $new_start = $exception['start'] ?? '';
                                    $new_end = $exception['end'] ?? '';
                                    if (is_string($new_start) && is_string($new_end) && skyrocket_events_valid_datetime($new_start) && skyrocket_events_valid_datetime($new_end)) {
                                        $moved_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $new_start, $timezone);
                                        $moved_end = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $new_end, $timezone);
                                        if ($moved_start && $moved_end && $moved_end > $moved_start && $moved_start >= $today && $moved_start <= $month_end_datetime) {
                                            $all_occurrences[] = [
                                                'title' => get_the_title($event),
                                                'url' => add_query_arg('occurrence', $moved_start->format('Y-m-d'), get_permalink($event)),
                                                'start' => $moved_start,
                                                'end' => $moved_end,
                                                'venue' => get_post_meta($event->ID, '_pavilion_venue', true),
                                            ];
                                        }
                                    }
                                    $iter_month = $iter_month->modify('+1 month');
                                    continue;
                                }
                            }

                            $occurrence_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $target_date_str . 'T' . $start_time_str, $timezone);
                            if ($occurrence_start && $occurrence_start >= $today->setTime(0, 0)) {
                                $occurrence_end = $occurrence_start->modify('+' . $duration . ' seconds');
                                $all_occurrences[] = [
                                    'title' => get_the_title($event),
                                    'url' => add_query_arg('occurrence', $original_date_key, get_permalink($event)),
                                    'start' => $occurrence_start,
                                    'end' => $occurrence_end,
                                    'venue' => get_post_meta($event->ID, '_pavilion_venue', true),
                                ];
                            }
                        }
                    }
                }
                $iter_month = $iter_month->modify('+1 month');
            }
            continue;
        }

        // Standard frequencies loop
        for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
            if ($date < $original_start->setTime(0, 0)) {
                continue;
            }

            if ($until && $date > $until) {
                continue;
            }

            $day_difference = (int) $original_start->setTime(0, 0)->diff($date)->format('%a');
            $matches = false;

            switch ($frequency) {
                case 'weekly':
                    $matches = $day_difference % 7 === 0;
                    break;
                case 'fortnightly':
                    $matches = $day_difference % 14 === 0;
                    break;
                case 'monthly':
                    $matches = $date->format('d') === $original_start->format('d');
                    break;
                case 'none':
                default:
                    $matches = $date->format('Y-m-d') === $original_start->format('Y-m-d');
                    break;
            }

            if (!$matches) {
                continue;
            }

            $original_date_key = $date->format('Y-m-d');
            $exception = $exceptions[$original_date_key] ?? null;

            if (is_array($exception)) {
                $action = $exception['action'] ?? '';
                if ($action === 'cancel') {
                    continue;
                }
                if ($action === 'move') {
                    $new_start = $exception['start'] ?? '';
                    $new_end = $exception['end'] ?? '';

                    if (is_string($new_start) && is_string($new_end) && skyrocket_events_valid_datetime($new_start) && skyrocket_events_valid_datetime($new_end)) {
                        $moved_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $new_start, $timezone);
                        $moved_end = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $new_end, $timezone);

                        if ($moved_start && $moved_end && $moved_end > $moved_start && $moved_start >= $today) {
                            $all_occurrences[] = [
                                'title' => get_the_title($event),
                                'url' => add_query_arg('occurrence', $moved_start->format('Y-m-d'), get_permalink($event)),
                                'start' => $moved_start,
                                'end' => $moved_end,
                                'venue' => get_post_meta($event->ID, '_pavilion_venue', true),
                            ];
                        }
                    }
                    continue;
                }
            }

            $occurrence_start = $date->setTime((int) $original_start->format('H'), (int) $original_start->format('i'));
            if ($occurrence_start >= $today->setTime(0, 0)) {
                $occurrence_end = $occurrence_start->modify('+' . $duration . ' seconds');
                $all_occurrences[] = [
                    'title' => get_the_title($event),
                    'url' => add_query_arg('occurrence', $original_date_key, get_permalink($event)),
                    'start' => $occurrence_start,
                    'end' => $occurrence_end,
                    'venue' => get_post_meta($event->ID, '_pavilion_venue', true),
                ];
            }
        }
    }

    // Sort all collected future occurrences chronologically
    usort($all_occurrences, static function (array $a, array $b): int {
        return $a['start'] <=> $b['start'];
    });

    $upcoming_items = array_slice($all_occurrences, 0, $limit);

    ob_start();
    ?>
    <?php if (empty($upcoming_items)): ?>
        <p>No upcoming events scheduled.</p>
    <?php else: ?>
        <ul class="ecs-event-list">
            <?php foreach ($upcoming_items as $item): ?>
                <?php
                $is_today = $item['start']->format('Y-m-d') === $today->format('Y-m-d');
                $icon_class = $is_today ? 'cal-icon cal-icon-today' : 'cal-icon';
                ?>
                <li class="ecs-event">
                    <div class="<?php echo esc_attr($icon_class); ?>">
                        <div class="cal-month"><?php echo esc_html(strtoupper($item['start']->format('M'))); ?></div>
                        <div class="cal-day"><?php echo esc_html($item['start']->format('j')); ?></div>
                        <div class="cal-weekday"><?php echo esc_html(strtoupper($item['start']->format('D'))); ?></div>
                    </div>
                    <div class="event-content-wrapper">
                        <h4 class="entry-title summary">
                            <a href="<?php echo esc_url($item['url']); ?>" rel="bookmark"><?php echo esc_html($item['title']); ?></a>
                        </h4>
                        <span class="duration time">
                            <span class="tribe-event-date-start"><?php echo esc_html($item['start']->format('j F @ g:i\&\n\b\s\p\;a')); ?></span> –
                            <span class="tribe-event-time"><?php echo esc_html($item['end']->format('g:i\&\n\b\s\p\;a')); ?></span>
                        </span>
                        <?php if (!empty($item['venue'])): ?>
                            <span class="duration venue"><em> at </em><?php echo esc_html($item['venue']); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}
add_shortcode('pavilion_upcoming_events', 'skyrocket_events_upcoming_shortcode');
add_shortcode('skyrocket_upcoming_events', 'skyrocket_events_upcoming_shortcode');