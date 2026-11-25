<?php
defined('ABSPATH') || exit;

/**
 * Register the event scheduling meta box.
 */
function skyrocket_events_add_meta_boxes(): void
{
    add_meta_box(
        'skyrocket_event_schedule',
        'Event Schedule & Details',
        'skyrocket_events_render_schedule',
        'pavilion_event',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'skyrocket_events_add_meta_boxes');

/**
 * Render one recurring-event exception row.
 */
function skyrocket_events_render_exception_row(
    array $exception,
    int|string $index
): void {
    $date = $exception['date'] ?? '';
    $action = $exception['action'] ?? 'cancel';
    $start = $exception['start'] ?? '';
    $end = $exception['end'] ?? '';
    ?>
    <tr>
        <td>
            <input type="date" name="skyrocket_exceptions[<?php echo esc_attr((string) $index); ?>][date]"
                value="<?php echo esc_attr($date); ?>" required>
        </td>
        <td>
            <select name="skyrocket_exceptions[<?php echo esc_attr((string) $index); ?>][action]"
                class="skyrocket-exception-action">
                <option value="cancel" <?php selected($action, 'cancel'); ?>>Cancel occurrence</option>
                <option value="move" <?php selected($action, 'move'); ?>>Move occurrence</option>
            </select>
        </td>
        <td>
            <input type="datetime-local" name="skyrocket_exceptions[<?php echo esc_attr((string) $index); ?>][start]"
                class="skyrocket-exception-start" value="<?php echo esc_attr($start); ?>" step="900">
        </td>
        <td>
            <input type="datetime-local" name="skyrocket_exceptions[<?php echo esc_attr((string) $index); ?>][end]"
                class="skyrocket-exception-end" value="<?php echo esc_attr($end); ?>" step="900">
        </td>
        <td>
            <button type="button" class="button skyrocket-remove-exception">Remove</button>
        </td>
    </tr>
    <?php
}

/**
 * Render the scheduling fields.
 */
function skyrocket_events_render_schedule(WP_Post $post): void
{
    wp_nonce_field('skyrocket_events_save_schedule', 'skyrocket_events_schedule_nonce');

    $all_day = get_post_meta($post->ID, '_pavilion_all_day', true) === '1';
    $start = get_post_meta($post->ID, '_pavilion_start', true);
    $end = get_post_meta($post->ID, '_pavilion_end', true);
    $frequency = get_post_meta($post->ID, '_pavilion_frequency', true) ?: 'none';
    $until = get_post_meta($post->ID, '_pavilion_until', true);
    $cost = get_post_meta($post->ID, '_pavilion_cost', true);
    $organiser_id = get_post_meta($post->ID, '_pavilion_organiser_id', true);
    $venue_id = get_post_meta($post->ID, '_pavilion_venue_id', true);
    $exceptions = get_post_meta($post->ID, '_pavilion_exceptions', true);

    $raw_click_through = get_post_meta($post->ID, '_pavilion_click_through', true);
    $click_through = ($raw_click_through === '' || $raw_click_through === false) ? '1' : ($raw_click_through === '1' ? '1' : '0');

    $nth_week = get_post_meta($post->ID, '_pavilion_nth_week', true) ?: '1';
    $nth_weekday = get_post_meta($post->ID, '_pavilion_nth_weekday', true) ?: '2'; // Default Tuesday

    if (!is_array($exceptions)) {
        $exceptions = [];
    }

    $start_date_val = '';
    $end_date_val = '';
    $start_time = '';
    $nth_start_date = '';

    if ($start) {
        $dt_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, wp_timezone());
        if ($dt_start) {
            $start_date_val = $dt_start->format('Y-m-d');
            $start_time = $dt_start->format('H:i');
            $nth_start_date = $dt_start->format('Y-m-d');
        }
    }
    if ($end) {
        $dt_end = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, wp_timezone());
        if ($dt_end) {
            $end_date_val = $dt_end->format('Y-m-d');
        }
    }

    $is_nth = ($frequency === 'monthly_nth');

    // Fetch published organisers and venues for selectors
    $organisers = get_posts([
        'post_type' => 'pavilion_organiser',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    $venues = get_posts([
        'post_type' => 'pavilion_venue',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    ?>

    <p>
        <label for="skyrocket_all_day">
            <input type="checkbox" id="skyrocket_all_day" name="skyrocket_all_day" value="1" <?php checked($all_day); ?>>
            <strong>Is this an all day event?</strong>
        </label><br>
        <small>When ticked, start and end times default to 00:00 and 23:59, and the event is flagged as all day.</small>
    </p>

    <p>
        <label for="skyrocket_frequency"><strong>Repeat event</strong></label><br>
        <select id="skyrocket_frequency" name="skyrocket_frequency" style="width: 100%; max-width: 400px;">
            <option value="none" <?php selected($frequency, 'none'); ?>>Does not repeat</option>
            <option value="weekly" <?php selected($frequency, 'weekly'); ?>>Every week</option>
            <option value="fortnightly" <?php selected($frequency, 'fortnightly'); ?>>Every two weeks</option>
            <option value="monthly" <?php selected($frequency, 'monthly'); ?>>Every month (same date)</option>
            <option value="monthly_nth" <?php selected($frequency, 'monthly_nth'); ?>>Every month (same week and weekday)</option>
            <option value="yearly" <?php selected($frequency, 'yearly'); ?>>Every year (same date)</option>
        </select>
    </p>

    <div id="skyrocket-nth-options" style="background: #f9f9f9; padding: 12px; border: 1px solid #ccd0d4; margin-bottom: 1em; max-width: 400px; display: <?php echo $is_nth ? 'block' : 'none'; ?>;">
        <p style="margin-top:0;">
            <label for="skyrocket_nth_week"><strong>Which week of the month?</strong></label><br>
            <select id="skyrocket_nth_week" name="skyrocket_nth_week" style="width: 100%;">
                <option value="1" <?php selected($nth_week, '1'); ?>>1st week</option>
                <option value="2" <?php selected($nth_week, '2'); ?>>2nd week</option>
                <option value="3" <?php selected($nth_week, '3'); ?>>3rd week</option>
                <option value="4" <?php selected($nth_week, '4'); ?>>4th week</option>
                <option value="5" <?php selected($nth_week, '5'); ?>>5th week</option>
                <option value="6" <?php selected($nth_week, '6'); ?>>6th week (if applicable)</option>
            </select>
        </p>
        <p style="margin-bottom:0;">
            <label for="skyrocket_nth_weekday"><strong>Which day of the week?</strong></label><br>
            <select id="skyrocket_nth_weekday" name="skyrocket_nth_weekday" style="width: 100%;">
                <option value="1" <?php selected($nth_weekday, '1'); ?>>Monday</option>
                <option value="2" <?php selected($nth_weekday, '2'); ?>>Tuesday</option>
                <option value="3" <?php selected($nth_weekday, '3'); ?>>Wednesday</option>
                <option value="4" <?php selected($nth_weekday, '4'); ?>>Thursday</option>
                <option value="5" <?php selected($nth_weekday, '5'); ?>>Friday</option>
                <option value="6" <?php selected($nth_weekday, '6'); ?>>Saturday</option>
                <option value="7" <?php selected($nth_weekday, '7'); ?>>Sunday</option>
            </select>
        </p>
    </div>

    <div id="skyrocket-start-container" style="display: <?php echo (!$is_nth && !$all_day) ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_start"><strong>Start date and time</strong></label><br>
            <input type="datetime-local" id="skyrocket_start" name="skyrocket_start" step="900" value="<?php echo esc_attr($start); ?>">
        </p>
    </div>

    <div id="skyrocket-allday-start-date-container" style="display: <?php echo (!$is_nth && $all_day) ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_allday_start_date"><strong>Start date</strong></label><br>
            <input type="date" id="skyrocket_allday_start_date" name="skyrocket_allday_start_date" value="<?php echo esc_attr($start_date_val); ?>">
        </p>
    </div>

    <div id="skyrocket-nth-start-date-container" style="display: <?php echo $is_nth ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_nth_start_date"><strong>Sequence start date</strong></label><br>
            <input type="date" id="skyrocket_nth_start_date" name="skyrocket_nth_start_date" value="<?php echo esc_attr($nth_start_date); ?>">
            <br><small>Events will not generate prior to this date.</small>
        </p>
    </div>

    <div id="skyrocket-time-only-container" style="display: <?php echo ($is_nth && !$all_day) ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_start_time"><strong>Start time</strong></label><br>
            <input type="time" id="skyrocket_start_time" name="skyrocket_start_time" step="900" value="<?php echo esc_attr($start_time); ?>">
        </p>
    </div>

    <div id="skyrocket-end-container" style="display: <?php echo !$all_day ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_end"><strong>End date and time</strong></label><br>
            <input type="datetime-local" id="skyrocket_end" name="skyrocket_end" step="900" value="<?php echo esc_attr($end); ?>">
            <br><small id="skyrocket-end-note" style="display:<?php echo $is_nth ? 'block' : 'none'; ?>;">For nth-monthly events, the end date time component determines the event length/end time for each generated occurrence.</small>
        </p>
    </div>

    <div id="skyrocket-allday-end-date-container" style="display: <?php echo $all_day ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_allday_end_date"><strong>End date</strong></label><br>
            <input type="date" id="skyrocket_allday_end_date" name="skyrocket_allday_end_date" value="<?php echo esc_attr($end_date_val); ?>">
            <br><small>Use the same date as the start date for a single all day event, or a later date for a multi-day all day event.</small>
        </p>
    </div>

    <p>
        <label for="skyrocket_until"><strong>Repeat until (Sequence end date)</strong></label><br>
        <input type="date" id="skyrocket_until" name="skyrocket_until" value="<?php echo esc_attr($until); ?>">
        <br>
        <small>Leave blank for no specified end date. Applies only to repeating events.</small>
    </p>

    <p>
        <label for="skyrocket_click_through"><strong>Click through to event details?</strong></label><br>
        <select id="skyrocket_click_through" name="skyrocket_click_through" style="width: 100%; max-width: 400px;">
            <option value="1" <?php selected($click_through, '1'); ?>>Yes</option>
            <option value="0" <?php selected($click_through, '0'); ?>>No</option>
        </select><br>
        <small>Choose whether visitors can click through to the full event details page. Defaults to Yes.</small>
    </p>

    <p>
        <label for="skyrocket_cost"><strong>Cost</strong></label><br>
        <?php
        wp_editor(
            $cost,
            'skyrocket_cost_editor',
            [
                'textarea_name' => 'skyrocket_cost',
                'media_buttons' => false,
                'textarea_rows' => 4,
                'teeny' => true,
            ]
        );
        ?>
        <br>
        <small>Enter the cost details and apply formatting as needed.</small>
    </p>

    <p>
        <label for="skyrocket_venue_id"><strong>Event Venue</strong></label><br>
        <select id="skyrocket_venue_id" name="skyrocket_venue_id" style="width: 100%; max-width: 400px;">
            <option value="">Select a venue</option>
            <?php foreach ($venues as $venue): ?>
                <option value="<?php echo esc_attr((string) $venue->ID); ?>" <?php selected($venue_id, $venue->ID); ?>>
                    <?php echo esc_html($venue->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="skyrocket_organiser_id"><strong>Event Organiser</strong></label><br>
        <select id="skyrocket_organiser_id" name="skyrocket_organiser_id" style="width: 100%; max-width: 400px;">
            <option value="">Select an organiser</option>
            <?php foreach ($organisers as $org): ?>
                <option value="<?php echo esc_attr((string) $org->ID); ?>" <?php selected($organiser_id, $org->ID); ?>>
                    <?php echo esc_html($org->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <hr>

    <h3>Exceptions to this series</h3>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Original date</th>
                <th>Action</th>
                <th>New start</th>
                <th>New end</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="skyrocket-exceptions">
            <?php foreach (array_values($exceptions) as $index => $exception): ?>
                <?php skyrocket_events_render_exception_row($exception, $index); ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <button type="button" class="button" id="skyrocket-add-exception">Add exception</button>
    </p>

    <template id="skyrocket-exception-template">
        <?php skyrocket_events_render_exception_row(['date' => '', 'action' => 'cancel', 'start' => '', 'end' => ''], '__INDEX__'); ?>
    </template>

    <script>
        (function () {
            var allDayEl = document.getElementById('skyrocket_all_day');
            var freqEl = document.getElementById('skyrocket_frequency');
            var startDatetimeEl = document.getElementById('skyrocket_start');
            var alldayStartDateEl = document.getElementById('skyrocket_allday_start_date');
            var nthStartDateEl = document.getElementById('skyrocket_nth_start_date');
            var endDatetimeEl = document.getElementById('skyrocket_end');
            var alldayEndDateEl = document.getElementById('skyrocket_allday_end_date');

            function syncScheduleFields() {
                var isAllDay = !!(allDayEl && allDayEl.checked);
                var isNth = !!(freqEl && freqEl.value === 'monthly_nth');

                document.getElementById('skyrocket-nth-options').style.display = isNth ? 'block' : 'none';
                document.getElementById('skyrocket-start-container').style.display = (!isNth && !isAllDay) ? 'block' : 'none';
                document.getElementById('skyrocket-allday-start-date-container').style.display = (!isNth && isAllDay) ? 'block' : 'none';
                document.getElementById('skyrocket-nth-start-date-container').style.display = isNth ? 'block' : 'none';
                document.getElementById('skyrocket-time-only-container').style.display = (isNth && !isAllDay) ? 'block' : 'none';
                document.getElementById('skyrocket-end-container').style.display = !isAllDay ? 'block' : 'none';
                document.getElementById('skyrocket-allday-end-date-container').style.display = isAllDay ? 'block' : 'none';
                document.getElementById('skyrocket-end-note').style.display = (isNth && !isAllDay) ? 'block' : 'none';

                if (startDatetimeEl) {
                    if (!isNth && !isAllDay) {
                        startDatetimeEl.setAttribute('required', 'required');
                    } else {
                        startDatetimeEl.removeAttribute('required');
                    }
                }

                if (alldayStartDateEl) {
                    if (!isNth && isAllDay) {
                        alldayStartDateEl.setAttribute('required', 'required');
                    } else {
                        alldayStartDateEl.removeAttribute('required');
                    }
                }

                if (nthStartDateEl) {
                    if (isNth) {
                        nthStartDateEl.setAttribute('required', 'required');
                    } else {
                        nthStartDateEl.removeAttribute('required');
                    }
                }

                if (endDatetimeEl) {
                    if (!isAllDay) {
                        endDatetimeEl.setAttribute('required', 'required');
                    } else {
                        endDatetimeEl.removeAttribute('required');
                    }
                }

                if (alldayEndDateEl) {
                    if (isAllDay) {
                        alldayEndDateEl.setAttribute('required', 'required');
                        if (!alldayEndDateEl.value) {
                            var sourceDate = isNth
                                ? (nthStartDateEl ? nthStartDateEl.value : '')
                                : (alldayStartDateEl ? alldayStartDateEl.value : '');
                            if (sourceDate) {
                                alldayEndDateEl.value = sourceDate;
                            }
                        }
                    } else {
                        alldayEndDateEl.removeAttribute('required');
                    }
                }
            }

            if (allDayEl) {
                allDayEl.addEventListener('change', syncScheduleFields);
            }
            if (freqEl) {
                freqEl.addEventListener('change', syncScheduleFields);
            }
            if (alldayStartDateEl && alldayEndDateEl) {
                alldayStartDateEl.addEventListener('change', function () {
                    if (!alldayEndDateEl.value && this.value) {
                        alldayEndDateEl.value = this.value;
                    }
                });
            }
            if (nthStartDateEl && alldayEndDateEl) {
                nthStartDateEl.addEventListener('change', function () {
                    if (allDayEl && allDayEl.checked && !alldayEndDateEl.value && this.value) {
                        alldayEndDateEl.value = this.value;
                    }
                });
            }

            syncScheduleFields();
        })();
    </script>
    <?php
}

/**
 * Validate a local datetime value.
 */
function skyrocket_events_valid_datetime(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
    return $date !== false && $date->format('Y-m-d\TH:i') === $value;
}

/**
 * Validate a calendar date value (Y-m-d).
 */
function skyrocket_events_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * Save event scheduling metadata and associated fields.
 */
function skyrocket_events_save_schedule(int $post_id): void
{
    if (
        !isset($_POST['skyrocket_events_schedule_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['skyrocket_events_schedule_nonce'])), 'skyrocket_events_save_schedule')
    ) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $is_all_day = isset($_POST['skyrocket_all_day']) && $_POST['skyrocket_all_day'] === '1';
    $frequency = isset($_POST['skyrocket_frequency']) ? sanitize_key(wp_unslash($_POST['skyrocket_frequency'])) : 'none';
    $until = isset($_POST['skyrocket_until']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_until'])) : '';
    $cost = isset($_POST['skyrocket_cost']) ? wp_kses_post(wp_unslash($_POST['skyrocket_cost'])) : '';
    $organiser_id = isset($_POST['skyrocket_organiser_id']) ? absint($_POST['skyrocket_organiser_id']) : 0;
    $venue_id = isset($_POST['skyrocket_venue_id']) ? absint($_POST['skyrocket_venue_id']) : 0;

    $click_through_raw = isset($_POST['skyrocket_click_through']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_click_through'])) : '1';
    $click_through = ($click_through_raw === '0') ? '0' : '1';

    $allowed_frequencies = ['none', 'weekly', 'fortnightly', 'monthly', 'monthly_nth', 'yearly'];
    if (!in_array($frequency, $allowed_frequencies, true)) {
        return;
    }

    if ($frequency === 'monthly_nth') {
        $nth_week = isset($_POST['skyrocket_nth_week']) ? absint($_POST['skyrocket_nth_week']) : 1;
        $nth_weekday = isset($_POST['skyrocket_nth_weekday']) ? absint($_POST['skyrocket_nth_weekday']) : 1;
        $nth_start_date = isset($_POST['skyrocket_nth_start_date']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_nth_start_date'])) : '';

        update_post_meta($post_id, '_pavilion_nth_week', $nth_week);
        update_post_meta($post_id, '_pavilion_nth_weekday', $nth_weekday);

        $anchor_date = skyrocket_events_valid_date($nth_start_date)
            ? $nth_start_date
            : (new DateTimeImmutable('today', wp_timezone()))->format('Y-m-d');

        if ($is_all_day) {
            $start = $anchor_date . 'T00:00';
            $allday_end_date = isset($_POST['skyrocket_allday_end_date']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_allday_end_date'])) : $anchor_date;
            $end_date_part = skyrocket_events_valid_date($allday_end_date) ? $allday_end_date : $anchor_date;
            $end = $end_date_part . 'T23:59';
        } else {
            $start_time = isset($_POST['skyrocket_start_time']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_start_time'])) : '09:00';
            $start = $anchor_date . 'T' . ($start_time ?: '09:00');
            $end = isset($_POST['skyrocket_end']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_end'])) : '';
        }
    } elseif ($is_all_day) {
        $allday_start_date = isset($_POST['skyrocket_allday_start_date']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_allday_start_date'])) : '';
        $allday_end_date = isset($_POST['skyrocket_allday_end_date']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_allday_end_date'])) : '';

        if (!skyrocket_events_valid_date($allday_start_date)) {
            return;
        }

        $effective_end_date = skyrocket_events_valid_date($allday_end_date) ? $allday_end_date : $allday_start_date;
        $start = $allday_start_date . 'T00:00';
        $end = $effective_end_date . 'T23:59';
    } else {
        $start = isset($_POST['skyrocket_start']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_start'])) : '';
        $end = isset($_POST['skyrocket_end']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_end'])) : '';

        if (!skyrocket_events_valid_datetime($start)) {
            return;
        }
    }

    if (!skyrocket_events_valid_datetime($start) || !skyrocket_events_valid_datetime($end)) {
        return;
    }

    $start_date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, wp_timezone());
    $end_date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, wp_timezone());

    if (!$start_date || !$end_date) {
        return;
    }

    update_post_meta($post_id, '_pavilion_all_day', $is_all_day ? '1' : '0');
    update_post_meta($post_id, '_pavilion_start', $start);
    update_post_meta($post_id, '_pavilion_end', $end);
    update_post_meta($post_id, '_pavilion_frequency', $frequency);
    update_post_meta($post_id, '_pavilion_until', $frequency === 'none' ? '' : $until);
    update_post_meta($post_id, '_pavilion_click_through', $click_through);
    update_post_meta($post_id, '_pavilion_cost', $cost);
    update_post_meta($post_id, '_pavilion_organiser_id', $organiser_id);
    update_post_meta($post_id, '_pavilion_venue_id', $venue_id);
}
add_action('save_post_pavilion_event', 'skyrocket_events_save_schedule');
