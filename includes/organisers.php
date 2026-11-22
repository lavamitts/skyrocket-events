<?php
/**
 * Organiser post type meta boxes, fields, and list table customisations.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the organiser details meta box.
 */
function skyrocket_organisers_add_meta_boxes(): void
{
    add_meta_box(
        'skyrocket_organiser_details',
        'Organiser Contact Details',
        'skyrocket_organisers_render_meta_box',
        'pavilion_organiser',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'skyrocket_organisers_add_meta_boxes');

/**
 * Render the organiser contact detail fields.
 */
function skyrocket_organisers_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('skyrocket_organisers_save_meta', 'skyrocket_organisers_nonce');

    $contact_name = get_post_meta($post->ID, '_pavilion_org_contact_name', true);
    $email = get_post_meta($post->ID, '_pavilion_org_email', true);
    $phone = get_post_meta($post->ID, '_pavilion_org_phone', true);
    $website = get_post_meta($post->ID, '_pavilion_org_website', true);
    ?>

    <p>
        <label for="skyrocket_org_contact_name"><strong>Contact name</strong></label><br>
        <input type="text" id="skyrocket_org_contact_name" name="skyrocket_org_contact_name"
            value="<?php echo esc_attr($contact_name); ?>" style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_org_email"><strong>Email address</strong></label><br>
        <input type="email" id="skyrocket_org_email" name="skyrocket_org_email" value="<?php echo esc_attr($email); ?>"
            style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_org_phone"><strong>Phone</strong></label><br>
        <input type="text" id="skyrocket_org_phone" name="skyrocket_org_phone" value="<?php echo esc_attr($phone); ?>"
            style="width: 100%; max-width: 400px;">
    </p>

    <p>
        <label for="skyrocket_org_website"><strong>Website</strong></label><br>
        <input type="url" id="skyrocket_org_website" name="skyrocket_org_website" value="<?php echo esc_attr($website); ?>"
            style="width: 100%; max-width: 400px;">
    </p>
    <?php
}

/**
 * Save organiser metadata.
 */
function skyrocket_organisers_save_meta(int $post_id): void
{
    if (
        !isset($_POST['skyrocket_organisers_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['skyrocket_organisers_nonce'])), 'skyrocket_organisers_save_meta')
    ) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $contact_name = isset($_POST['skyrocket_org_contact_name']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_org_contact_name'])) : '';
    $email = isset($_POST['skyrocket_org_email']) ? sanitize_email(wp_unslash($_POST['skyrocket_org_email'])) : '';
    $phone = isset($_POST['skyrocket_org_phone']) ? sanitize_text_field(wp_unslash($_POST['skyrocket_org_phone'])) : '';
    $website = isset($_POST['skyrocket_org_website']) ? esc_url_raw(wp_unslash($_POST['skyrocket_org_website'])) : '';

    update_post_meta($post_id, '_pavilion_org_contact_name', $contact_name);
    update_post_meta($post_id, '_pavilion_org_email', $email);
    update_post_meta($post_id, '_pavilion_org_phone', $phone);
    update_post_meta($post_id, '_pavilion_org_website', $website);
}
add_action('save_post_pavilion_organiser', 'skyrocket_organisers_save_meta');

/**
 * Add 'Related Events' column to organisers list table.
 */
function skyrocket_events_organiser_columns($columns)
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
add_filter('manage_edit-pavilion_organiser_columns', 'skyrocket_events_organiser_columns');

/**
 * Render content for 'Related Events' column in organisers list table.
 */
function skyrocket_events_organiser_custom_column($column_name, $post_id)
{
    if ('related_events' === $column_name) {
        $args = array(
            'post_type' => 'pavilion_event',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_pavilion_organiser_id',
                    'value' => $post_id,
                ),
            ),
        );

        $events_query = new WP_Query($args);
        $count = $events_query->found_posts;

        if ($count > 0) {
            // Link to filtered events list using custom request parameter
            $filter_url = admin_url('edit.php?post_type=pavilion_event&skyrocket_organiser_filter=' . $post_id);
            echo '<a href="' . esc_url($filter_url) . '">' . intval($count) . ' events</a>';
        } else {
            echo '0 events';
        }
    }
}
add_action('manage_pavilion_organiser_posts_custom_column', 'skyrocket_events_organiser_custom_column', 10, 2);

/**
 * Filter the events admin list when filtering by organiser.
 */
function skyrocket_events_filter_events_by_organiser($query)
{
    global $pagenow;

    if ('edit.php' === $pagenow && isset($_GET['post_type']) && 'pavilion_event' === $_GET['post_type'] && !empty($_GET['skyrocket_organiser_filter'])) {
        $organiser_id = absint($_GET['skyrocket_organiser_filter']);
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        $meta_query[] = array(
            'key' => '_pavilion_organiser_id',
            'value' => $organiser_id,
        );
        $query->set('meta_query', $meta_query);
    }
}
add_action('parse_query', 'skyrocket_events_filter_events_by_organiser');