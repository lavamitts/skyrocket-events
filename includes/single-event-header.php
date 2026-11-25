<?php
/**
 * Single event banner widget area registration, rendering, and activation setup.
 *
 * Registers the `skyrocket-single-event-banner` sidebar used on single
 * `pavilion_event` templates, outputs its markup with the event's featured
 * image applied as an inline background, and seeds an empty Custom HTML
 * widget on plugin activation so the widget area appears ready to edit in
 * WordPress Appearance > Widgets.
 *
 * @package Skyrocket_Events
 */

/**
 * Register the Single Event Banner widget area (sidebar).
 *
 * Creates the `skyrocket-single-event-banner` sidebar so site administrators
 * can place blocks or Custom HTML widgets above single event details. Widget
 * titles are hidden visually using WordPress's `screen-reader-text` class.
 *
 * @hook widgets_init
 *
 * @return void
 */
add_action('widgets_init', function () {
    register_sidebar([
        'name' => __('Single Event Banner', 'skyrocket-events'),
        'id' => 'skyrocket-single-event-banner',
        'description' => __('Optional banner content displayed on single event pages. Uses the event featured image as the background when set.', 'skyrocket-events'),
        'before_widget' => '<div id="%1$s" class="skyrocket-event-banner-widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<span class="screen-reader-text">',
        'after_title' => '</span>',
    ]);
});

/**
 * Render the single event banner section if the sidebar contains non-empty output.
 *
 * Captures the rendered HTML of the `skyrocket-single-event-banner` sidebar via
 * output buffering. If the sidebar is inactive or produces only whitespace,
 * nothing is output so empty banner containers are never rendered.
 *
 * When the event post has a featured image, its full-size URL is injected as both
 * a CSS custom property (`--skyrocket-event-bg`) and inline `background-image`
 * cover rules on the `<section>` wrapper.
 *
 * @param int $event_id The post ID of the `pavilion_event` being rendered.
 * @return void Outputs HTML directly when widget content is present.
 */
function skyrocket_render_single_event_banner(int $event_id): void
{
    if (!is_active_sidebar('skyrocket-single-event-banner')) {
        return;
    }

    ob_start();
    dynamic_sidebar('skyrocket-single-event-banner');
    $widget_html = trim((string) ob_get_clean());

    if ($widget_html === '') {
        return;
    }

    $featured_url = get_the_post_thumbnail_url($event_id, 'full');
    $style_attr = '';

    if ($featured_url) {
        $safe_url = esc_url($featured_url);
        $style_attr = sprintf(
            ' style="--skyrocket-event-bg: url(\'%s\'); background-image: url(\'%s\'); background-size: cover; background-position: center;"',
            $safe_url,
            $safe_url
        );
    }
    ?>
    <section class="skyrocket-event-detail__banner" <?php echo $style_attr; ?>>
        <?php echo $widget_html; // Custom HTML / blocks saved in Widgets ?>
    </section>
    <?php
}

/**
 * Seed a default Custom HTML widget in the Single Event Banner sidebar on activation.
 *
 * Inspects `sidebars_widgets` and `widget_custom_html` options on plugin activation:
 * - Exits early if `skyrocket-single-event-banner` already has widgets assigned,
 *   preserving existing site configuration across reactivations.
 * - Finds the highest existing numeric instance key in `widget_custom_html` and
 *   allocates the next sequential integer ID.
 * - Inserts a placeholder `<div></div>` Custom HTML widget instance and assigns
 *   it to `skyrocket-single-event-banner` so the banner area renders immediately
 *   and is visible in the Widgets screen.
 *
 * Note: `register_activation_hook(__FILE__, ...)` requires this code to reside in
 * the main plugin bootstrap file (or receive the main plugin file path) to fire.
 *
 * @hook register_activation_hook
 *
 * @return void
 */
register_activation_hook(__FILE__, function () {
    $sidebar_id = 'skyrocket-single-event-banner';
    $sidebars = get_option('sidebars_widgets', []);

    if (!is_array($sidebars)) {
        $sidebars = [];
    }

    // Do nothing if the sidebar already has widgets assigned
    if (!empty($sidebars[$sidebar_id])) {
        return;
    }

    $html_widgets = get_option('widget_custom_html', []);
    if (!is_array($html_widgets)) {
        $html_widgets = [];
    }

    // Find next numeric instance ID
    $max_id = 0;
    foreach (array_keys($html_widgets) as $key) {
        if (is_numeric($key) && (int) $key > $max_id) {
            $max_id = (int) $key;
        }
    }

    $next_id = $max_id + 1;

    $html_widgets[$next_id] = [
        'title' => '',
        'content' => '<div></div>',
    ];
    $html_widgets['_multiwidget'] = 1;

    update_option('widget_custom_html', $html_widgets);

    $sidebars[$sidebar_id] = ['custom_html-' . $next_id];
    update_option('sidebars_widgets', $sidebars);
});
