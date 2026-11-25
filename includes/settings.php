<?php
/**
 * Events calendar settings page and option handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

const SKYROCKET_EVENTS_OPTION_NAME = 'skyrocket_events_calendar_settings';

/**
 * Default settings values.
 *
 * @return array<string, int>
 */
function skyrocket_events_get_default_settings(): array
{
    return [
        'show_month_grid' => 1,
        'show_month_list' => 1,
        'show_rolling_list' => 1,
        'show_day_view' => 1,
        'rolling_list_days' => 56,
        'show_excerpt_list_view' => 0,
        'show_excerpt_detail_view' => 0,
        'show_add_to_calendar_links' => 1,
        'show_day_badge' => 1,
        'show_upcoming_events' => 1,
        'show_event_breadcrumbs' => 1,
        'show_all_day_indicator' => 1,
        'breadcrumb_slug' => 'events',
    ];
}

/**
 * Retrieve normalised calendar settings.
 *
 * @return array<string, bool|int>
 */
function skyrocket_events_get_calendar_settings(): array
{
    $defaults = skyrocket_events_get_default_settings();
    $saved = get_option(SKYROCKET_EVENTS_OPTION_NAME, []);

    if (!is_array($saved)) {
        $saved = [];
    }

    $merged = wp_parse_args($saved, $defaults);

    $rolling_days = filter_var($merged['rolling_list_days'] ?? 56, FILTER_VALIDATE_INT);
    if ($rolling_days === false || $rolling_days < 1) {
        $rolling_days = 56;
    }

    // Normalise the settings slug
    $raw_slug = is_string($merged['breadcrumb_slug'] ?? null) ? trim($merged['breadcrumb_slug'], " \t\n\r\0\x0B/") : '';
    $breadcrumb_slug = $raw_slug !== '' ? sanitize_title($raw_slug) : 'events';
    if ($breadcrumb_slug === '') {
        $breadcrumb_slug = 'events';
    }

    $settings = [
        'show_month_grid' => !empty($merged['show_month_grid']),
        'show_month_list' => !empty($merged['show_month_list']),
        'show_rolling_list' => !empty($merged['show_rolling_list']),
        'show_day_view' => !empty($merged['show_day_view']),
        'rolling_list_days' => $rolling_days,
        'show_excerpt_list_view' => !empty($merged['show_excerpt_list_view']),
        'show_excerpt_detail_view' => !empty($merged['show_excerpt_detail_view']),
        'show_add_to_calendar_links' => !empty($merged['show_add_to_calendar_links']),
        'show_day_badge' => !empty($merged['show_day_badge']),
        'show_upcoming_events' => !empty($merged['show_upcoming_events']),
        'show_event_breadcrumbs' => !empty($merged['show_event_breadcrumbs']),
        'show_all_day_indicator' => !empty($merged['show_all_day_indicator']),
        'breadcrumb_slug' => $breadcrumb_slug,
    ];

    // Safety fallback in case options were manipulated directly in the database.
    if (!$settings['show_month_grid'] && !$settings['show_month_list'] && !$settings['show_rolling_list'] && !$settings['show_day_view']) {
        $settings['show_month_grid'] = true;
    }

    return $settings;
}

/**
 * Register the Settings submenu under Events (after Organisers).
 */
function skyrocket_events_register_settings_page(): void
{
    $parent_slug = 'edit.php?post_type=pavilion_event';

    // Use a late priority on admin_menu so Organisers is registered first.
    add_submenu_page(
        $parent_slug,
        __('Events Settings', 'skyrocket-events'),
        __('Settings', 'skyrocket-events'),
        'manage_options',
        'skyrocket-events-settings',
        'skyrocket_events_render_settings_page',
        20
    );
}
add_action('admin_menu', 'skyrocket_events_register_settings_page', 99);

/**
 * Register setting and validation hook.
 */
function skyrocket_events_register_settings(): void
{
    register_setting(
        'skyrocket_events_settings_group',
        SKYROCKET_EVENTS_OPTION_NAME,
        [
            'type' => 'array',
            'sanitize_callback' => 'skyrocket_events_sanitize_settings',
            'default' => skyrocket_events_get_default_settings(),
        ]
    );

    // Intercept before update so an invalid submission does not overwrite saved options.
    add_filter(
        'pre_update_option_' . SKYROCKET_EVENTS_OPTION_NAME,
        'skyrocket_events_validate_before_update',
        10,
        2
    );
}
add_action('admin_init', 'skyrocket_events_register_settings');

/**
 * Sanitize raw submitted option values.
 *
 * @param mixed $input Raw POST array.
 * @return array<string, int>
 */
