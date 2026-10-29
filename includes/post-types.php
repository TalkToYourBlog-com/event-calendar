<?php

defined('ABSPATH') || exit;

function talktoyourblog_eventcalendar_get_archive_slug() {
    $settings = talktoyourblog_eventcalendar_get_settings();
    $slug = sanitize_title((string) $settings['archive_slug']);

    return $slug !== '' ? $slug : 'events';
}

function talktoyourblog_eventcalendar_register_post_types() {
    $archive_slug = talktoyourblog_eventcalendar_get_archive_slug();

    register_taxonomy(TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY, [TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE], [
        'labels' => [
            'name' => _x('Event Categories', 'taxonomy general name', 'talktoyourblog-event-calendar'),
            'singular_name' => _x('Event Category', 'taxonomy singular name', 'talktoyourblog-event-calendar'),
            'search_items' => __('Search Event Categories', 'talktoyourblog-event-calendar'),
            'all_items' => __('All Event Categories', 'talktoyourblog-event-calendar'),
            'parent_item' => __('Parent Event Category', 'talktoyourblog-event-calendar'),
            'parent_item_colon' => __('Parent Event Category:', 'talktoyourblog-event-calendar'),
            'edit_item' => __('Edit Event Category', 'talktoyourblog-event-calendar'),
            'update_item' => __('Update Event Category', 'talktoyourblog-event-calendar'),
            'add_new_item' => __('Add New Event Category', 'talktoyourblog-event-calendar'),
            'new_item_name' => __('New Event Category Name', 'talktoyourblog-event-calendar'),
            'menu_name' => __('Categories', 'talktoyourblog-event-calendar'),
        ],
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => false,
        'show_in_rest' => true,
        'rewrite' => ['slug' => $archive_slug . '/category', 'with_front' => false],
    ]);

    register_post_type(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE, [
        'labels' => [
            'name' => _x('Events', 'post type general name', 'talktoyourblog-event-calendar'),
            'singular_name' => _x('Event', 'post type singular name', 'talktoyourblog-event-calendar'),
            'menu_name' => _x('Events', 'admin menu', 'talktoyourblog-event-calendar'),
            'name_admin_bar' => _x('Event', 'add new on admin bar', 'talktoyourblog-event-calendar'),
            'add_new' => __('Add New', 'talktoyourblog-event-calendar'),
            'add_new_item' => __('Add New Event', 'talktoyourblog-event-calendar'),
            'new_item' => __('New Event', 'talktoyourblog-event-calendar'),
            'edit_item' => __('Edit Event', 'talktoyourblog-event-calendar'),
            'view_item' => __('View Event', 'talktoyourblog-event-calendar'),
            'all_items' => __('All Events', 'talktoyourblog-event-calendar'),
            'search_items' => __('Search Events', 'talktoyourblog-event-calendar'),
            'not_found' => __('No events found.', 'talktoyourblog-event-calendar'),
            'not_found_in_trash' => __('No events found in Trash.', 'talktoyourblog-event-calendar'),
            'featured_image' => __('Event image', 'talktoyourblog-event-calendar'),
            'set_featured_image' => __('Set event image', 'talktoyourblog-event-calendar'),
            'remove_featured_image' => __('Remove event image', 'talktoyourblog-event-calendar'),
            'use_featured_image' => __('Use as event image', 'talktoyourblog-event-calendar'),
            'archives' => __('Event archives', 'talktoyourblog-event-calendar'),
        ],
        'description' => __('Events with a date, a place and categories.', 'talktoyourblog-event-calendar'),
        'public' => true,
        'has_archive' => $archive_slug,
        'rewrite' => ['slug' => $archive_slug, 'with_front' => false],
        'menu_position' => 57,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'],
        'show_in_rest' => true,
        'taxonomies' => [TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY],
    ]);

    foreach (talktoyourblog_eventcalendar_get_meta_keys() as $field => $key) {
        $is_flag = in_array($field, ['all_day', 'is_online', 'featured', 'cancelled'], true);
        register_post_meta(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE, $key, [
            'type' => $is_flag ? 'boolean' : 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => $is_flag ? 'rest_sanitize_boolean' : 'sanitize_text_field',
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}

add_action('init', 'talktoyourblog_eventcalendar_register_post_types');

// Front-end archive and category pages: upcoming events first, by start date.
function talktoyourblog_eventcalendar_order_archives($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE) && !$query->is_tax(TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY)) {
        return;
    }

    $keys = talktoyourblog_eventcalendar_get_meta_keys();
    $query->set('meta_key', $keys['start']);
    $query->set('orderby', 'meta_value');
    $query->set('order', 'ASC');

    $now = talktoyourblog_eventcalendar_now();
    $query->set('meta_query', [[
        'relation' => 'OR',
        ['key' => $keys['start'], 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'],
        ['key' => $keys['end'], 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'],
    ]]);
}

add_action('pre_get_posts', 'talktoyourblog_eventcalendar_order_archives');
