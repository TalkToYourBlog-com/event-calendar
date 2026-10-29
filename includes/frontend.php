<?php

defined('ABSPATH') || exit;

function tyb_eventcalendar_register_frontend_assets() {
    wp_register_style(
        'tyb-event-calendar',
        TYB_EVENT_CALENDAR_PLUGIN_URL . 'assets/frontend/calendar.css',
        [],
        TYB_EVENT_CALENDAR_VERSION
    );
}

add_action('init', 'tyb_eventcalendar_register_frontend_assets');

function tyb_eventcalendar_enqueue_on_event_pages() {
    if (is_singular(TYB_EVENT_CALENDAR_POST_TYPE) || is_post_type_archive(TYB_EVENT_CALENDAR_POST_TYPE) || is_tax(TYB_EVENT_CALENDAR_TAXONOMY)) {
        wp_enqueue_style('tyb-event-calendar');
    }
}

add_action('wp_enqueue_scripts', 'tyb_eventcalendar_enqueue_on_event_pages');

// ─── Shared pieces ───────────────────────────────────────────────────────────

function tyb_eventcalendar_category_badges($post_id, $link = true) {
    $terms = get_the_terms($post_id, TYB_EVENT_CALENDAR_TAXONOMY);
    if (!is_array($terms) || empty($terms)) {
        return '';
    }

    $html = '<span class="tyb-ec-badges">';
    foreach ($terms as $term) {
        $color = tyb_eventcalendar_get_category_color($term->term_id);
        $style = 'background:' . esc_attr($color) . ';color:' . esc_attr(tyb_eventcalendar_contrast_color($color));
        $term_link = $link ? get_term_link($term) : '';
        if ($link && !is_wp_error($term_link)) {
            $html .= '<a class="tyb-ec-badge" style="' . $style . '" href="' . esc_url($term_link) . '">' . esc_html($term->name) . '</a>';
        } else {
            $html .= '<span class="tyb-ec-badge" style="' . $style . '">' . esc_html($term->name) . '</span>';
        }
    }
    return $html . '</span>';
}

function tyb_eventcalendar_when_text(array $meta) {
    $start = tyb_eventcalendar_format_datetime($meta['start'], $meta['all_day'], $meta['timezone']);
    if ($start === '') {
        return '';
    }

    if ($meta['end'] === '') {
        return $start;
    }

    $same_day = substr($meta['start'], 0, 10) === substr($meta['end'], 0, 10);
    if ($same_day && !$meta['all_day']) {
        $end_time = date_i18n(get_option('time_format'), strtotime($meta['end'] . ' UTC'), true);
        /* translators: 1: start date and time, 2: end time */
        return sprintf(_x('%1$s – %2$s', 'event time range on one day', 'tyb-event-calendar'), $start, $end_time);
    }
    if ($same_day) {
        return $start;
    }

    /* translators: 1: start, 2: end */
    return sprintf(_x('%1$s – %2$s', 'event date range', 'tyb-event-calendar'), $start, tyb_eventcalendar_format_datetime($meta['end'], $meta['all_day'], $meta['timezone']));
}

