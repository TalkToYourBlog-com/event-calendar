<?php

defined('ABSPATH') || exit;

// Meta keys of an event. Dates are wall-clock "Y-m-d H:i:s" in the event's own time
// zone (or the site's), not UTC: a workshop at 18:00 stays at 18:00.
function talktoyourblog_eventcalendar_get_meta_keys() {
    return [
        'start' => '_talktoyourblog_event_start',
        'end' => '_talktoyourblog_event_end',
        'all_day' => '_talktoyourblog_event_all_day',
        'timezone' => '_talktoyourblog_event_timezone',
        'location' => '_talktoyourblog_event_location',
        'location_url' => '_talktoyourblog_event_location_url',
        'is_online' => '_talktoyourblog_event_is_online',
        'url' => '_talktoyourblog_event_url',
        'featured' => '_talktoyourblog_event_featured',
        'cancelled' => '_talktoyourblog_event_cancelled',
    ];
}

function talktoyourblog_eventcalendar_get_event_meta($post_id) {
    $keys = talktoyourblog_eventcalendar_get_meta_keys();
    $meta = [];

    foreach ($keys as $field => $key) {
        $meta[$field] = get_post_meta($post_id, $key, true);
    }

    foreach (['all_day', 'is_online', 'featured', 'cancelled'] as $flag) {
        $meta[$flag] = (int) $meta[$flag] === 1;
    }

    return $meta;
}

// Colours are stored as #rrggbb; anything else falls back to the default.
function talktoyourblog_eventcalendar_normalize_color($raw_color) {
    $color = strtolower(trim((string) $raw_color));

    if (preg_match('/^#?([0-9a-f]{6})$/', $color, $matches)) {
        return '#' . $matches[1];
    }

    if (preg_match('/^#?([0-9a-f]{3})$/', $color, $matches)) {
        $pairs = array_map(static function ($c) {
            return $c . $c;
        }, str_split($matches[1]));
        return '#' . implode('', $pairs);
    }

    return TALKTOYOURBLOG_EVENT_CALENDAR_DEFAULT_CATEGORY_COLOR;
}

// Text colour that stays readable on a category colour used as a background.
function talktoyourblog_eventcalendar_contrast_color($hex) {
    $n = hexdec(substr(talktoyourblog_eventcalendar_normalize_color($hex), 1));
    $luma = (0.299 * (($n >> 16) & 255) + 0.587 * (($n >> 8) & 255) + 0.114 * ($n & 255)) / 255;

    return $luma > 0.6 ? '#1d2327' : '#ffffff';
}

function talktoyourblog_eventcalendar_get_category_color($term_id) {
    return talktoyourblog_eventcalendar_normalize_color(get_term_meta($term_id, 'talktoyourblog_event_color', true));
}

// datetime-local input ("2026-10-01T18:30") or strtotime()-readable text to "Y-m-d H:i:s"; '' when unreadable.
function talktoyourblog_eventcalendar_normalize_datetime($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw . ' UTC');
    if ($timestamp === false) {
        return '';
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}

function talktoyourblog_eventcalendar_datetime_input_value($stored) {
    $stored = trim((string) $stored);
    if ($stored === '') {
        return '';
    }

    return str_replace(' ', 'T', substr($stored, 0, 16));
}

function talktoyourblog_eventcalendar_sanitize_timezone($raw) {
    $timezone = trim((string) $raw);
    if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
        return '';
    }

    return $timezone;
}

// Formats a stored wall-clock date with the site's date/time formats.
function talktoyourblog_eventcalendar_format_datetime($stored, $all_day = false, $timezone = '') {
    $stored = trim((string) $stored);
    if ($stored === '') {
        return '';
    }

    $timestamp = strtotime($stored . ' UTC');
    if ($timestamp === false) {
        return '';
    }

    $date = date_i18n(get_option('date_format'), $timestamp, true);
    if ($all_day) {
        return $date;
    }

    $time = date_i18n(get_option('time_format'), $timestamp, true);
    $out = sprintf(
        /* translators: 1: date, 2: time */
        _x('%1$s at %2$s', 'event date and time', 'talktoyourblog-event-calendar'),
        $date,
        $time
    );

    if ($timezone !== '' && $timezone !== wp_timezone_string()) {
        $out .= ' (' . $timezone . ')';
    }

    return $out;
}

// "Y-m-d\TH:i:sP" for JSON-LD; the offset comes from the event's zone or the site's.
function talktoyourblog_eventcalendar_iso8601($stored, $timezone = '') {
    $stored = trim((string) $stored);
    if ($stored === '') {
        return '';
    }

    try {
        $zone = new DateTimeZone($timezone !== '' ? $timezone : wp_timezone_string());
        $date = new DateTime($stored, $zone);
    } catch (Exception $e) {
        return '';
    }

    return $date->format('c');
}

// Current wall-clock time in the site's zone, comparable with stored event dates.
function talktoyourblog_eventcalendar_now() {
    return current_time('Y-m-d H:i:s');
}

/**
 * Events as WP_Query. Args: upcoming (bool), category (slug|id), from/to ("Y-m-d H:i:s"),
 * limit (int, -1 for all), featured (bool), paged (int).
 */
function talktoyourblog_eventcalendar_query_events(array $args = []) {
    $keys = talktoyourblog_eventcalendar_get_meta_keys();
    $args = array_merge([
        'upcoming' => true,
        'category' => '',
        'from' => '',
        'to' => '',
        'limit' => 10,
        'featured' => false,
        'paged' => 1,
    ], $args);

    $meta_query = ['relation' => 'AND'];

    if ($args['from'] !== '' || $args['to'] !== '') {
        if ($args['from'] !== '') {
            // An event that started earlier but has not ended yet still belongs to the range.
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => $keys['start'], 'value' => $args['from'], 'compare' => '>=', 'type' => 'DATETIME'],
                ['key' => $keys['end'], 'value' => $args['from'], 'compare' => '>=', 'type' => 'DATETIME'],
            ];
        }
        if ($args['to'] !== '') {
            $meta_query[] = ['key' => $keys['start'], 'value' => $args['to'], 'compare' => '<=', 'type' => 'DATETIME'];
        }
    } elseif ($args['upcoming']) {
        $now = talktoyourblog_eventcalendar_now();
        $meta_query[] = [
            'relation' => 'OR',
            ['key' => $keys['start'], 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'],
            ['key' => $keys['end'], 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'],
        ];
    }

    if ($args['featured']) {
        $meta_query[] = ['key' => $keys['featured'], 'value' => '1'];
    }

    $query_args = [
        'post_type' => TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => (int) $args['limit'],
        'paged' => max(1, (int) $args['paged']),
        'meta_key' => $keys['start'],
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => $meta_query,
    ];

    if ($args['category'] !== '') {
        $query_args['tax_query'] = [[
            'taxonomy' => TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY,
            'field' => is_numeric($args['category']) ? 'term_id' : 'slug',
            'terms' => is_numeric($args['category']) ? (int) $args['category'] : sanitize_title($args['category']),
        ]];
    }

    return new WP_Query(apply_filters('talktoyourblog_eventcalendar_query_args', $query_args, $args));
}
