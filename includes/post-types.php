<?php

defined('ABSPATH') || exit;

function tyb_eventcalendar_get_archive_slug() {
    $settings = tyb_eventcalendar_get_settings();
    $slug = sanitize_title((string) $settings['archive_slug']);

    return $slug !== '' ? $slug : 'events';
}

function tyb_eventcalendar_register_post_types() {
    $archive_slug = tyb_eventcalendar_get_archive_slug();

    register_taxonomy(TYB_EVENT_CALENDAR_TAXONOMY, [TYB_EVENT_CALENDAR_POST_TYPE], [
        'labels' => [
            'name' => _x('Event Categories', 'taxonomy general name', 'tyb-event-calendar'),
            'singular_name' => _x('Event Category', 'taxonomy singular name', 'tyb-event-calendar'),
            'search_items' => __('Search Event Categories', 'tyb-event-calendar'),
            'all_items' => __('All Event Categories', 'tyb-event-calendar'),
            'parent_item' => __('Parent Event Category', 'tyb-event-calendar'),
            'parent_item_colon' => __('Parent Event Category:', 'tyb-event-calendar'),
            'edit_item' => __('Edit Event Category', 'tyb-event-calendar'),
            'update_item' => __('Update Event Category', 'tyb-event-calendar'),
            'add_new_item' => __('Add New Event Category', 'tyb-event-calendar'),
            'new_item_name' => __('New Event Category Name', 'tyb-event-calendar'),
            'menu_name' => __('Categories', 'tyb-event-calendar'),
        ],
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => false,
        'show_in_rest' => true,
        'rewrite' => ['slug' => $archive_slug . '/category', 'with_front' => false],
    ]);

    register_post_type(TYB_EVENT_CALENDAR_POST_TYPE, [
        'labels' => [
            'name' => _x('Events', 'post type general name', 'tyb-event-calendar'),
            'singular_name' => _x('Event', 'post type singular name', 'tyb-event-calendar'),
            'menu_name' => _x('Events', 'admin menu', 'tyb-event-calendar'),
            'name_admin_bar' => _x('Event', 'add new on admin bar', 'tyb-event-calendar'),
            'add_new' => __('Add New', 'tyb-event-calendar'),
            'add_new_item' => __('Add New Event', 'tyb-event-calendar'),
            'new_item' => __('New Event', 'tyb-event-calendar'),
            'edit_item' => __('Edit Event', 'tyb-event-calendar'),
            'view_item' => __('View Event', 'tyb-event-calendar'),
            'all_items' => __('All Events', 'tyb-event-calendar'),
            'search_items' => __('Search Events', 'tyb-event-calendar'),
            'not_found' => __('No events found.', 'tyb-event-calendar'),
            'not_found_in_trash' => __('No events found in Trash.', 'tyb-event-calendar'),
            'featured_image' => __('Event image', 'tyb-event-calendar'),
            'set_featured_image' => __('Set event image', 'tyb-event-calendar'),
            'remove_featured_image' => __('Remove event image', 'tyb-event-calendar'),
            'use_featured_image' => __('Use as event image', 'tyb-event-calendar'),
            'archives' => __('Event archives', 'tyb-event-calendar'),
        ],
        'description' => __('Events with a date, a place and categories.', 'tyb-event-calendar'),
        'public' => true,
        'has_archive' => $archive_slug,
        'rewrite' => ['slug' => $archive_slug, 'with_front' => false],
        'menu_position' => 57,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'],
        'show_in_rest' => true,
        'taxonomies' => [TYB_EVENT_CALENDAR_TAXONOMY],
    ]);

    foreach (tyb_eventcalendar_get_meta_keys() as $field => $key) {
        $is_flag = in_array($field, ['all_day', 'is_online', 'featured', 'cancelled'], true);
        register_post_meta(TYB_EVENT_CALENDAR_POST_TYPE, $key, [
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

add_action('init', 'tyb_eventcalendar_register_post_types');

// Front-end archive and category pages: upcoming events first, by start date.
function tyb_eventcalendar_order_archives($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive(TYB_EVENT_CALENDAR_POST_TYPE) && !$query->is_tax(TYB_EVENT_CALENDAR_TAXONOMY)) {
        return;
    }

    $keys = tyb_eventcalendar_get_meta_keys();
    $query->set('meta_key', $keys['start']);
    $query->set('orderby', 'meta_value');
    $query->set('order', 'ASC');

    $now = tyb_eventcalendar_now();
    $query->set('meta_query', [[
        'relation' => 'OR',
        ['key' => $keys['start'], 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'],
        ['key' => $keys['end'], 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'],
    ]]);
}

add_action('pre_get_posts', 'tyb_eventcalendar_order_archives');
