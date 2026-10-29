<?php

defined('ABSPATH') || exit;

function talktoyourblog_eventcalendar_register_frontend_assets() {
    wp_register_style(
        'talktoyourblog-event-calendar',
        TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_URL . 'assets/frontend/calendar.css',
        [],
        TALKTOYOURBLOG_EVENT_CALENDAR_VERSION
    );
}

add_action('init', 'talktoyourblog_eventcalendar_register_frontend_assets');

function talktoyourblog_eventcalendar_enqueue_on_event_pages() {
    if (is_singular(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE) || is_post_type_archive(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE) || is_tax(TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY)) {
        wp_enqueue_style('talktoyourblog-event-calendar');
    }
}

add_action('wp_enqueue_scripts', 'talktoyourblog_eventcalendar_enqueue_on_event_pages');

// ─── Shared pieces ───────────────────────────────────────────────────────────

function talktoyourblog_eventcalendar_category_badges($post_id, $link = true) {
    $terms = get_the_terms($post_id, TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY);
    if (!is_array($terms) || empty($terms)) {
        return '';
    }

    $html = '<span class="talktoyourblog-ec-badges">';
    foreach ($terms as $term) {
        $color = talktoyourblog_eventcalendar_get_category_color($term->term_id);
        $style = 'background:' . esc_attr($color) . ';color:' . esc_attr(talktoyourblog_eventcalendar_contrast_color($color));
        $term_link = $link ? get_term_link($term) : '';
        if ($link && !is_wp_error($term_link)) {
            $html .= '<a class="talktoyourblog-ec-badge" style="' . $style . '" href="' . esc_url($term_link) . '">' . esc_html($term->name) . '</a>';
        } else {
            $html .= '<span class="talktoyourblog-ec-badge" style="' . $style . '">' . esc_html($term->name) . '</span>';
        }
    }
    return $html . '</span>';
}

function talktoyourblog_eventcalendar_when_text(array $meta) {
    $start = talktoyourblog_eventcalendar_format_datetime($meta['start'], $meta['all_day'], $meta['timezone']);
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
        return sprintf(_x('%1$s – %2$s', 'event time range on one day', 'talktoyourblog-event-calendar'), $start, $end_time);
    }
    if ($same_day) {
        return $start;
    }

    /* translators: 1: start, 2: end */
    return sprintf(_x('%1$s – %2$s', 'event date range', 'talktoyourblog-event-calendar'), $start, talktoyourblog_eventcalendar_format_datetime($meta['end'], $meta['all_day'], $meta['timezone']));
}

