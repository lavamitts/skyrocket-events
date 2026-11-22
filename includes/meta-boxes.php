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

    $start = get_post_meta($post->ID, '_pavilion_start', true);
    $end = get_post_meta($post->ID, '_pavilion_end', true);
    $frequency = get_post_meta($post->ID, '_pavilion_frequency', true) ?: 'none';
    $until = get_post_meta($post->ID, '_pavilion_until', true);
    $cost = get_post_meta($post->ID, '_pavilion_cost', true);
    $organiser_id = get_post_meta($post->ID, '_pavilion_organiser_id', true);
    $venue_id = get_post_meta($post->ID, '_pavilion_venue_id', true);
    $exceptions = get_post_meta($post->ID, '_pavilion_exceptions', true);

    $nth_week = get_post_meta($post->ID, '_pavilion_nth_week', true) ?: '1';
    $nth_weekday = get_post_meta($post->ID, '_pavilion_nth_weekday', true) ?: '2'; // Default Tuesday

    if (!is_array($exceptions)) {
        $exceptions = [];
    }

    $start_time = '';
    $end_time = '';
    $nth_start_date = '';
    if ($start) {
        $dt_start = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, wp_timezone());
        if ($dt_start) {
            $start_time = $dt_start->format('H:i');
            $nth_start_date = $dt_start->format('Y-m-d');
        }
    }
    if ($end) {
        $dt_end = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, wp_timezone());
        if ($dt_end) {
            $end_time = $dt_end->format('H:i');
        }
    }

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
        <label for="skyrocket_frequency"><strong>Repeat event</strong></label><br>
        <select id="skyrocket_frequency" name="skyrocket_frequency" style="width: 100%; max-width: 400px;">
            <option value="none" <?php selected($frequency, 'none'); ?>>Does not repeat</option>
            <option value="weekly" <?php selected($frequency, 'weekly'); ?>>Every week</option>
            <option value="fortnightly" <?php selected($frequency, 'fortnightly'); ?>>Every two weeks</option>
            <option value="monthly" <?php selected($frequency, 'monthly'); ?>>Every month (same date)</option>
            <option value="monthly_nth" <?php selected($frequency, 'monthly_nth'); ?>>Every month (same week and weekday)</option>
        </select>
    </p>

    <div id="skyrocket-nth-options" style="background: #f9f9f9; padding: 12px; border: 1px solid #ccd0d4; margin-bottom: 1em; max-width: 400px; display: <?php echo $frequency === 'monthly_nth' ? 'block' : 'none'; ?>;">
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

    <p id="skyrocket-start-container" style="display: <?php echo $frequency === 'monthly_nth' ? 'none' : 'block'; ?>;">
        <label for="skyrocket_start"><strong>Start date and time</strong></label><br>
        <input type="datetime-local" id="skyrocket_start" name="skyrocket_start" step="900" value="<?php echo esc_attr($start); ?>">
    </p>

    <div id="skyrocket-nth-start-date-container" style="display: <?php echo $frequency === 'monthly_nth' ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_nth_start_date"><strong>Sequence start date</strong></label><br>
            <input type="date" id="skyrocket_nth_start_date" name="skyrocket_nth_start_date" value="<?php echo esc_attr($nth_start_date); ?>">
            <br><small>Events will not generate prior to this date.</small>
        </p>
    </div>

    <div id="skyrocket-time-only-container" style="display: <?php echo $frequency === 'monthly_nth' ? 'block' : 'none'; ?>;">
        <p>
            <label for="skyrocket_start_time"><strong>Start time</strong></label><br>
            <input type="time" id="skyrocket_start_time" name="skyrocket_start_time" step="900" value="<?php echo esc_attr($start_time); ?>">
        </p>
    </div>

    <p>
        <label for="skyrocket_end"><strong>End date and time</strong></label><br>
        <input type="datetime-local" id="skyrocket_end" name="skyrocket_end" step="900" value="<?php echo esc_attr($end); ?>" required>
        <br><small id="skyrocket-end-note" style="display:<?php echo $frequency === 'monthly_nth' ? 'block' : 'none'; ?>;">For nth-monthly events, the end date time component determines the event length/end time for each generated occurrence.</small>
    </p>

    <p>
        <label for="skyrocket_until"><strong>Repeat until (Sequence end date)</strong></label><br>
        <input type="date" id="skyrocket_until" name="skyrocket_until" value="<?php echo esc_attr($until); ?>">
        <br>
        <small>Leave blank for no specified end date. Applies only to repeating events.</small>
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
            <option value="">— Select a venue —</option>
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
            <option value="">— Select an organiser —</option>
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
        document.getElementById('skyrocket_frequency').addEventListener('change', function () {
            var isNth = (this.value === 'monthly_nth');
            document.getElementById('skyrocket-nth-options').style.display = isNth ? 'block' : 'none';
            document.getElementById('skyrocket-start-container').style.display = isNth ? 'none' : 'block';
            document.getElementById('skyrocket-nth-start-date-container').style.display = isNth ? 'block' : 'none';
            document.getElementById('skyrocket-time-only-container').style.display = isNth ? 'block' : 'none';
            document.getElementById('skyrocket-end-note').style.display = isNth ? 'block' : 'none';
            if (isNth) {
                document.getElementById('skyrocket_start').removeAttribute('required');
            } else {
                document.getElementById('skyrocket_start').setAttribute('required', 'required');
            }
        });
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

    $frequency = isset($_POST['skyrocket_frequency']) ? sanitize_key(wp_unslash($_POST['skyrocket_frequency'])) : 'none';
    $until = isset($_POST['skyrocket_until']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_until'])) : '';
    $cost = isset($_POST['skyrocket_cost']) ? wp_kses_post(wp_unslash($_POST['skyrocket_cost'])) : '';
    $organiser_id = isset($_POST['skyrocket_organiser_id']) ? absint($_POST['skyrocket_organiser_id']) : 0;
    $venue_id = isset($_POST['skyrocket_venue_id']) ? absint($_POST['skyrocket_venue_id']) : 0;
    $end = isset($_POST['skyrocket_end']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_end'])) : '';

    $allowed_frequencies = ['none', 'weekly', 'fortnightly', 'monthly', 'monthly_nth'];
    if (!in_array($frequency, $allowed_frequencies, true)) {
        return;
    }

    if ($frequency === 'monthly_nth') {
        $nth_week = isset($_POST['skyrocket_nth_week']) ? absint($_POST['skyrocket_nth_week']) : 1;
        $nth_weekday = isset($_POST['skyrocket_nth_weekday']) ? absint($_POST['skyrocket_nth_weekday']) : 1;
        $start_time = isset($_POST['skyrocket_start_time']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_start_time'])) : '09:00';
        $nth_start_date = isset($_POST['skyrocket_nth_start_date']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_nth_start_date'])) : '';

        update_post_meta($post_id, '_pavilion_nth_week', $nth_week);
        update_post_meta($post_id, '_pavilion_nth_weekday', $nth_weekday);

        $anchor_date = $nth_start_date ?: (new DateTimeImmutable('today', wp_timezone()))->format('Y-m-d');
        $start = $anchor_date . 'T' . ($start_time ?: '09:00');
    } else {
        $start = isset($_POST['skyrocket_start']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_start'])) : '';
        if (!skyrocket_events_valid_datetime($start)) {
            return;
        }
    }

    if (!skyrocket_events_valid_datetime($end)) {
        return;
    }

    $start_date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, wp_timezone());
    $end_date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, wp_timezone());

    if (!$start_date || !$end_date) {
        return;
    }

    update_post_meta($post_id, '_pavilion_start', $start);
    update_post_meta($post_id, '_pavilion_end', $end);
    update_post_meta($post_id, '_pavilion_frequency', $frequency);
    update_post_meta($post_id, '_pavilion_until', $frequency === 'none' ? '' : $until);
    update_post_meta($post_id, '_pavilion_cost', $cost);
    update_post_meta($post_id, '_pavilion_organiser_id', $organiser_id);
    update_post_meta($post_id, '_pavilion_venue_id', $venue_id);
}
add_action('save_post_pavilion_event', 'skyrocket_events_save_schedule');