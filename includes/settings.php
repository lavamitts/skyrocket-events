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
        'show_day_view' => 1,
        'show_excerpt' => 0,
    ];
}

/**
 * Retrieve normalised calendar settings.
 *
 * @return array<string, bool>
 */
function skyrocket_events_get_calendar_settings(): array
{
    $defaults = skyrocket_events_get_default_settings();
    $saved = get_option(SKYROCKET_EVENTS_OPTION_NAME, []);

    if (!is_array($saved)) {
        $saved = [];
    }

    $merged = wp_parse_args($saved, $defaults);

    $settings = [
        'show_month_grid' => !empty($merged['show_month_grid']),
        'show_month_list' => !empty($merged['show_month_list']),
        'show_day_view' => !empty($merged['show_day_view']),
        'show_excerpt' => !empty($merged['show_excerpt']),
    ];

    // Safety fallback in case options were manipulated directly in the database.
    if (!$settings['show_month_grid'] && !$settings['show_month_list'] && !$settings['show_day_view']) {
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
 * Sanitize raw submitted checkbox values.
 *
 * @param mixed $input Raw POST array.
 * @return array<string, int>
 */
function skyrocket_events_sanitize_settings($input): array
{
    $input = is_array($input) ? $input : [];

    return [
        'show_month_grid' => !empty($input['show_month_grid']) ? 1 : 0,
        'show_month_list' => !empty($input['show_month_list']) ? 1 : 0,
        'show_day_view' => !empty($input['show_day_view']) ? 1 : 0,
        'show_excerpt' => !empty($input['show_excerpt']) ? 1 : 0,
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
    $day = !empty($new_value['show_day_view']);

    if (!$grid && !$list && !$day) {
        add_settings_error(
            SKYROCKET_EVENTS_OPTION_NAME,
            'skyrocket_events_no_views_selected',
            __('At least one calendar view (month grid, month list, or day view) must remain enabled. Your changes were not saved.', 'skyrocket-events'),
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
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php settings_errors(SKYROCKET_EVENTS_OPTION_NAME); ?>

        <form method="post" action="options.php" id="skyrocket-events-settings-form">
            <?php settings_fields('skyrocket_events_settings_group'); ?>

            <table class="form-table" role="presentation">
                <tbody>
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

                                <p class="description" id="skyrocket-views-validation-msg" style="color: #b32d2e; display: none;">
                                    <?php esc_html_e('Please select at least one view before saving.', 'skyrocket-events'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Event details', 'skyrocket-events'); ?></th>
                        <td>
                            <label for="skyrocket-show-excerpt">
                                <input
                                    type="checkbox"
                                    id="skyrocket-show-excerpt"
                                    name="<?php echo esc_attr(SKYROCKET_EVENTS_OPTION_NAME); ?>[show_excerpt]"
                                    value="1"
                                    <?php checked($settings['show_excerpt']); ?> />
                                <?php esc_html_e('Show event excerpt on calendar?', 'skyrocket-events'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Displays the event excerpt in month list and day views when enabled. Never shown in month grid view.', 'skyrocket-events'); ?>
                            </p>
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