function tyb_eventcalendar_where_html(array $meta) {
    $label = $meta['is_online'] ? __('Online', 'tyb-event-calendar') : $meta['location'];
    if ($label === '') {
        return '';
    }

    if ($meta['location_url'] !== '') {
        return '<a href="' . esc_url($meta['location_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
    }

    return esc_html($label);
}

// ─── Shortcode: [tyb_event_calendar] ───────────────────────────────────────

function tyb_eventcalendar_shortcode($atts = []) {
    $atts = shortcode_atts([
        'view' => 'list',
        'category' => '',
        'limit' => 10,
        'upcoming' => 'yes',
        'featured' => 'no',
        'month' => '',
    ], $atts, 'tyb_event_calendar');

    wp_enqueue_style('tyb-event-calendar');

    $truthy = static function ($value) {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    };

    if (strtolower(trim((string) $atts['view'])) === 'month') {
        return tyb_eventcalendar_render_month($atts, $truthy);
    }

    $query = tyb_eventcalendar_query_events([
        'upcoming' => $truthy($atts['upcoming']),
        'category' => (string) $atts['category'],
        'limit' => (int) $atts['limit'] === 0 ? 10 : (int) $atts['limit'],
        'featured' => $truthy($atts['featured']),
    ]);

    if (!$query->have_posts()) {
        return '<div class="tyb-ec tyb-ec-list"><p class="tyb-ec-empty">' . esc_html__('No upcoming events.', 'tyb-event-calendar') . '</p></div>';
    }

    $html = '<div class="tyb-ec tyb-ec-list">';
    $current_month = '';
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $meta = tyb_eventcalendar_get_event_meta($post_id);

        $month = date_i18n('F Y', strtotime($meta['start'] . ' UTC'), true);
        if ($month !== $current_month) {
            $html .= '<h3 class="tyb-ec-month">' . esc_html($month) . '</h3>';
            $current_month = $month;
        }

        $classes = 'tyb-ec-item' . ($meta['cancelled'] ? ' is-cancelled' : '') . ($meta['featured'] ? ' is-featured' : '');
        $html .= '<article class="' . esc_attr($classes) . '">';
        $html .= '<div class="tyb-ec-date"><span class="tyb-ec-day">' . esc_html(date_i18n('j', strtotime($meta['start'] . ' UTC'), true)) . '</span><span class="tyb-ec-mon">' . esc_html(date_i18n('M', strtotime($meta['start'] . ' UTC'), true)) . '</span></div>';
        $html .= '<div class="tyb-ec-body">';
        $html .= '<h4 class="tyb-ec-title"><a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
        if ($meta['cancelled']) {
            $html .= ' <span class="tyb-ec-cancelled">' . esc_html__('Cancelled', 'tyb-event-calendar') . '</span>';
        }
        $html .= '</h4>';
        $html .= '<p class="tyb-ec-when">' . esc_html(tyb_eventcalendar_when_text($meta)) . '</p>';
        $where = tyb_eventcalendar_where_html($meta);
        if ($where !== '') {
            $html .= '<p class="tyb-ec-where">' . $where . '</p>';
        }
        if (has_excerpt($post_id)) {
            $html .= '<p class="tyb-ec-excerpt">' . esc_html(get_the_excerpt($post_id)) . '</p>';
        }
        $html .= tyb_eventcalendar_category_badges($post_id);
        $html .= '</div></article>';
    }
    wp_reset_postdata();

    return $html . '</div>';
}

add_shortcode('tyb_event_calendar', 'tyb_eventcalendar_shortcode');

function tyb_eventcalendar_render_month(array $atts, callable $truthy) {
    // Month from the URL (prev/next links) wins over the shortcode attribute.
    $requested = isset($_GET['tyb_month']) ? sanitize_text_field(wp_unslash($_GET['tyb_month'])) : (string) $atts['month']; // phpcs:ignore WordPress.Security.NonceVerification -- read-only navigation
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested)) {
        $requested = current_time('Y-m');
    }

    $first = $requested . '-01 00:00:00';
    $first_ts = strtotime($first . ' UTC');
    $days_in_month = (int) gmdate('t', $first_ts);
    $last = $requested . '-' . $days_in_month . ' 23:59:59';

    $query = tyb_eventcalendar_query_events([
        'from' => $first,
        'to' => $last,
        'limit' => -1,
        'category' => (string) $atts['category'],
        'featured' => $truthy($atts['featured']),
    ]);

    // Events keyed by day; multi-day events land on every day they cover.
    $by_day = [];
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $meta = tyb_eventcalendar_get_event_meta($post_id);
        $start_day = max(1, (int) gmdate('j', strtotime($meta['start'] . ' UTC')));
        $end_day = $start_day;
        if ($meta['end'] !== '' && substr($meta['end'], 0, 7) === $requested) {
            $end_day = max($start_day, (int) gmdate('j', strtotime($meta['end'] . ' UTC')));
        } elseif ($meta['end'] !== '' && $meta['end'] > $last) {
            $end_day = $days_in_month;
        }
        if (substr($meta['start'], 0, 7) < $requested) {
            $start_day = 1;
        }
        for ($d = $start_day; $d <= $end_day; $d++) {
            $by_day[$d][] = ['id' => $post_id, 'meta' => $meta];
        }
    }
    wp_reset_postdata();

    $week_starts = (int) get_option('start_of_week', 1);
    $first_weekday = (int) gmdate('N', $first_ts) % 7; // 0 = Sunday
    $offset = ($first_weekday - $week_starts + 7) % 7;
    $today = current_time('Y-m-d');

    global $wp_locale;
    $prev = gmdate('Y-m', strtotime('-1 month', $first_ts));
    $next = gmdate('Y-m', strtotime('+1 month', $first_ts));
    $base_url = remove_query_arg('tyb_month');

    $html = '<div class="tyb-ec tyb-ec-month-view">';
    $html .= '<div class="tyb-ec-nav">';
    $html .= '<a class="tyb-ec-nav-prev" href="' . esc_url(add_query_arg('tyb_month', $prev, $base_url)) . '" aria-label="' . esc_attr__('Previous month', 'tyb-event-calendar') . '">&lsaquo;</a>';
    $html .= '<h3 class="tyb-ec-month">' . esc_html(date_i18n('F Y', $first_ts, true)) . '</h3>';
    $html .= '<a class="tyb-ec-nav-next" href="' . esc_url(add_query_arg('tyb_month', $next, $base_url)) . '" aria-label="' . esc_attr__('Next month', 'tyb-event-calendar') . '">&rsaquo;</a>';
    $html .= '</div>';

    $html .= '<table class="tyb-ec-grid"><thead><tr>';
    for ($i = 0; $i < 7; $i++) {
        $html .= '<th scope="col">' . esc_html($wp_locale->get_weekday_abbrev($wp_locale->get_weekday(($week_starts + $i) % 7))) . '</th>';
    }
    $html .= '</tr></thead><tbody><tr>';

    $cell = 0;
    for ($i = 0; $i < $offset; $i++, $cell++) {
        $html .= '<td class="tyb-ec-pad"></td>';
    }
    for ($day = 1; $day <= $days_in_month; $day++, $cell++) {
        if ($cell > 0 && $cell % 7 === 0) {
            $html .= '</tr><tr>';
        }
        $date = sprintf('%s-%02d', $requested, $day);
        $classes = 'tyb-ec-day-cell' . ($date === $today ? ' is-today' : '') . (isset($by_day[$day]) ? ' has-events' : '');
        $html .= '<td class="' . esc_attr($classes) . '"><span class="tyb-ec-daynum">' . esc_html((string) $day) . '</span>';
        foreach ($by_day[$day] ?? [] as $entry) {
            $terms = get_the_terms($entry['id'], TYB_EVENT_CALENDAR_TAXONOMY);
            $color = is_array($terms) && !empty($terms) ? tyb_eventcalendar_get_category_color($terms[0]->term_id) : TYB_EVENT_CALENDAR_DEFAULT_CATEGORY_COLOR;
            $title = get_the_title($entry['id']);
            $time = $entry['meta']['all_day'] ? '' : date_i18n(get_option('time_format'), strtotime($entry['meta']['start'] . ' UTC'), true);
            $html .= '<a class="tyb-ec-chip' . ($entry['meta']['cancelled'] ? ' is-cancelled' : '') . '" style="--tyb-ec-color:' . esc_attr($color) . '" href="' . esc_url(get_permalink($entry['id'])) . '" title="' . esc_attr($title) . '">';
            if ($time !== '') {
                $html .= '<span class="tyb-ec-chip-time">' . esc_html($time) . '</span> ';
            }
            $html .= esc_html($title) . '</a>';
        }
        $html .= '</td>';
    }
    while ($cell % 7 !== 0) {
        $html .= '<td class="tyb-ec-pad"></td>';
        $cell++;
    }
    $html .= '</tr></tbody></table></div>';

    return $html;
}

