<?php
/**
 * Shortcodes and calendar rendering logic.
 */

defined('ABSPATH') || exit;

/**
 * Render calendar or event list.
 *
 * Usage: [skyrocket_calendar] or [pavilion_calendar]
 * Optional: [skyrocket_calendar month="2026-01" mode="compact"]
 */
function skyrocket_events_calendar_shortcode($atts): string
{
    $PREVIOUS_ARROW = "&#9664;";
    $NEXT_ARROW = "&#9658;";

    $atts = shortcode_atts(
        [
            'month' => '',
            'mode' => '',
        ],
        $atts,
        'skyrocket_calendar'
    );

    $is_compact = ($atts['mode'] === 'compact');

    $timezone = wp_timezone();
    $today = new DateTimeImmutable('today', $timezone);

    if ($is_compact) {
        $view = 'list';
    } else {
        $view = isset($_GET['skyrocket_view'])
            ? sanitize_key(wp_unslash($_GET['skyrocket_view']))
            : '';

        if (!in_array($view, ['grid', 'list', 'day'], true)) {
            $view = 'grid';
        }
    }

    $requested_month = isset($_GET['skyrocket_month'])
        ? sanitize_text_field(
            wp_unslash($_GET['skyrocket_month'])
        )
        : $atts['month'];

    if (
        !preg_match(
            '/^\d{4}-(0[1-9]|1[0-2])$/',
            $requested_month
        )
    ) {
        $requested_month = $today->format('Y-m');
    }

    $requested_date = isset($_GET['skyrocket_date'])
        ? sanitize_text_field(wp_unslash($_GET['skyrocket_date']))
        : $today->format('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested_date)) {
        $requested_date = $today->format('Y-m-d');
    }

    $day_target = DateTimeImmutable::createFromFormat('!Y-m-d', $requested_date, $timezone);
    if (!$day_target) {
        $day_target = $today;
    }

    if ($view === 'day' && !$is_compact) {
        $first = $day_target;
        $last = $day_target;
    } else {
        $first = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $requested_month . '-01',
            $timezone
        );

        if (!$first) {
            $first = $today->modify('first day of this month');
        }

        $last = $first->modify('last day of this month');
    }

    $month_start_key = $first->format('Y-m-d');
    $month_end_key = $last->format('Y-m-d');
    $month_end_datetime = $last->setTime(23, 59, 59);

    $events = get_posts([
        'post_type' => 'pavilion_event',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);

    $days = [];

    foreach ($events as $event) {
        $start_value = get_post_meta(
            $event->ID,
            '_pavilion_start',
            true
        );

        $end_value = get_post_meta(
            $event->ID,
            '_pavilion_end',
            true
        );

        if (
            !skyrocket_events_valid_datetime($end_value)
        ) {
            continue;
        }

        $frequency = get_post_meta(
            $event->ID,
            '_pavilion_frequency',
            true
        ) ?: 'none';

        if ($frequency !== 'monthly_nth' && !skyrocket_events_valid_datetime($start_value)) {
            continue;
        }

        $original_start = $start_value ? DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $start_value,
            $timezone
        ) : null;

        $original_end = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $end_value,
            $timezone
        );

        if (!$original_end) {
            continue;
        }

        // Calculate correct duration. For monthly_nth, start_value provides the time component
        // and end_value provides the end time component on the base date.
        $duration = 3600;
        if ($original_start && $original_end > $original_start) {
            $duration = $original_end->getTimestamp() - $original_start->getTimestamp();
        } elseif ($frequency === 'monthly_nth' && $start_value) {
            // Fallback calculation for monthly_nth if original_start object wasn't built yet
            $temp_start_time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone);
            $temp_end_time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end_value, $timezone);
            if ($temp_start_time && $temp_end_time && $temp_end_time > $temp_start_time) {
                $duration = $temp_end_time->getTimestamp() - $temp_start_time->getTimestamp();
            }
        }

        $until_value = get_post_meta(
            $event->ID,
            '_pavilion_until',
            true
        );

        $exceptions = get_post_meta(
            $event->ID,
            '_pavilion_exceptions',
            true
        );

        if (!is_array($exceptions)) {
            $exceptions = [];
        }

        $until = $until_value
            ? DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $until_value,
                $timezone
            )
            : null;

        if ($frequency === 'monthly_nth') {
            $nth_week = (int) (get_post_meta($event->ID, '_pavilion_nth_week', true) ?: 1);
            $nth_weekday = (int) (get_post_meta($event->ID, '_pavilion_nth_weekday', true) ?: 2);
            $start_time_str = $start_value ? (DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone)?->format('H:i') ?: '09:00') : '09:00';

            $sequence_start = $start_value ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone) : null;
            $sequence_start_date = $sequence_start ? $sequence_start->setTime(0, 0) : null;

            // Loop through each month present in the view range
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

                            // Handle exception logic
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
                                        if ($moved_start && $moved_end && $moved_end > $moved_start && $moved_start >= $first && $moved_start <= $month_end_datetime) {
                                            $moved_date_key = $moved_start->format('Y-m-d');
                                            $days[$moved_date_key][] = [
                                                'post_id' => $event->ID,
                                                'title' => get_the_title($event),
                                                'url' => add_query_arg('occurrence', $moved_date_key, get_permalink($event)),
                                                'start' => $moved_start,
                                                'end' => $moved_end,
                                            ];
                                        }
                                    }
                                    $iter_month = $iter_month->modify('+1 month');
                                    continue;
                                }
                            }

                            $occurrence_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $target_date_str . 'T' . $start_time_str, $timezone);
                            if ($occurrence_start) {
                                $occurrence_end = $occurrence_start->modify('+' . $duration . ' seconds');
                                $days[$original_date_key][] = [
                                    'post_id' => $event->ID,
                                    'title' => get_the_title($event),
                                    'url' => add_query_arg('occurrence', $original_date_key, get_permalink($event)),
                                    'start' => $occurrence_start,
                                    'end' => $occurrence_end,
                                ];
                            }
                        }
                    }
                }
                $iter_month = $iter_month->modify('+1 month');
            }

            // Check for moved exceptions falling into this month whose original dates might be outside
            foreach ($exceptions as $exception_date => $exception) {
                if (!is_array($exception) || ($exception['action'] ?? '') !== 'move') {
                    continue;
                }
                $new_start = $exception['start'] ?? '';
                $new_end = $exception['end'] ?? '';
                if (!is_string($new_start) || !is_string($new_end) || !skyrocket_events_valid_datetime($new_start) || !skyrocket_events_valid_datetime($new_end)) {
                    continue;
                }
                $moved_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $new_start, $timezone);
                $moved_end = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $new_end, $timezone);
                if (!$moved_start || !$moved_end || $moved_end <= $moved_start || $moved_start < $first || $moved_start > $month_end_datetime) {
                    continue;
                }
                $moved_date_key = $moved_start->format('Y-m-d');
                $already_added = false;
                foreach ($days[$moved_date_key] ?? [] as $existing_item) {
                    if ($existing_item['title'] === get_the_title($event) && $existing_item['start'] == $moved_start) {
                        $already_added = true;
                        break;
                    }
                }
                if (!$already_added) {
                    $days[$moved_date_key][] = [
                        'title' => get_the_title($event),
                        'url' => add_query_arg('occurrence', $moved_date_key, get_permalink($event)),
                        'start' => $moved_start,
                        'end' => $moved_end,
                    ];
                }
            }

            continue;
        }

        // Standard frequencies loop
        for (
            $date = $first;
            $date <= $last;
            $date = $date->modify('+1 day')
        ) {
            if ($date < $original_start->setTime(0, 0)) {
                continue;
            }

            if ($until && $date > $until) {
                continue;
            }

            $day_difference = (int) $original_start
                ->setTime(0, 0)
                ->diff($date)
                ->format('%a');

            $matches = false;

            switch ($frequency) {
                case 'weekly':
                    $matches = $day_difference % 7 === 0;
                    break;

                case 'fortnightly':
                    $matches = $day_difference % 14 === 0;
                    break;

                case 'monthly':
                    $matches = $date->format('d')
                        === $original_start->format('d');
                    break;

                case 'none':
                default:
                    $matches = $date->format('Y-m-d')
                        === $original_start->format('Y-m-d');
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

                    if (
                        !is_string($new_start)
                        || !is_string($new_end)
                        || !skyrocket_events_valid_datetime($new_start)
                        || !skyrocket_events_valid_datetime($new_end)
                    ) {
                        continue;
                    }

                    $moved_start = DateTimeImmutable::createFromFormat(
                        '!Y-m-d\TH:i',
                        $new_start,
                        $timezone
                    );

                    $moved_end = DateTimeImmutable::createFromFormat(
                        '!Y-m-d\TH:i',
                        $new_end,
                        $timezone
                    );

                    if (
                        !$moved_start
                        || !$moved_end
                        || $moved_end <= $moved_start
                    ) {
                        continue;
                    }

                    if (
                        $moved_start >= $first
                        && $moved_start <= $month_end_datetime
                    ) {
                        $moved_date_key = $moved_start->format('Y-m-d');

                        $days[$moved_date_key][] = [
                            'title' => get_the_title($event),
                            'url' => add_query_arg(
                                'occurrence',
                                $moved_date_key,
                                get_permalink($event)
                            ),
                            'start' => $moved_start,
                            'end' => $moved_end,
                        ];
                    }

                    continue;
                }
            }

            $occurrence_start = $date->setTime(
                (int) $original_start->format('H'),
                (int) $original_start->format('i')
            );

            $occurrence_end = $occurrence_start->modify(
                '+' . $duration . ' seconds'
            );

            $days[$original_date_key][] = [
                'title' => get_the_title($event),
                'url' => add_query_arg(
                    'occurrence',
                    $original_date_key,
                    get_permalink($event)
                ),
                'start' => $occurrence_start,
                'end' => $occurrence_end,
            ];
        }

        foreach ($exceptions as $exception_date => $exception) {
            if (
                !is_array($exception)
                || ($exception['action'] ?? '') !== 'move'
                || !is_string($exception_date)
                || (
                    $exception_date >= $month_start_key
                    && $exception_date <= $month_end_key
                )
            ) {
                continue;
            }

            $new_start = $exception['start'] ?? '';
            $new_end = $exception['end'] ?? '';

            if (
                !is_string($new_start)
                || !is_string($new_end)
                || !skyrocket_events_valid_datetime($new_start)
                || !skyrocket_events_valid_datetime($new_end)
            ) {
                continue;
            }

            $moved_start = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                $new_start,
                $timezone
            );

            $moved_end = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                $new_end,
                $timezone
            );

            if (
                !$moved_start
                || !$moved_end
                || $moved_end <= $moved_start
                || $moved_start < $first
                || $moved_start > $month_end_datetime
            ) {
                continue;
            }

            $moved_date_key = $moved_start->format('Y-m-d');

            $days[$moved_date_key][] = [
                'title' => get_the_title($event),
                'url' => add_query_arg(
                    'occurrence',
                    $moved_date_key,
                    get_permalink($event)
                ),
                'start' => $moved_start,
                'end' => $moved_end,
            ];
        }
    }

    foreach ($days as &$day_events) {
        usort(
            $day_events,
            static function (array $a, array $b): int {
                return $a['start'] <=> $b['start'];
            }
        );
    }
    unset($day_events);

    ksort($days);

    $base_url = remove_query_arg(['skyrocket_month', 'skyrocket_date', 'skyrocket_view']);
    $grid_url = add_query_arg(['skyrocket_view' => 'grid', 'skyrocket_month' => $first->format('Y-m')], $base_url);
    $list_url = add_query_arg(['skyrocket_view' => 'list', 'skyrocket_month' => $first->format('Y-m')], $base_url);
    $day_url = add_query_arg(['skyrocket_view' => 'day', 'skyrocket_date' => $today->format('Y-m-d')], $base_url);

    if ($view === 'day' && !$is_compact) {
        $previous_url = add_query_arg([
            'skyrocket_view' => 'day',
            'skyrocket_date' => $first->modify('-1 day')->format('Y-m-d'),
        ], $base_url);

        $next_url = add_query_arg([
            'skyrocket_view' => 'day',
            'skyrocket_date' => $first->modify('+1 day')->format('Y-m-d'),
        ], $base_url);
    } else {
        $previous_url = add_query_arg([
            'skyrocket_view' => $view,
            'skyrocket_month' => $first->modify('-1 month')->format('Y-m'),
        ], $base_url);

        $next_url = add_query_arg([
            'skyrocket_view' => $view,
            'skyrocket_month' => $first->modify('+1 month')->format('Y-m'),
        ], $base_url);
    }

    ob_start();
    ?>

    <?php if (!$is_compact && !isset($_GET['skyrocket_view'])): ?>
        <script>
            if (window.innerWidth <= 689) {
                const url = new URL(window.location.href);
                url.searchParams.set('skyrocket_view', 'list');
                window.location.replace(url.toString());
            }
        </script>
    <?php endif; ?>

    <div
        class="skyrocket-calendar skyrocket-calendar--<?php echo esc_attr($view); ?><?php echo $is_compact ? ' skyrocket-calendar--compact' : ''; ?>">
        <?php if (!$is_compact): ?>
            <div class="skyrocket-calendar__views">
                <a id="month-grid-button" href="<?php echo esc_url($grid_url); ?>"
                    class="button <?php echo $view === 'grid' ? 'button-primary' : ''; ?>">Month view</a>
                <a id="month-list-button" href="<?php echo esc_url($list_url); ?>"
                    class="button <?php echo $view === 'list' ? 'button-primary' : ''; ?>">List view</a>
                <a id="day-list-button" href="<?php echo esc_url($day_url); ?>"
                    class="button <?php echo $view === 'day' ? 'button-primary' : ''; ?>">Daily view</a>
            </div>
        <?php endif; ?>

        <?php if ($view === 'day' && !$is_compact): ?>
            <div class="skyrocket-calendar__nav">
                <a href="<?php echo esc_url($previous_url); ?>" aria-label="Previous day" class="previous-day"><?= $PREVIOUS_ARROW ?> Previous
                    day</a>
                <strong>
                    <?php echo esc_html($first->format('l, j F Y')); ?>
                </strong>
                <a href="<?php echo esc_url($next_url); ?>" aria-label="Next day" class="next-day">Next day <?= $NEXT_ARROW ?></a>
            </div>

            <div class="skyrocket-calendar__day-view" style="margin-top: 1.5em;">
                <?php $day_key = $first->format('Y-m-d'); ?>
                <?php if (empty($days[$day_key])): ?>
                    <p>No events scheduled for this day.</p>
                <?php else: ?>
                    <h2>Events scheduled for <?php echo esc_html($first->format('l, j F Y')); ?></h2>
                    <ul class="skyrocket-calendar__list list-view">
                        <?php foreach ($days[$day_key] as $item): ?>

                            <li>
                                <?php $is_event_private = is_event_private($item['title']); ?>
                                <?php if ($is_event_private) { ?>
                                    <strong>
                                        <?php echo esc_html($item['start']->format('H:i') . ' - ' . $item['end']->format('H:i')); ?>
                                    </strong>:
                                    <span class="private-event"><?php echo esc_html($item['title']); ?></span>

                                <?php } else { ?>
                                    <a href="<?php echo esc_url($item['url']); ?>" style="text-decoration: none; color: inherit;">
                                        <strong>
                                            <?php echo esc_html($item['start']->format('H:i') . ' - ' . $item['end']->format('H:i')); ?>
                                        </strong>:
                                        <?php echo esc_html($item['title']); ?>
                                    </a>

                                <?php } ?>

                                <?php
                                if (!empty($item['post_id'])) {
                                    $event_excerpt = get_the_excerpt($item['post_id']);
                                    if (!empty($event_excerpt)) {
                                        echo '<div class="skyrocket-event-excerpt" style="margin-top: 0.25em; font-size: 0.9em; opacity: 0.85;">' . esc_html($event_excerpt) . '</div>';
                                    }
                                }
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        <?php elseif ($view === 'list'): ?>
            <div class="skyrocket-calendar__nav">
                <a href="<?php echo esc_url($previous_url); ?>" aria-label="Previous month" class="previous-month"><?= $PREVIOUS_ARROW ?> Previous month</a>
                <strong>
                    <?php echo esc_html($first->format('F Y')); ?>
                </strong>
                <a href="<?php echo esc_url($next_url); ?>" aria-label="Next month" class="next-month">Next month <?= $NEXT_ARROW ?></a>
            </div>

            <div class="skyrocket-calendar__list-view" style="margin-top: 1.5em;">
                <?php
                $today_string = $today->format('Y-m-d');
                if ($is_compact) {
                    $today_string = $today->format('Y-m-d');
                    $limit_string = $today->modify('+6 days')->format('Y-m-d');

                    $days = array_filter($days, static function ($date_key) use ($today_string, $limit_string) {
                        return $date_key >= $today_string && $date_key <= $limit_string;
                    }, ARRAY_FILTER_USE_KEY);
                }
                ?>
                <?php if (empty($days)): ?>
                    <p>No upcoming events scheduled for this month.</p>
                <?php else: ?>
                    <?php foreach ($days as $date_key => $day_events): ?>
                        <?php $day_dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date_key, $timezone); ?>
                        <div class="skyrocket-calendar__list-group">
                            <h4>
                                <?php echo esc_html($day_dt ? $day_dt->format('l, j F Y') : $date_key); ?>
                            </h4>
                            <ul class="list-view">
                                <?php foreach ($day_events as $item): ?>
                                    <li>
                                        <?php $is_event_private = is_event_private($item['title']); ?>
                                        <?php if ($is_event_private) { ?>
                                            <strong>
                                                <?php echo esc_html($item['start']->format('H:i') . ' - ' . $item['end']->format('H:i')); ?>
                                            </strong>:
                                            <span class="private-event">
                                                <?php echo esc_html($item['title']); ?>
                                            </span>

                                        <?php } else { ?>
                                            <a href="<?php echo esc_url($item['url']); ?>" style="text-decoration: none; color: inherit;">
                                                <strong>
                                                    <?php echo esc_html($item['start']->format('H:i') . ' - ' . $item['end']->format('H:i')); ?>
                                                </strong>:
                                                <span>
                                                    <?php echo esc_html($item['title']); ?>
                                                </span>
                                            </a>
                                        <?php } ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="skyrocket-calendar__nav">
                <a href="<?php echo esc_url($previous_url); ?>" aria-label="Previous month" class="xprevious-month"><?= $PREVIOUS_ARROW ?></a>
                <strong>
                    <?php echo esc_html($first->format('F Y')); ?>
                </strong>
                <a href="<?php echo esc_url($next_url); ?>" aria-label="Next month" class="xnext-month"><?= $NEXT_ARROW ?></a>
            </div>

            <table class="skyrocket-calendar__grid">
                <thead>
                    <tr>
                        <?php foreach (
                            [
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                                'Sun'
                            ] as $weekday
                        ): ?>
                            <th scope="col">
                                <?php echo esc_html($weekday); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $dateNowString = date('Y-m-d');
                    $offset = (int) $first->format('N') - 1;
                    $number_of_days = (int) $last->format('j');
                    $cells = (int) ceil(
                        ($offset + $number_of_days) / 7
                    ) * 7;
                    ?>
                    <?php for ($cell = 0; $cell < $cells; $cell++): ?>
                        <?php
                        $day_number = $cell - $offset + 1;
                        $date_key = $first->format('Y-m-') . sprintf('%02d', $day_number);
                        if ($date_key == $dateNowString) {
                            $cell_class = "today";
                            $day_string = " (today)";
                        } else {
                            $cell_class = "";
                            $day_string = "";
                        }
                        ?>

                        <?php if ($cell % 7 === 0): ?>
                            <tr>
                            <?php endif; ?>

                            <td class="<?= $cell_class ?>">
                                <?php if (
                                    $day_number >= 1
                                    && $day_number <= $number_of_days
                                ): ?>

                                    <?php
                                    $cell_day_url = add_query_arg([
                                        'skyrocket_view' => 'day',
                                        'skyrocket_date' => $date_key,
                                    ], $base_url);
                                    ?>

                                    <span class="skyrocket-calendar__day">
                                        <a href="<?php echo esc_url($cell_day_url); ?>" style="text-decoration: none; color: inherit;">
                                            <?php echo esc_html($day_number); ?>
                                            <?= $day_string ?>
                                        </a>
                                    </span>

                                    <?php foreach (
                                        $days[$date_key] ?? [] as $item
                                    ): ?>
                                        <?php $is_event_private = is_event_private($item['title']); ?>
                                        <?php if ($is_event_private) { ?>
                                            <span class="skyrocket-calendar__event">
                                                <small class="event-date">
                                                    <?php
                                                    echo esc_html(
                                                        $item['start']->format('H:i')
                                                        . ' - '
                                                        . $item['end']->format('H:i')
                                                    );
                                                    ?>
                                                </small>

                                                <span class="private-event"><?php echo esc_html($item['title']); ?></span></span>

                                        <?php } else { ?>
                                            <small class="event-date">
                                                <?php
                                                echo esc_html(
                                                    $item['start']->format('H:i')
                                                    . ' - '
                                                    . $item['end']->format('H:i')
                                                );
                                                ?>
                                            </small>
                                            <a class="skyrocket-calendar__event" href="<?php echo esc_url($item['url']); ?>"
                                                title="<?php echo esc_attr($item['title']); ?>">

                                                <?php echo esc_html($item['title']); ?>
                                            </a>
                                        <?php } ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>

                            <?php if ($cell % 7 === 6): ?>
                            </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php
    return (string) ob_get_clean();
}
add_shortcode(
    'pavilion_calendar',
    'skyrocket_events_calendar_shortcode'
);
add_shortcode(
    'skyrocket_calendar',
    'skyrocket_events_calendar_shortcode'
);