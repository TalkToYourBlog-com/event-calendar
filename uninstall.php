<?php
/**
 * Runs when the plugin is deleted from the Plugins screen. Data is removed only when
 * the user ticked "Delete all events, categories and settings" in the settings.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$tyb_ec_settings = get_option('tyb_eventcalendar_settings', []);
$tyb_ec_delete = is_array($tyb_ec_settings) && !empty($tyb_ec_settings['delete_data_on_uninstall']);

if ($tyb_ec_delete) {
    $tyb_ec_posts = get_posts([
        'post_type' => 'tyb_event',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
    ]);
    foreach ($tyb_ec_posts as $tyb_ec_post_id) {
        wp_delete_post($tyb_ec_post_id, true);
    }

    $tyb_ec_terms = get_terms([
        'taxonomy' => 'tyb_event_cat',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);
    if (is_array($tyb_ec_terms)) {
        foreach ($tyb_ec_terms as $tyb_ec_term_id) {
            wp_delete_term($tyb_ec_term_id, 'tyb_event_cat');
        }
    }

    delete_option('tyb_eventcalendar_settings');
}

delete_option('tyb_eventcalendar_plugin_version');
