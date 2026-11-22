<?php
/**
 * Venue post type meta boxes, fields, and list table customisations.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the venue details meta box.
 */
function skyrocket_venues_add_meta_boxes(): void
{
    add_meta_box(
        'skyrocket_venue_details',
        'Venue Details',
        'skyrocket_venues_render_meta_box',
        'pavilion_venue',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'skyrocket_venues_add_meta_boxes');

/**
 * Render the venue detail fields.
 */
function skyrocket_venues_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('skyrocket_venues_save_meta', 'skyrocket_venues_nonce');

    $address = get_post_meta($post->ID, '_pavilion_venue_address', true);
    $city = get_post_meta($post->ID, '_pavilion_venue_city', true);
    $country = get_post_meta($post->ID, '_pavilion_venue_country', true);
    $postcode = get_post_meta($post->ID, '_pavilion_venue_postcode', true);
    $phone = get_post_meta($post->ID, '_pavilion_venue_phone', true);
    $website = get_post_meta($post->ID, '_pavilion_venue_website', true);
    $include_map = get_post_meta($post->ID, '_pavilion_venue_include_map', true);
    ?>

    <p>
        <label for="skyrocket_venue_address"><strong>Address</strong></label><br>
        <textarea id="skyrocket_venue_address" name="skyrocket_venue_address" rows="2" style="width: 100%; max-width: 400px;"><?php echo esc_textarea($address); ?></textarea>
    </p>

    <p>
        <label for="skyrocket_venue_city"><strong>City</strong></label><br>
        <input type="text" id="skyrocket_venue_city" name="skyrocket_venue_city"
            value="<?php echo esc_attr($city); ?>" style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_venue_country"><strong>Country</strong></label><br>
        <input type="text" id="skyrocket_venue_country" name="skyrocket_venue_country"
            value="<?php echo esc_attr($country); ?>" style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_venue_postcode"><strong>Postcode</strong></label><br>
        <input type="text" id="skyrocket_venue_postcode" name="skyrocket_venue_postcode"
            value="<?php echo esc_attr($postcode); ?>" style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_venue_phone"><strong>Phone</strong></label><br>
        <input type="text" id="skyrocket_venue_phone" name="skyrocket_venue_phone"
            value="<?php echo esc_attr($phone); ?>" style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_venue_website"><strong>Website</strong></label><br>
        <input type="url" id="skyrocket_venue_website" name="skyrocket_venue_website"
            value="<?php echo esc_attr($website); ?>" style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label>
            <input type="checkbox" name="skyrocket_venue_include_map" value="1" <?php checked($include_map, '1'); ?>>
            <strong>Include map</strong>
        </label>
    </p>
    <?php
}

/**
 * Save venue metadata.
 */
function skyrocket_venues_save_meta(int $post_id): void
{
    if (
        !isset($_POST['skyrocket_venues_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['skyrocket_venues_nonce'])), 'skyrocket_venues_save_meta')
    ) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $address = isset($_POST['skyrocket_venue_address']) ? sanitize_textarea_field(wp_unslash($_POST['skyrocket_venue_address'])) : '';
    $city = isset($_POST['skyrocket_venue_city']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_venue_city'])) : '';
    $country = isset($_POST['skyrocket_venue_country']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_venue_country'])) : '';
    $postcode = isset($_POST['skyrocket_venue_postcode']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_venue_postcode'])) : '';
    $phone = isset($_POST['skyrocket_venue_phone']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_venue_phone'])) : '';
    $website = isset($_POST['skyrocket_venue_website']) ? esc_url_raw(wp_unslash($_POST['skyrocket_venue_website'])) : '';
    $include_map = isset($_POST['skyrocket_venue_include_map']) ? '1' : '';

    update_post_meta($post_id, '_pavilion_venue_address', $address);
    update_post_meta($post_id, '_pavilion_venue_city', $city);
    update_post_meta($post_id, '_pavilion_venue_country', $country);
    update_post_meta($post_id, '_pavilion_venue_postcode', $postcode);
    update_post_meta($post_id, '_pavilion_venue_phone', $phone);
    update_post_meta($post_id, '_pavilion_venue_website', $website);
    update_post_meta($post_id, '_pavilion_venue_include_map', $include_map);
}
add_action('save_post_pavilion_venue', 'skyrocket_venues_save_meta');

/**
 * Add 'Related Events' column to venues list table.
 */
function skyrocket_events_venue_columns($columns)
{
    $new_columns = array();
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ('title' === $key) {
            $new_columns['related_events'] = 'Related Events';
        }
    }
    return $new_columns;
}
add_filter('manage_edit-pavilion_venue_columns', 'skyrocket_events_venue_columns');

/**
 * Render content for 'Related Events' column in venues list table.
 */
function skyrocket_events_venue_custom_column($column_name, $post_id)
{
    if ('related_events' === $column_name) {
        $args = array(
            'post_type' => 'pavilion_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_pavilion_venue_id',
                    'value' => $post_id,
                ),
            ),
        );

        $events_query = new WP_Query($args);
        $count = $events_query->found_posts;

        if ($count > 0) {
            $filter_url = admin_url('edit.php?post_type=pavilion_event&skyrocket_venue_filter=' . $post_id);
            echo '<a href="' . esc_url($filter_url) . '">' . intval($count) . ' events</a>';
        } else {
            echo '0 events';
        }
    }
}
add_action('manage_pavilion_venue_posts_custom_column', 'skyrocket_events_venue_custom_column', 10, 2);

/**
 * Filter the events admin list when filtering by venue.
 */
function skyrocket_events_filter_events_by_venue($query)
{
    global $pagenow;

    if ('edit.php' === $pagenow && isset($_GET['post_type']) && 'pavilion_event' === $_GET['post_type'] && !empty($_GET['skyrocket_venue_filter'])) {
        $venue_id = absint($_GET['skyrocket_venue_filter']);
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        $meta_query[] = array(
            'key' => '_pavilion_venue_id',
            'value' => $venue_id,
        );
        $query->set('meta_query', $meta_query);
    }
}
add_action('parse_query', 'skyrocket_events_filter_events_by_venue');