// ─── Single event: details box above the content ─────────────────────────────

function tyb_eventcalendar_prepend_details($content) {
    if (!is_singular(TYB_EVENT_CALENDAR_POST_TYPE) || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    $meta = tyb_eventcalendar_get_event_meta($post_id);

    $html = '<div class="tyb-ec tyb-ec-details' . ($meta['cancelled'] ? ' is-cancelled' : '') . '">';
    if ($meta['cancelled']) {
        $html .= '<p class="tyb-ec-notice">' . esc_html__('This event has been cancelled.', 'tyb-event-calendar') . '</p>';
    }
    $html .= '<dl>';
    $when = tyb_eventcalendar_when_text($meta);
    if ($when !== '') {
        $html .= '<dt>' . esc_html__('When', 'tyb-event-calendar') . '</dt><dd>' . esc_html($when) . '</dd>';
    }
    $where = tyb_eventcalendar_where_html($meta);
    if ($where !== '') {
        $html .= '<dt>' . esc_html__('Where', 'tyb-event-calendar') . '</dt><dd>' . $where . '</dd>';
    }
    $badges = tyb_eventcalendar_category_badges($post_id);
    if ($badges !== '') {
        $html .= '<dt>' . esc_html__('Categories', 'tyb-event-calendar') . '</dt><dd>' . $badges . '</dd>';
    }
    $html .= '</dl>';
    if ($meta['url'] !== '' && !$meta['cancelled']) {
        $html .= '<p class="tyb-ec-cta"><a class="tyb-ec-button" href="' . esc_url($meta['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('More information / tickets', 'tyb-event-calendar') . '</a></p>';
    }
    $html .= '</div>';

    return $html . $content;
}

add_filter('the_content', 'tyb_eventcalendar_prepend_details');

// ─── Structured data (schema.org Event) ──────────────────────────────────────

function tyb_eventcalendar_json_ld() {
    if (!is_singular(TYB_EVENT_CALENDAR_POST_TYPE)) {
        return;
    }

    $post_id = get_queried_object_id();
    $meta = tyb_eventcalendar_get_event_meta($post_id);
    if ($meta['start'] === '') {
        return;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'startDate' => tyb_eventcalendar_iso8601($meta['start'], $meta['timezone']),
        'eventStatus' => $meta['cancelled'] ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $meta['is_online'] ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
    ];
    if ($meta['end'] !== '') {
        $data['endDate'] = tyb_eventcalendar_iso8601($meta['end'], $meta['timezone']);
    }
    if (has_excerpt($post_id)) {
        $data['description'] = get_the_excerpt($post_id);
    }
    if (has_post_thumbnail($post_id)) {
        $data['image'] = get_the_post_thumbnail_url($post_id, 'large');
    }
    if ($meta['is_online']) {
        $data['location'] = ['@type' => 'VirtualLocation', 'url' => $meta['location_url'] !== '' ? $meta['location_url'] : get_permalink($post_id)];
    } elseif ($meta['location'] !== '') {
        $data['location'] = ['@type' => 'Place', 'name' => $meta['location'], 'address' => $meta['location']];
    }
    if ($meta['url'] !== '') {
        $data['offers'] = ['@type' => 'Offer', 'url' => $meta['url']];
    }
    $data['organizer'] = ['@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/')];

    $data = apply_filters('tyb_eventcalendar_json_ld', $data, $post_id, $meta);

    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

add_action('wp_head', 'tyb_eventcalendar_json_ld');