function skyrocket_events_sanitize_settings($input): array
{
    $input = is_array($input) ? $input : [];

    $rolling_days = filter_var($input['rolling_list_days'] ?? 56, FILTER_VALIDATE_INT);
    if ($rolling_days === false || $rolling_days < 1) {
        $rolling_days = 56;
    }

    // Sanitize the slug
    $raw_slug = is_string($input['breadcrumb_slug'] ?? null) ? trim($input['breadcrumb_slug'], " \t\n\r\0\x0B/") : '';
    $breadcrumb_slug = $raw_slug !== '' ? sanitize_title($raw_slug) : 'events';
    if ($breadcrumb_slug === '') {
        $breadcrumb_slug = 'events';
    }

    return [
        'show_month_grid' => !empty($input['show_month_grid']) ? 1 : 0,
        'show_month_list' => !empty($input['show_month_list']) ? 1 : 0,
        'show_rolling_list' => !empty($input['show_rolling_list']) ? 1 : 0,
        'show_day_view' => !empty($input['show_day_view']) ? 1 : 0,
        'rolling_list_days' => $rolling_days,
        'breadcrumb_slug' => $breadcrumb_slug,
        'show_excerpt_list_view' => !empty($input['show_excerpt_list_view']) ? 1 : 0,
        'show_excerpt_detail_view' => !empty($input['show_excerpt_detail_view']) ? 1 : 0,
        'show_add_to_calendar_links' => !empty($input['show_add_to_calendar_links']) ? 1 : 0,
        'show_day_badge' => !empty($input['show_day_badge']) ? 1 : 0,
        'show_upcoming_events' => !empty($input['show_upcoming_events']) ? 1 : 0,
        'show_event_breadcrumbs' => !empty($input['show_event_breadcrumbs']) ? 1 : 0,
        'show_all_day_indicator' => !empty($input['show_all_day_indicator']) ? 1 : 0,
    ];
}

/**
 * Prevent saving if zero calendar views are selected.
 *
 * @param mixed $new_value Sanitized new option value.
 * @param mixed $old_value Existing option value.
 * @return mixed
 */
function skyrocket_events_validate_before_update($new_value, $old_value)
{
    if (!is_array($new_value)) {
        return $old_value;
    }

    $grid = !empty($new_value['show_month_grid']);
    $list = !empty($new_value['show_month_list']);
    $rolling_list = !empty($new_value['show_rolling_list']);
    $day = !empty($new_value['show_day_view']);

    if (!$grid && !$list && !$rolling_list && !$day) {
        add_settings_error(
            SKYROCKET_EVENTS_OPTION_NAME,
            'skyrocket_events_no_views_selected',
            __('At least one calendar view (month grid, month list, rolling list or day view) must remain enabled. Your changes were not saved.', 'skyrocket-events'),
            'error'
        );

        return $old_value;
    }

    add_settings_error(
        SKYROCKET_EVENTS_OPTION_NAME,
        'skyrocket_events_settings_saved',
        __('Settings saved.', 'skyrocket-events'),
        'updated'
    );

    return $new_value;
}

/**
 * Render the Events Settings admin page.
 */
