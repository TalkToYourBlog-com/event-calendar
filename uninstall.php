<?php
/**
 * Runs when the plugin is deleted from the Plugins screen. Data is removed only when
 * the user ticked "Delete all events, categories and settings" in the settings.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$talktoyourblog_ec_settings = get_option('talktoyourblog_eventcalendar_settings', []);
$talktoyourblog_ec_delete = is_array($talktoyourblog_ec_settings) && !empty($talktoyourblog_ec_settings['delete_data_on_uninstall']);

if ($talktoyourblog_ec_delete) {
    $talktoyourblog_ec_posts = get_posts([
        'post_type' => 'talktoyourblog_event',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
    ]);
    foreach ($talktoyourblog_ec_posts as $talktoyourblog_ec_post_id) {
        wp_delete_post($talktoyourblog_ec_post_id, true);
    }

    $talktoyourblog_ec_terms = get_terms([
        'taxonomy' => 'talktoyourblog_event_cat',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);
    if (is_array($talktoyourblog_ec_terms)) {
        foreach ($talktoyourblog_ec_terms as $talktoyourblog_ec_term_id) {
            wp_delete_term($talktoyourblog_ec_term_id, 'talktoyourblog_event_cat');
        }
    }

    delete_option('talktoyourblog_eventcalendar_settings');
}

delete_option('talktoyourblog_eventcalendar_plugin_version');
