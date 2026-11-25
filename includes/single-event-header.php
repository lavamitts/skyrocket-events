<?php
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