function skyrocket_events_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = skyrocket_events_get_calendar_settings();
    $rolling_days_value = (int) ($settings['rolling_list_days'] ?? 56);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php settings_errors(SKYROCKET_EVENTS_OPTION_NAME); ?>

        <form method="post" action="options.php" id="skyrocket-events-settings-form" style="max-width:1000px">
            <?php settings_fields('skyrocket_events_settings_group'); ?>

            <p>Use the events settings form to configure the events functionality.</p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Breadcrumbs', 'skyrocket-events'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Breadcrumbs', 'skyrocket-events'); ?></span>
                                </legend>

                                <label for="skyrocket-show_event_breadcrumbs">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show_event_breadcrumbs"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_event_breadcrumbs]"
                                        value="1"
                                        <?php checked($settings['show_event_breadcrumbs']); ?> />
                                    <?php esc_html_e('Show event breadcrumbs?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-breadcrumb-slug" style="display: inline-block; margin-top: 0.5em;">
                                    <?php esc_html_e('Breadcrumb calendar page slug:', 'skyrocket-events'); ?>
                                    <input
                                        type="text"
                                        id="skyrocket-breadcrumb-slug"
                                        class="regular-text"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[breadcrumb_slug]"
                                        value="<?php echo esc_attr((string) ($settings['breadcrumb_slug'] ?? 'events')); ?>" />
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Slug used for the calendar link in single event breadcrumbs, for example church-calendar or events. Also used for the back button that is displayed on events.', 'skyrocket-events'); ?>
                                </p>


                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Calendar views', 'skyrocket-events'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Calendar views', 'skyrocket-events'); ?></span>
                                </legend>

                                <label for="skyrocket-show-month-grid">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-month-grid"
                                        class="skyrocket-view-toggle"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_month_grid]"
                                        value="1"
                                        <?php checked($settings['show_month_grid']); ?> />
                                    <?php esc_html_e('Show month grid view?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show-month-list">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-month-list"
                                        class="skyrocket-view-toggle"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_month_list]"
                                        value="1"
                                        <?php checked($settings['show_month_list']); ?> />
                                    <?php esc_html_e('Show month list view?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show-rolling-list">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-rolling-list"
                                        class="skyrocket-view-toggle"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_rolling_list]"
                                        value="1"
                                        <?php checked($settings['show_rolling_list']); ?> />
                                    <?php esc_html_e('Show rolling list view?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show-day-view">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-day-view"
                                        class="skyrocket-view-toggle"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_day_view]"
                                        value="1"
                                        <?php checked($settings['show_day_view']); ?> />
                                    <?php esc_html_e('Show day view?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-rolling-list-days" style="display: inline-block; margin-top: 0.5em;">
                                    <?php esc_html_e('Rolling list window (days):', 'skyrocket-events'); ?>
                                    <input
                                        type="number"
                                        id="skyrocket-rolling-list-days"
                                        class="small-text"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[rolling_list_days]"
                                        value="<?php echo esc_attr((string) $rolling_days_value); ?>"
                                        min="1"
                                        max="999"
                                        maxlength="3" />
                                </label>

                                <p class="description" id="skyrocket-views-validation-msg" style="color: #b32d2e; display: none;">
                                    <?php esc_html_e('Please select at least one view before saving.', 'skyrocket-events'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Month and day list display', 'skyrocket-events'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Month and day list display', 'skyrocket-events'); ?></span>
                                </legend>

                                <label for="skyrocket-show_day_badge">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show_day_badge"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_day_badge]"
                                        value="1"
                                        <?php checked($settings['show_day_badge']); ?> />
                                    <?php esc_html_e('Show badge style day indicator in list view?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show_all_day_indicator">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show_all_day_indicator"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_all_day_indicator]"
                                        value="1"
                                        <?php checked($settings['show_all_day_indicator']); ?> />
                                    <?php esc_html_e('Show all day indicator?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show-excerpt-list-view">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-excerpt-list-view"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_excerpt_list_view]"
                                        value="1"
                                        <?php checked($settings['show_excerpt_list_view']); ?> />
                                    <?php esc_html_e('Show event excerpt on calendar list view?', 'skyrocket-events'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Displays the event excerpt in month list and day views when enabled. Never shown in month grid view.', 'skyrocket-events'); ?>
                                </p>
                                <br />


                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Event detail display', 'skyrocket-events'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span>
                                        <?php esc_html_e('Event detail display', 'skyrocket-events'); ?>
                                    </span>
                                </legend>


                                <label for="skyrocket-show-excerpt-detail-view">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-excerpt-detail-view"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_excerpt_detail_view]"
                                        value="1"
                                        <?php checked($settings['show_excerpt_detail_view']); ?> />
                                    <?php esc_html_e('Show event excerpt on event detail view?', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show_upcoming_events">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show_upcoming_events"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_upcoming_events]"
                                        value="1"
                                        <?php checked($settings['show_upcoming_events']); ?> />
                                    <?php esc_html_e('Show upcoming events on the event detail page', 'skyrocket-events'); ?>
                                </label>
                                <br />

                                <label for="skyrocket-show-add-to-calendar-links">
                                    <input
                                        type="checkbox"
                                        id="skyrocket-show-add-to-calendar-links"
                                        name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_add_to_calendar_links]"
                                        value="1"
                                        <?php checked($settings['show_add_to_calendar_links']); ?> />
                                    <?php esc_html_e('Show Add to Google Calendar and Download ICS buttons?', 'skyrocket-events'); ?>
                                </label>


                            </fieldset>
                        </td>
                    </tr>

                </tbody>
            </table>

            <?php submit_button(__('Save Settings', 'skyrocket-events'), 'primary', 'submit', true, ['id' => 'skyrocket-save-settings']); ?>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('skyrocket-events-settings-form');
            const checkboxes = Array.from(document.querySelectorAll('.skyrocket-view-toggle'));
            const submitBtn = document.getElementById('skyrocket-save-settings');
            const msg = document.getElementById('skyrocket-views-validation-msg');

            if (!form || !checkboxes.length || !submitBtn) {
                return;
            }

            function syncViewValidation() {
                const checkedCount = checkboxes.filter((cb) => cb.checked).length;
                const isValid = checkedCount >= 1;

                submitBtn.disabled = !isValid;
                if (msg) {
                    msg.style.display = isValid ? 'none' : 'block';
                }
            }

            checkboxes.forEach((cb) => {
                cb.addEventListener('change', syncViewValidation);
            });

            form.addEventListener('submit', (event) => {
                const checkedCount = checkboxes.filter((cb) => cb.checked).length;
                if (checkedCount < 1) {
                    event.preventDefault();
                    syncViewValidation();
                }
            });

            syncViewValidation();
        })();
    </script>
    <?php
}
