<?php
/**
 * Single Pavilion Event template.
 */

// Include UI helper functions
require_once __DIR__ . '/includes/single-event-helpers.php';
require_once __DIR__ . '/includes/single-event-ics-handler.php';

// Get the title and slugify it
$site = skyrocket_get_site_details();
$slug = "skyrocket-events-" . $site['slug'];

get_header();

while (have_posts()):
    the_post();

    $event_id = get_the_ID();
    $timezone = wp_timezone();

    $start_value = get_post_meta($event_id, '_pavilion_start', true);
    $end_value = get_post_meta($event_id, '_pavilion_end', true);
    $frequency = get_post_meta($event_id, '_pavilion_frequency', true) ?: 'none';
    $until = get_post_meta($event_id, '_pavilion_until', true);
    $cost = get_post_meta($event_id, '_pavilion_cost', true);
    $exceptions = get_post_meta($event_id, '_pavilion_exceptions', true);
    $organiser_id = get_post_meta($event_id, '_pavilion_organiser_id', true);
    $venue_id = get_post_meta($event_id, '_pavilion_venue_id', true);

    if (!is_array($exceptions)) {
        $exceptions = [];
    }

    // Build human-readable frequency text including nth formatting if applicable
    $frequency_label = 'One-off event';
    if ($frequency === 'weekly') {
        $frequency_label = 'Repeats weekly';
    } elseif ($frequency === 'fortnightly') {
        $frequency_label = 'Repeats fortnightly';
    } elseif ($frequency === 'monthly') {
        $frequency_label = 'Repeats monthly';
    } elseif ($frequency === 'monthly_nth') {
        $w = (int) get_post_meta($event_id, '_pavilion_nth_week', true);
        $wd = (int) get_post_meta($event_id, '_pavilion_nth_weekday', true);
        $weeks = ['', '1st', '2nd', '3rd', '4th', '5th', '6th'];
        $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $frequency_label = 'Repeats monthly (' . ($weeks[$w] ?? '') . ' ' . ($days[$wd] ?? '') . ')';
    }

    $start = $start_value ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone) : false;
    $end = $end_value ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end_value, $timezone) : false;
    $now = new DateTimeImmutable('now', $timezone);

    $occurrence_date = isset($_GET['occurrence']) ? sanitize_text_field(wp_unslash($_GET['occurrence'])) : '';
    $selected_date = DateTimeImmutable::createFromFormat('!Y-m-d', $occurrence_date, $timezone);

    if (!$selected_date || $selected_date->format('Y-m-d') !== $occurrence_date) {
        $selected_date = $start ? $start->setTime(0, 0) : false;
    }

    $display_start = $start;
    $display_end = $end;
    $valid_occurrence = false;
    $moved_occurrence = false;

    if ($end && $selected_date) {
        $selected_key = $selected_date->format('Y-m-d');

        foreach ($exceptions as $original_date => $exception) {
            if (!is_array($exception) || ($exception['action'] ?? '') !== 'move') {
                continue;
            }

            if (!pavilion_ui_is_scheduled_date((string) $original_date, $start, $frequency, $until, $timezone)) {
                continue;
            }

            $replacement_start = pavilion_ui_get_exception_datetime($exception, 'start', $timezone);
            $replacement_end = pavilion_ui_get_exception_datetime($exception, 'end', $timezone);

            if ($replacement_start && $replacement_end && $replacement_start->format('Y-m-d') === $selected_key) {
                $display_start = $replacement_start;
                $display_end = $replacement_end;
                $valid_occurrence = true;
                $moved_occurrence = true;
                break;
            }
        }

        if (!$moved_occurrence && pavilion_ui_is_scheduled_date($selected_key, $start, $frequency, $until, $timezone)) {
            $exception = $exceptions[$selected_key] ?? [];
            $action = is_array($exception) ? ($exception['action'] ?? '') : '';

            if ($action !== 'cancel' && $action !== 'move') {
                $time_str = $start ? $start->format('H:i') : '09:00';
                if ($frequency === 'monthly_nth' && $start_value) {
                    $dt_s = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone);
                    if ($dt_s) {
                        $time_str = $dt_s->format('H:i');
                    }
                }

                $occurrence_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $selected_key . 'T' . $time_str, $timezone);
                if ($occurrence_start && $end) {
                    $duration = 3600;
                    if ($start && $end > $start) {
                        $duration = $end->getTimestamp() - $start->getTimestamp();
                    }
                    $display_start = $occurrence_start;
                    $display_end = $occurrence_start->modify('+' . $duration . ' seconds');
                    $valid_occurrence = true;
                }
            }
        }

        if (!$valid_occurrence && $start) {
            $display_start = $start;
            $display_end = $end;
        }
    }

    $has_passed = $display_end && $display_end < $now;

    $titleText = get_the_title();
    if (str_contains($titleText, "TTO")) {
        $termTimeOnlyString = " (term-time only) ";
        $titleText = str_replace(" (TTO)", "", $titleText);
    } else {
        $termTimeOnlyString = "";
    }
    ?>

    <div class="ct-container <?= $slug ?>">
        <main id="primary" class="site-main skyrocket-event-detail">
            <article <?php post_class('skyrocket-event-detail__article'); ?>>
                <div class="hero-section is-width-constrained" data-type="type-1">
                    <header class="skyrocket-event-detail__header">
                        <nav class="ct-breadcrumbs" aria-label="Breadcrumb">
                            <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
                            <span class="separator">/</span>
                            <a href="<?php echo esc_url(home_url('/events/')); ?>">Events</a>
                            <span class="separator">/</span>
                            <span aria-current="page">
                                <?php echo esc_html($titleText); ?>
                            </span>
                        </nav>
                        <h1>
                            <?= esc_html($titleText); ?>
                        </h1>
                        <?php if (has_excerpt()): ?>
                            <div class="skyrocket-event-excerpt" style="margin-top: 0.5em; font-size: 1.1em; opacity: 0.9;">
                                <?php the_excerpt(); ?>
                            </div>
                        <?php endif; ?>
                        <!-- <div class="skyrocket-event-detail__back">
                        <?php get_template_part('template-parts/back-button'); ?>
                    </div> -->
                    </header>
                </div>
                <?php if ($display_start && $display_end): ?>
                    <section class="skyrocket-event-detail__schedule">


                        <?php if (!empty($cost)): ?>
                            <div class="icon price">
                                <?php echo wp_kses_post($cost); ?>
                            </div>
                        <?php endif; ?>

                        <p class="icon date"><strong><?php echo esc_html($display_start->format('l j F Y')); ?></strong></p>
                        <p class="icon time">
                            <?php echo esc_html($display_start->format('H:i') . ' – ' . $display_end->format('H:i')); ?>
                        </p>

                        <?php
                        $until_date = null;
                        if ($frequency !== 'none' && $until) {
                            $until_date = DateTimeImmutable::createFromFormat('!Y-m-d', $until, $timezone);
                        }
                        ?>

                        <?php if ($until_date == null): ?>
                            <?php if ($frequency_label != "One-off event"): ?>
                                <p class="icon repeat">
                                    <?php echo esc_html($frequency_label) . esc_html($termTimeOnlyString); ?>
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="icon repeat">
                                <?php echo esc_html($frequency_label) . esc_html($termTimeOnlyString) . ' until ' . esc_html($until_date->format('j F Y')); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!$has_passed): ?>
                            <!-- Add to Calendar UI Options -->
                            <div class="skyrocket-add-to-calendar">
                                <div class="wp-block-button google-button image-button">
                                    <a href="<?php echo esc_url(pavilion_ui_google_calendar_url($titleText, $display_start, $display_end, get_the_content())); ?>"
                                        target="_blank" rel="noopener" class="wp-block-button__link calendar-google">Add to Google
                                        Calendar</a>
                                </div>

                                <div class="wp-block-button ics-button image-button">
                                    <a href="<?php echo pavilion_ui_ics_download_url($event_id, $occurrence_date); ?>"
                                        class="wp-block-button__link calendar-ical">Download iCal (.ics)</a>
                                </div>

                            </div>
                        <?php endif; ?>



                        <?php if ($has_passed): ?>
                            <?php if ($has_passed && $frequency === 'none'): ?>
                                <p class="skyrocket-event-passed"><strong>Please note:</strong> This event has passed.</p>
                            <?php else: ?>
                                <p class="skyrocket-event-passed"><strong>Please note:</strong> This event has passed. See <a
                                        href="#upcoming">upcoming events</a>.</p>
                            <?php endif; ?>
                        <?php endif; ?>



                        <?php if ($has_passed && $frequency !== 'none'): ?>
                            <p class="skyrocket-event-passed"><strong>Please note:</strong> This event has passed. See <a
                                    href="#upcoming">upcoming events</a>.</p>
                        <?php endif; ?>

                    </section>
                <?php endif; ?>

                <div class="skyrocket-event-detail__content">
                    <?php the_content(); ?>
                </div>

                <?php
                $grid_item_count = 0;
                if (!empty($organiser_id)) {
                    $grid_item_count += 1;
                }
                if (!empty($venue_id)) {
                    $grid_item_count += 1;
                }
                if ($grid_item_count == 1) {
                    $row_class = "skyrocket-event-detail__venue_and_organiser__row1-single-item";
                } elseif ($grid_item_count == 2) {
                    $row_class = "skyrocket-event-detail__venue_and_organiser__row1";
                }
                ?>
                <?php
                if ($grid_item_count > 0):
                    // Show the organiser and venue block
                    ?>
                    <div class="skyrocket-event-detail__venue_and_organiser">
                        <div class="<?= $row_class ?>">
                            <?php
                            if (!empty($organiser_id)) {
                                $org_title = get_the_title($organiser_id);
                                $org_contact = get_post_meta($organiser_id, '_pavilion_org_contact_name', true);
                                $org_email = get_post_meta($organiser_id, '_pavilion_org_email', true);
                                $org_phone = get_post_meta($organiser_id, '_pavilion_org_phone', true);
                                $org_website = get_post_meta($organiser_id, '_pavilion_org_website', true);
                                $org_content = get_post_field('post_content', $organiser_id);
                                ?>
                                <section class="skyrocket-event-detail__organiser">
                                    <!-- Organiser name -->
                                    <h3>Organiser:
                                        <?php echo esc_html($org_title); ?>
                                    </h3>

                                    <!-- Organiser content block -->
                                    <?php if (!empty($org_content)): ?>
                                        <div class="skyrocket-organiser-description mb-1">
                                            <?php echo apply_filters('the_content', $org_content); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($org_contact)): ?>
                                        <p class="icon person mb-half">
                                            <?php echo esc_html($org_contact); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($org_phone)): ?>
                                        <p class="icon phone mb-half">
                                            <?php echo esc_html($org_phone); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($org_email)): ?>
                                        <p class="icon email mb-half"><a href="mailto:<?php echo esc_attr($org_email); ?>">
                                                <?php echo esc_html($org_email); ?>
                                            </a></p>
                                    <?php endif; ?>

                                    <?php if (!empty($org_website)): ?>
                                        <p class="icon web mb-half"><a href="<?php echo esc_url($org_website); ?>" target="_blank"
                                                rel="noopener"><?= $org_website ?></a></p>
                                    <?php endif; ?>
                                </section>
                                <?php
                            }
                            ?>


                            <?php
                            // Venue Section Block
                            if (!empty($venue_id)) {
                                $venue_title = get_the_title($venue_id);
                                $v_address = get_post_meta($venue_id, '_pavilion_venue_address', true);
                                $v_city = get_post_meta($venue_id, '_pavilion_venue_city', true);
                                $v_country = get_post_meta($venue_id, '_pavilion_venue_country', true);
                                $v_postcode = get_post_meta($venue_id, '_pavilion_venue_postcode', true);
                                $v_phone = get_post_meta($venue_id, '_pavilion_venue_phone', true);
                                $v_website = get_post_meta($venue_id, '_pavilion_venue_website', true);
                                $v_include_map = get_post_meta($venue_id, '_pavilion_venue_include_map', true);
                                $v_excerpt = get_post_field('post_excerpt', $venue_id);
                                $v_content = get_post_field('post_content', $venue_id);
                                ?>
                                <section class="skyrocket-event-detail__venue">
                                    <h3>Venue: <?php echo esc_html($venue_title); ?></h3>

                                    <?php if (!empty($v_content)): ?>
                                        <div class="venue-description">
                                            <?php echo apply_filters('the_content', $v_content); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="venue-address-block" style="margin-bottom: 1rem;">
                                        <?php if (!empty($v_address)): ?>
                                            <p class="m-zero b">Address</p>
                                            <p class="m-zero"><?php echo nl2br(esc_html($v_address)); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($v_city)): ?>
                                            <p class="m-zero"><?php echo esc_html($v_city); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($v_postcode)): ?>
                                            <p class="m-zero"><?php echo esc_html($v_postcode); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($v_country)): ?>
                                            <p class="m-zero"><?php echo esc_html($v_country); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($v_phone)): ?>
                                        <p class="icon phone"><a href="tel:<?php echo esc_attr($v_phone); ?>"><?php echo esc_html($v_phone); ?></a></p>
                                    <?php endif; ?>

                                    <?php if (!empty($v_website)): ?>
                                        <p class="icon website"><a href="<?php echo esc_url($v_website); ?>" target="_blank" rel="noopener">
                                                <?= $v_website ?>
                                            </a></p>
                                    <?php endif; ?>


                                </section>
                                <?php
                            }
                            ?>


                        </div>
                        <div>
                            <?php
                            // Render 4:3 Aspect Ratio Google Maps iFrame if map is enabled and postcode is provided
                            if (!empty($v_include_map) && !empty($v_postcode)):
                                $map_query = urlencode(trim($v_postcode . ' ' . $v_city));
                                ?>
                                <div class="skyrocket-venue-map-container">
                                    <iframe
                                        src="https://maps.google.com/maps?q=<?php echo $map_query; ?>&output=embed"
                                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                                        allowfullscreen=""
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade">
                                    </iframe>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <?php
                endif ?>
                <?php
                // Upcoming occurrences list calculation block
                if ($end):
                    $today = $now->setTime(0, 0);
                    $list_start = $today;

                    if ($selected_date && $selected_date > $list_start) {
                        $list_start = $selected_date;
                    }

                    $list_end = $list_start->modify('+12 months');

                    if ($until) {
                        $until_date = DateTimeImmutable::createFromFormat('!Y-m-d', $until, $timezone);
                        if ($until_date && $until_date < $list_end) {
                            $list_end = $until_date;
                        }
                    }

                    $upcoming_occurrences = [];

                    for ($day = $list_start; $day <= $list_end; $day = $day->modify('+1 day')) {
                        $date_key = $day->format('Y-m-d');

                        if (!pavilion_ui_is_scheduled_date($date_key, $start, $frequency, $until, $timezone)) {
                            continue;
                        }

                        $exception = $exceptions[$date_key] ?? [];
                        if (!is_array($exception)) {
                            $exception = [];
                        }

                        $action = $exception['action'] ?? '';
                        if ($action === 'cancel' || $action === 'move') {
                            continue;
                        }

                        $time_str = $start ? $start->format('H:i') : '09:00';
                        if ($frequency === 'monthly_nth' && $start_value) {
                            $dt_s = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start_value, $timezone);
                            if ($dt_s) {
                                $time_str = $dt_s->format('H:i');
                            }
                        }

                        $occurrence_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date_key . 'T' . $time_str, $timezone);
                        if (!$occurrence_start) {
                            continue;
                        }

                        $duration = 3600;
                        if ($start && $end > $start) {
                            $duration = $end->getTimestamp() - $start->getTimestamp();
                        }
                        $occurrence_end = $occurrence_start->modify('+' . $duration . ' seconds');

                        if ($occurrence_end < $now) {
                            continue;
                        }

                        $upcoming_occurrences[] = [
                            'start' => $occurrence_start,
                            'end' => $occurrence_end,
                            'date' => $date_key,
                        ];
                    }

                    foreach ($exceptions as $original_date => $exception) {
                        if (!is_array($exception) || ($exception['action'] ?? '') !== 'move' || !pavilion_ui_is_scheduled_date((string) $original_date, $start, $frequency, $until, $timezone)) {
                            continue;
                        }


                        $moved_start = pavilion_ui_get_exception_datetime($exception, 'start', $timezone);
                        $moved_end = pavilion_ui_get_exception_datetime($exception, 'end', $timezone);

                        if (!$moved_start || !$moved_end) {
                            continue;
                        }

                        $moved_date = $moved_start->format('Y-m-d');

                        if ($moved_start < $list_start || $moved_start > $list_end->setTime(23, 59, 59) || $moved_end < $now) {
                            continue;
                        }

                        $upcoming_occurrences[] = [
                            'start' => $moved_start,
                            'end' => $moved_end,
                            'date' => $moved_date,
                        ];
                    }

                    usort($upcoming_occurrences, static function ($a, $b) {
                        return $a['start']->getTimestamp() <=> $b['start']->getTimestamp();
                    });

                    // Limit strictly up to 12 future events
                    $upcoming_occurrences = array_slice($upcoming_occurrences, 0, 12);
                    ?>

                    <?php if ($frequency_label != "One-off event"): ?>
                        <!-- Only show upcoming events if this is not a one-off event -->
                        <?php if ($upcoming_occurrences): ?>
                            <section id="upcoming" class="skyrocket-event-detail__upcoming">
                                <h2>Upcoming events</h2>
                                <ul>
                                    <?php foreach ($upcoming_occurrences as $occurrence): ?>
                                        <li>
                                            <a
                                                href="<?php echo esc_url(add_query_arg('occurrence', $occurrence['date'], get_permalink($event_id))); ?>">
                                                <strong><?php echo esc_html($occurrence['start']->format('l j F Y')); ?></strong>
                                                —
                                                <?php echo esc_html($occurrence['start']->format('H:i') . '–' . $occurrence['end']->format('H:i')); ?>
                                                —
                                                <?php echo esc_html(get_the_title($event_id)); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php
                require_once SKYROCKET_EVENTS_PATH . 'templates/template-parts/back-button.php';
                ?>
            </article>
        </main>
    </div>
<?php endwhile; ?>

<?php get_footer(); ?>