function talktoyourblog_eventcalendar_where_html(array $meta) {
    $label = $meta['is_online'] ? __('Online', 'talktoyourblog-event-calendar') : $meta['location'];
    if ($label === '') {
        return '';
    }

    if ($meta['location_url'] !== '') {
        return '<a href="' . esc_url($meta['location_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
    }

    return esc_html($label);
}

// ─── Shortcode: [talktoyourblog_event_calendar] ───────────────────────────────────────

function talktoyourblog_eventcalendar_shortcode($atts = []) {
    $atts = shortcode_atts([
        'view' => 'list',
        'category' => '',
        'limit' => 10,
        'upcoming' => 'yes',
        'featured' => 'no',
        'month' => '',
    ], $atts, 'talktoyourblog_event_calendar');

    wp_enqueue_style('talktoyourblog-event-calendar');

    $truthy = static function ($value) {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    };

    if (strtolower(trim((string) $atts['view'])) === 'month') {
        return talktoyourblog_eventcalendar_render_month($atts, $truthy);
    }

    $query = talktoyourblog_eventcalendar_query_events([
        'upcoming' => $truthy($atts['upcoming']),
        'category' => (string) $atts['category'],
        'limit' => (int) $atts['limit'] === 0 ? 10 : (int) $atts['limit'],
        'featured' => $truthy($atts['featured']),
    ]);

    if (!$query->have_posts()) {
        return '<div class="talktoyourblog-ec talktoyourblog-ec-list"><p class="talktoyourblog-ec-empty">' . esc_html__('No upcoming events.', 'talktoyourblog-event-calendar') . '</p></div>';
    }

    $html = '<div class="talktoyourblog-ec talktoyourblog-ec-list">';
    $current_month = '';
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $meta = talktoyourblog_eventcalendar_get_event_meta($post_id);

        $month = date_i18n('F Y', strtotime($meta['start'] . ' UTC'), true);
        if ($month !== $current_month) {
            $html .= '<h3 class="talktoyourblog-ec-month">' . esc_html($month) . '</h3>';
            $current_month = $month;
        }

        $classes = 'talktoyourblog-ec-item' . ($meta['cancelled'] ? ' is-cancelled' : '') . ($meta['featured'] ? ' is-featured' : '');
        $html .= '<article class="' . esc_attr($classes) . '">';
        $html .= '<div class="talktoyourblog-ec-date"><span class="talktoyourblog-ec-day">' . esc_html(date_i18n('j', strtotime($meta['start'] . ' UTC'), true)) . '</span><span class="talktoyourblog-ec-mon">' . esc_html(date_i18n('M', strtotime($meta['start'] . ' UTC'), true)) . '</span></div>';
        $html .= '<div class="talktoyourblog-ec-body">';
        $html .= '<h4 class="talktoyourblog-ec-title"><a href="' . esc_url(get_permalink($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
        if ($meta['cancelled']) {
            $html .= ' <span class="talktoyourblog-ec-cancelled">' . esc_html__('Cancelled', 'talktoyourblog-event-calendar') . '</span>';
        }
        $html .= '</h4>';
        $html .= '<p class="talktoyourblog-ec-when">' . esc_html(talktoyourblog_eventcalendar_when_text($meta)) . '</p>';
        $where = talktoyourblog_eventcalendar_where_html($meta);
        if ($where !== '') {
            $html .= '<p class="talktoyourblog-ec-where">' . $where . '</p>';
        }
        if (has_excerpt($post_id)) {
            $html .= '<p class="talktoyourblog-ec-excerpt">' . esc_html(get_the_excerpt($post_id)) . '</p>';
        }
        $html .= talktoyourblog_eventcalendar_category_badges($post_id);
        $html .= '</div></article>';
    }
    wp_reset_postdata();

    return $html . '</div>';
}

add_shortcode('talktoyourblog_event_calendar', 'talktoyourblog_eventcalendar_shortcode');

function talktoyourblog_eventcalendar_render_month(array $atts, callable $truthy) {
    // Month from the URL (prev/next links) wins over the shortcode attribute.
    $requested = isset($_GET['talktoyourblog_month']) ? sanitize_text_field(wp_unslash($_GET['talktoyourblog_month'])) : (string) $atts['month']; // phpcs:ignore WordPress.Security.NonceVerification -- read-only navigation
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested)) {
        $requested = current_time('Y-m');
    }

    $first = $requested . '-01 00:00:00';
    $first_ts = strtotime($first . ' UTC');
    $days_in_month = (int) gmdate('t', $first_ts);
    $last = $requested . '-' . $days_in_month . ' 23:59:59';

    $query = talktoyourblog_eventcalendar_query_events([
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
        $meta = talktoyourblog_eventcalendar_get_event_meta($post_id);
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
    $base_url = remove_query_arg('talktoyourblog_month');

    $html = '<div class="talktoyourblog-ec talktoyourblog-ec-month-view">';
    $html .= '<div class="talktoyourblog-ec-nav">';
    $html .= '<a class="talktoyourblog-ec-nav-prev" href="' . esc_url(add_query_arg('talktoyourblog_month', $prev, $base_url)) . '" aria-label="' . esc_attr__('Previous month', 'talktoyourblog-event-calendar') . '">&lsaquo;</a>';
    $html .= '<h3 class="talktoyourblog-ec-month">' . esc_html(date_i18n('F Y', $first_ts, true)) . '</h3>';
    $html .= '<a class="talktoyourblog-ec-nav-next" href="' . esc_url(add_query_arg('talktoyourblog_month', $next, $base_url)) . '" aria-label="' . esc_attr__('Next month', 'talktoyourblog-event-calendar') . '">&rsaquo;</a>';
    $html .= '</div>';

    $html .= '<table class="talktoyourblog-ec-grid"><thead><tr>';
    for ($i = 0; $i < 7; $i++) {
        $html .= '<th scope="col">' . esc_html($wp_locale->get_weekday_abbrev($wp_locale->get_weekday(($week_starts + $i) % 7))) . '</th>';
    }
    $html .= '</tr></thead><tbody><tr>';

    $cell = 0;
    for ($i = 0; $i < $offset; $i++, $cell++) {
        $html .= '<td class="talktoyourblog-ec-pad"></td>';
    }
    for ($day = 1; $day <= $days_in_month; $day++, $cell++) {
        if ($cell > 0 && $cell % 7 === 0) {
            $html .= '</tr><tr>';
        }
        $date = sprintf('%s-%02d', $requested, $day);
        $classes = 'talktoyourblog-ec-day-cell' . ($date === $today ? ' is-today' : '') . (isset($by_day[$day]) ? ' has-events' : '');
        $html .= '<td class="' . esc_attr($classes) . '"><span class="talktoyourblog-ec-daynum">' . esc_html((string) $day) . '</span>';
        foreach ($by_day[$day] ?? [] as $entry) {
            $terms = get_the_terms($entry['id'], TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY);
            $color = is_array($terms) && !empty($terms) ? talktoyourblog_eventcalendar_get_category_color($terms[0]->term_id) : TALKTOYOURBLOG_EVENT_CALENDAR_DEFAULT_CATEGORY_COLOR;
            $title = get_the_title($entry['id']);
            $time = $entry['meta']['all_day'] ? '' : date_i18n(get_option('time_format'), strtotime($entry['meta']['start'] . ' UTC'), true);
            $html .= '<a class="talktoyourblog-ec-chip' . ($entry['meta']['cancelled'] ? ' is-cancelled' : '') . '" style="--talktoyourblog-ec-color:' . esc_attr($color) . '" href="' . esc_url(get_permalink($entry['id'])) . '" title="' . esc_attr($title) . '">';
            if ($time !== '') {
                $html .= '<span class="talktoyourblog-ec-chip-time">' . esc_html($time) . '</span> ';
            }
            $html .= esc_html($title) . '</a>';
        }
        $html .= '</td>';
    }
    while ($cell % 7 !== 0) {
        $html .= '<td class="talktoyourblog-ec-pad"></td>';
        $cell++;
    }
    $html .= '</tr></tbody></table></div>';

    return $html;
}

// ─── Single event: details box above the content ─────────────────────────────

function talktoyourblog_eventcalendar_prepend_details($content) {
    if (!is_singular(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE) || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $post_id = get_the_ID();
    $meta = talktoyourblog_eventcalendar_get_event_meta($post_id);

    $html = '<div class="talktoyourblog-ec talktoyourblog-ec-details' . ($meta['cancelled'] ? ' is-cancelled' : '') . '">';
    if ($meta['cancelled']) {
        $html .= '<p class="talktoyourblog-ec-notice">' . esc_html__('This event has been cancelled.', 'talktoyourblog-event-calendar') . '</p>';
    }
    $html .= '<dl>';
    $when = talktoyourblog_eventcalendar_when_text($meta);
    if ($when !== '') {
        $html .= '<dt>' . esc_html__('When', 'talktoyourblog-event-calendar') . '</dt><dd>' . esc_html($when) . '</dd>';
    }
    $where = talktoyourblog_eventcalendar_where_html($meta);
    if ($where !== '') {
        $html .= '<dt>' . esc_html__('Where', 'talktoyourblog-event-calendar') . '</dt><dd>' . $where . '</dd>';
    }
    $badges = talktoyourblog_eventcalendar_category_badges($post_id);
    if ($badges !== '') {
        $html .= '<dt>' . esc_html__('Categories', 'talktoyourblog-event-calendar') . '</dt><dd>' . $badges . '</dd>';
    }
    $html .= '</dl>';
    if ($meta['url'] !== '' && !$meta['cancelled']) {
        $html .= '<p class="talktoyourblog-ec-cta"><a class="talktoyourblog-ec-button" href="' . esc_url($meta['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('More information / tickets', 'talktoyourblog-event-calendar') . '</a></p>';
    }
    $html .= '</div>';

    return $html . $content;
}

add_filter('the_content', 'talktoyourblog_eventcalendar_prepend_details');

// ─── Structured data (schema.org Event) ──────────────────────────────────────

function talktoyourblog_eventcalendar_json_ld() {
    if (!is_singular(TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE)) {
        return;
    }

    $post_id = get_queried_object_id();
    $meta = talktoyourblog_eventcalendar_get_event_meta($post_id);
    if ($meta['start'] === '') {
        return;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'startDate' => talktoyourblog_eventcalendar_iso8601($meta['start'], $meta['timezone']),
        'eventStatus' => $meta['cancelled'] ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $meta['is_online'] ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
    ];
    if ($meta['end'] !== '') {
        $data['endDate'] = talktoyourblog_eventcalendar_iso8601($meta['end'], $meta['timezone']);
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

    $data = apply_filters('talktoyourblog_eventcalendar_json_ld', $data, $post_id, $meta);

    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

add_action('wp_head', 'talktoyourblog_eventcalendar_json_ld');
