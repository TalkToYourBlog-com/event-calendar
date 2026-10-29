<?php

defined('ABSPATH') || exit;

// ─── Assets ──────────────────────────────────────────────────────────────────

function tyb_eventcalendar_admin_enqueue_assets($hook_suffix) {
    $screen = get_current_screen();
    $is_plugin_screen = $screen && (
        $screen->post_type === TYB_EVENT_CALENDAR_POST_TYPE
        || $screen->taxonomy === TYB_EVENT_CALENDAR_TAXONOMY
        || strpos((string) $hook_suffix, 'tyb-event-calendar') !== false
    );

    if (!$is_plugin_screen) {
        return;
    }

    wp_enqueue_style(
        'tyb-event-calendar-admin',
        TYB_EVENT_CALENDAR_PLUGIN_URL . 'assets/admin/admin.css',
        [],
        TYB_EVENT_CALENDAR_VERSION
    );
    wp_enqueue_script(
        'tyb-event-calendar-admin',
        TYB_EVENT_CALENDAR_PLUGIN_URL . 'assets/admin/admin.js',
        [],
        TYB_EVENT_CALENDAR_VERSION,
        true
    );

    tyb_eventcalendar_enqueue_log_assets();
}

add_action('admin_enqueue_scripts', 'tyb_eventcalendar_admin_enqueue_assets');

// ─── Event details meta box ──────────────────────────────────────────────────

function tyb_eventcalendar_add_meta_boxes() {
    add_meta_box(
        'tyb_event_details',
        __('Event details', 'tyb-event-calendar'),
        'tyb_eventcalendar_render_details_meta_box',
        TYB_EVENT_CALENDAR_POST_TYPE,
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'tyb_eventcalendar_add_meta_boxes');

function tyb_eventcalendar_render_details_meta_box($post) {
    $meta = tyb_eventcalendar_get_event_meta($post->ID);
    wp_nonce_field('tyb_event_details', 'tyb_event_details_nonce');
    ?>
    <table class="form-table tyb-event-details" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="tyb_event_start"><?php esc_html_e('Start', 'tyb-event-calendar'); ?></label></th>
                <td><input type="datetime-local" id="tyb_event_start" name="tyb_event[start]" value="<?php echo esc_attr(tyb_eventcalendar_datetime_input_value($meta['start'])); ?>" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="tyb_event_end"><?php esc_html_e('End', 'tyb-event-calendar'); ?></label></th>
                <td><input type="datetime-local" id="tyb_event_end" name="tyb_event[end]" value="<?php echo esc_attr(tyb_eventcalendar_datetime_input_value($meta['end'])); ?>" /> <span class="description"><?php esc_html_e('Optional.', 'tyb-event-calendar'); ?></span></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('All day', 'tyb-event-calendar'); ?></th>
                <td><label><input type="checkbox" name="tyb_event[all_day]" value="1" <?php checked($meta['all_day']); ?> /> <?php esc_html_e('All-day event (times are ignored)', 'tyb-event-calendar'); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><label for="tyb_event_timezone"><?php esc_html_e('Time zone', 'tyb-event-calendar'); ?></label></th>
                <td>
                    <input type="text" class="regular-text code" id="tyb_event_timezone" name="tyb_event[timezone]" list="tyb_event_timezones" value="<?php echo esc_attr($meta['timezone']); ?>" placeholder="<?php echo esc_attr(wp_timezone_string()); ?>" />
                    <datalist id="tyb_event_timezones">
                        <?php foreach (timezone_identifiers_list() as $tz) : ?>
                            <option value="<?php echo esc_attr($tz); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: site time zone */
                            esc_html__('Leave empty to use the site time zone (%s). Set it for online events aimed at another country.', 'tyb-event-calendar'),
                            esc_html(wp_timezone_string())
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tyb_event_location"><?php esc_html_e('Location', 'tyb-event-calendar'); ?></label></th>
                <td><input type="text" class="regular-text" id="tyb_event_location" name="tyb_event[location]" value="<?php echo esc_attr($meta['location']); ?>" placeholder="<?php esc_attr_e('Venue name, street, city', 'tyb-event-calendar'); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="tyb_event_location_url"><?php esc_html_e('Location link', 'tyb-event-calendar'); ?></label></th>
                <td><input type="url" class="regular-text" id="tyb_event_location_url" name="tyb_event[location_url]" value="<?php echo esc_attr($meta['location_url']); ?>" placeholder="https://" /> <span class="description"><?php esc_html_e('Map link, or the meeting link for online events.', 'tyb-event-calendar'); ?></span></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Online', 'tyb-event-calendar'); ?></th>
                <td><label><input type="checkbox" name="tyb_event[is_online]" value="1" <?php checked($meta['is_online']); ?> /> <?php esc_html_e('This event takes place online', 'tyb-event-calendar'); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><label for="tyb_event_url"><?php esc_html_e('External link', 'tyb-event-calendar'); ?></label></th>
                <td><input type="url" class="regular-text" id="tyb_event_url" name="tyb_event[url]" value="<?php echo esc_attr($meta['url']); ?>" placeholder="https://" /> <span class="description"><?php esc_html_e("Tickets, registration or the event's own page.", 'tyb-event-calendar'); ?></span></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Flags', 'tyb-event-calendar'); ?></th>
                <td>
                    <label><input type="checkbox" name="tyb_event[featured]" value="1" <?php checked($meta['featured']); ?> /> <?php esc_html_e('Featured', 'tyb-event-calendar'); ?></label><br />
                    <label><input type="checkbox" name="tyb_event[cancelled]" value="1" <?php checked($meta['cancelled']); ?> /> <?php esc_html_e('Cancelled (stays visible with a notice)', 'tyb-event-calendar'); ?></label>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

function tyb_eventcalendar_save_event_details($post_id, $post) {
    if (!isset($_POST['tyb_event_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tyb_event_details_nonce'])), 'tyb_event_details')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post->post_type !== TYB_EVENT_CALENDAR_POST_TYPE || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $input = isset($_POST['tyb_event']) && is_array($_POST['tyb_event']) ? wp_unslash($_POST['tyb_event']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field below
    $keys = tyb_eventcalendar_get_meta_keys();

    $start = tyb_eventcalendar_normalize_datetime($input['start'] ?? '');
    $end = tyb_eventcalendar_normalize_datetime($input['end'] ?? '');
    if ($end !== '' && $start !== '' && $end < $start) {
        $end = '';
    }

    $values = [
        'start' => $start,
        'end' => $end,
        'all_day' => !empty($input['all_day']) ? 1 : 0,
        'timezone' => tyb_eventcalendar_sanitize_timezone($input['timezone'] ?? ''),
        'location' => sanitize_text_field($input['location'] ?? ''),
        'location_url' => esc_url_raw((string) ($input['location_url'] ?? '')),
        'is_online' => !empty($input['is_online']) ? 1 : 0,
        'url' => esc_url_raw((string) ($input['url'] ?? '')),
        'featured' => !empty($input['featured']) ? 1 : 0,
        'cancelled' => !empty($input['cancelled']) ? 1 : 0,
    ];

    foreach ($values as $field => $value) {
        if ($value === '' || $value === 0) {
            delete_post_meta($post_id, $keys[$field]);
        } elseif (update_post_meta($post_id, $keys[$field], $value) === false) {
            tyb_eventcalendar_log_warning('Event meta save failed.', [
                'post_id' => $post_id,
                'field' => $field,
            ]);
        }
    }

    tyb_eventcalendar_log_info('Event details saved.', ['post_id' => $post_id]);
}

add_action('save_post', 'tyb_eventcalendar_save_event_details', 10, 2);

// An event without a start date cannot be listed; keep it as a draft.
function tyb_eventcalendar_require_start_date($data, $postarr) {
    if ($data['post_type'] !== TYB_EVENT_CALENDAR_POST_TYPE || $data['post_status'] !== 'publish') {
        return $data;
    }

    $start = isset($_POST['tyb_event']['start']) ? tyb_eventcalendar_normalize_datetime(sanitize_text_field(wp_unslash($_POST['tyb_event']['start']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- nonce checked in save handler; this only guards the status
    if ($start === '' && isset($_POST['tyb_event'])) {
        $data['post_status'] = 'draft';
        add_filter('redirect_post_location', 'tyb_eventcalendar_redirect_missing_start');
    }

    return $data;
}

add_filter('wp_insert_post_data', 'tyb_eventcalendar_require_start_date', 10, 2);

function tyb_eventcalendar_redirect_missing_start($location) {
    return add_query_arg('tyb_missing_start', '1', $location);
}

function tyb_eventcalendar_admin_notices() {
    if (empty($_GET['tyb_missing_start'])) { // phpcs:ignore WordPress.Security.NonceVerification -- display only
        return;
    }
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('The event was saved as a draft because it has no start date. Add a start date to publish it.', 'tyb-event-calendar') . '</p></div>';
}

add_action('admin_notices', 'tyb_eventcalendar_admin_notices');

// ─── Event list columns ──────────────────────────────────────────────────────

function tyb_eventcalendar_event_columns($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['tyb_when'] = __('When', 'tyb-event-calendar');
            $new['tyb_where'] = __('Where', 'tyb-event-calendar');
            $new['tyb_categories'] = __('Categories', 'tyb-event-calendar');
        }
    }
    unset($new['date']);
    $new['date'] = $columns['date'] ?? __('Date', 'tyb-event-calendar');

    return $new;
}

add_filter('manage_' . TYB_EVENT_CALENDAR_POST_TYPE . '_posts_columns', 'tyb_eventcalendar_event_columns');

function tyb_eventcalendar_render_event_column($column, $post_id) {
    $meta = tyb_eventcalendar_get_event_meta($post_id);

    switch ($column) {
        case 'tyb_when':
            $when = tyb_eventcalendar_format_datetime($meta['start'], $meta['all_day'], $meta['timezone']);
            if ($meta['end'] !== '') {
                $when .= ' – ' . tyb_eventcalendar_format_datetime($meta['end'], $meta['all_day'], $meta['timezone']);
            }
            echo esc_html($when !== '' ? $when : '—');
            if ($meta['cancelled']) {
                echo ' <span class="tyb-badge tyb-badge-cancelled">' . esc_html__('Cancelled', 'tyb-event-calendar') . '</span>';
            }
            if ($meta['featured']) {
                echo ' <span class="dashicons dashicons-star-filled tyb-featured-star" title="' . esc_attr__('Featured', 'tyb-event-calendar') . '"></span>';
            }
            break;

        case 'tyb_where':
            echo esc_html($meta['is_online'] ? __('Online', 'tyb-event-calendar') : ($meta['location'] !== '' ? $meta['location'] : '—'));
            break;

        case 'tyb_categories':
            $terms = get_the_terms($post_id, TYB_EVENT_CALENDAR_TAXONOMY);
            if (!is_array($terms) || empty($terms)) {
                echo '—';
                break;
            }
            foreach ($terms as $term) {
                $color = tyb_eventcalendar_get_category_color($term->term_id);
                echo '<span class="tyb-badge" style="background:' . esc_attr($color) . ';color:' . esc_attr(tyb_eventcalendar_contrast_color($color)) . '">' . esc_html($term->name) . '</span> ';
            }
            break;
    }
}

add_action('manage_' . TYB_EVENT_CALENDAR_POST_TYPE . '_posts_custom_column', 'tyb_eventcalendar_render_event_column', 10, 2);

function tyb_eventcalendar_sortable_columns($columns) {
    $columns['tyb_when'] = 'tyb_when';
    return $columns;
}

add_filter('manage_edit-' . TYB_EVENT_CALENDAR_POST_TYPE . '_sortable_columns', 'tyb_eventcalendar_sortable_columns');

function tyb_eventcalendar_sort_admin_list($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== TYB_EVENT_CALENDAR_POST_TYPE) {
        return;
    }

    $orderby = $query->get('orderby');
    if ($orderby === 'tyb_when' || $orderby === '') {
        $keys = tyb_eventcalendar_get_meta_keys();
        $query->set('meta_key', $keys['start']);
        $query->set('orderby', 'meta_value');
        if ($orderby === '') {
            $query->set('order', 'DESC');
        }
    }
}

add_action('pre_get_posts', 'tyb_eventcalendar_sort_admin_list');

// ─── Category colour (term meta) ─────────────────────────────────────────────

function tyb_eventcalendar_add_category_color_field() {
    ?>
    <div class="form-field">
        <label for="tyb_event_color"><?php esc_html_e('Colour', 'tyb-event-calendar'); ?></label>
        <input type="color" id="tyb_event_color" name="tyb_event_color" value="<?php echo esc_attr(TYB_EVENT_CALENDAR_DEFAULT_CATEGORY_COLOR); ?>" />
        <p><?php esc_html_e('Used for this category in the calendar and on event pages.', 'tyb-event-calendar'); ?></p>
    </div>
    <?php
}

add_action(TYB_EVENT_CALENDAR_TAXONOMY . '_add_form_fields', 'tyb_eventcalendar_add_category_color_field');

function tyb_eventcalendar_edit_category_color_field($term) {
    ?>
    <tr class="form-field">
        <th scope="row"><label for="tyb_event_color"><?php esc_html_e('Colour', 'tyb-event-calendar'); ?></label></th>
        <td>
            <input type="color" id="tyb_event_color" name="tyb_event_color" value="<?php echo esc_attr(tyb_eventcalendar_get_category_color($term->term_id)); ?>" />
            <p class="description"><?php esc_html_e('Used for this category in the calendar and on event pages.', 'tyb-event-calendar'); ?></p>
        </td>
    </tr>
    <?php
}

add_action(TYB_EVENT_CALENDAR_TAXONOMY . '_edit_form_fields', 'tyb_eventcalendar_edit_category_color_field');

function tyb_eventcalendar_save_category_color($term_id) {
    if (!isset($_POST['tyb_event_color'])) { // phpcs:ignore WordPress.Security.NonceVerification -- WordPress verified the term form nonce before firing this hook
        return;
    }
    if (!current_user_can('manage_categories')) {
        return;
    }

    $result = update_term_meta($term_id, 'tyb_event_color', tyb_eventcalendar_normalize_color(sanitize_text_field(wp_unslash($_POST['tyb_event_color'])))); // phpcs:ignore WordPress.Security.NonceVerification

    if ($result === false) {
        tyb_eventcalendar_log_warning('Category colour save failed.', ['term_id' => $term_id]);
    } else {
        tyb_eventcalendar_log_info('Category colour saved.', ['term_id' => $term_id]);
    }
}

add_action('created_' . TYB_EVENT_CALENDAR_TAXONOMY, 'tyb_eventcalendar_save_category_color');
add_action('edited_' . TYB_EVENT_CALENDAR_TAXONOMY, 'tyb_eventcalendar_save_category_color');

function tyb_eventcalendar_category_columns($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'name') {
            $new['tyb_color'] = __('Colour', 'tyb-event-calendar');
        }
    }
    return $new;
}

add_filter('manage_edit-' . TYB_EVENT_CALENDAR_TAXONOMY . '_columns', 'tyb_eventcalendar_category_columns');

function tyb_eventcalendar_render_category_column($content, $column, $term_id) {
    if ($column !== 'tyb_color') {
        return $content;
    }
    $color = tyb_eventcalendar_get_category_color($term_id);
    return '<span class="tyb-color-dot" style="background:' . esc_attr($color) . '"></span><code>' . esc_html($color) . '</code>';
}

add_filter('manage_' . TYB_EVENT_CALENDAR_TAXONOMY . '_custom_column', 'tyb_eventcalendar_render_category_column', 10, 3);

// ─── Settings and About pages ────────────────────────────────────────────────

function tyb_eventcalendar_register_admin_menu() {
    $parent = 'edit.php?post_type=' . TYB_EVENT_CALENDAR_POST_TYPE;

    add_submenu_page($parent, __('Event Calendar Settings', 'tyb-event-calendar'), __('Settings', 'tyb-event-calendar'), 'manage_options', 'tyb-event-calendar-settings', 'tyb_eventcalendar_render_settings_page');
    add_submenu_page($parent, __('About TalkToYourBlog Event Calendar', 'tyb-event-calendar'), __('About', 'tyb-event-calendar'), 'manage_options', 'tyb-event-calendar-about', 'tyb_eventcalendar_render_about_page');
    add_submenu_page($parent, __('Error log', 'tyb-event-calendar'), __('Error log', 'tyb-event-calendar'), 'manage_options', 'tyb-event-calendar-error-log', 'tyb_eventcalendar_render_log_page');
}

add_action('admin_menu', 'tyb_eventcalendar_register_admin_menu');

function tyb_eventcalendar_log_option_update($option, $old_value, $value) {
    if (strpos((string) $option, 'tyb_eventcalendar_') !== 0) {
        return;
    }

    tyb_eventcalendar_log_info('Option updated.', [
        'option' => (string) $option,
        'value_changed' => $old_value !== $value,
        'user_id' => get_current_user_id(),
    ]);
}

add_action('updated_option', 'tyb_eventcalendar_log_option_update', 10, 3);

function tyb_eventcalendar_register_settings() {
    $names = tyb_eventcalendar_get_option_names();
    register_setting('tyb_eventcalendar_settings', $names['settings'], [
        'type' => 'array',
        'sanitize_callback' => 'tyb_eventcalendar_sanitize_settings',
        'default' => tyb_eventcalendar_get_default_settings(),
    ]);
}

add_action('admin_init', 'tyb_eventcalendar_register_settings');

function tyb_eventcalendar_sanitize_settings($input) {
    $current = tyb_eventcalendar_get_settings();
    $input = is_array($input) ? $input : [];

    $slug = sanitize_title((string) ($input['archive_slug'] ?? ''));
    $clean = [
        'archive_slug' => $slug !== '' ? $slug : 'events',
        'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']) ? 1 : 0,
    ];

    // The archive slug is part of the rewrite rules.
    if ($clean['archive_slug'] !== $current['archive_slug']) {
        add_action('shutdown', 'flush_rewrite_rules');
    }

    return $clean;
}

function tyb_eventcalendar_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $settings = tyb_eventcalendar_get_settings();
    $names = tyb_eventcalendar_get_option_names();
    ?>
    <div class="wrap tyb-admin-wrap">
        <h1><?php esc_html_e('Event Calendar Settings', 'tyb-event-calendar'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('tyb_eventcalendar_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tyb_archive_slug"><?php esc_html_e('URL base', 'tyb-event-calendar'); ?></label></th>
                        <td>
                            <code><?php echo esc_html(home_url('/')); ?></code>
                            <input type="text" class="regular-text code" id="tyb_archive_slug" name="<?php echo esc_attr($names['settings']); ?>[archive_slug]" value="<?php echo esc_attr($settings['archive_slug']); ?>" style="width: 160px;" />
                            <code>/</code>
                            <p class="description"><?php esc_html_e('The event list lives at this address and every event below it, e.g. /events/summer-workshop/. Categories at /events/category/name/.', 'tyb-event-calendar'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Shortcode', 'tyb-event-calendar'); ?></th>
                        <td>
                            <code>[tyb_event_calendar]</code>
                            <p class="description"><?php esc_html_e('Shows upcoming events on any page or post. Attributes: view="list" or "month", category="slug", limit="10", upcoming="yes", featured="yes".', 'tyb-event-calendar'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Uninstall', 'tyb-event-calendar'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($names['settings']); ?>[delete_data_on_uninstall]" value="1" <?php checked((int) $settings['delete_data_on_uninstall'], 1); ?> /> <?php esc_html_e('Delete all events, categories and settings when the plugin is deleted', 'tyb-event-calendar'); ?></label>
                            <p class="description"><?php esc_html_e('Deactivating never removes data. Deleting the plugin removes it only with this box ticked.', 'tyb-event-calendar'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function tyb_eventcalendar_get_related_projects() {
    return [
        [
            'name' => 'Anonymous Sales Plugin',
            'url' => 'https://michalfutera.pro/my-projects/anonymoussalesplugin-wordpress/',
            'description' => __('Sell digital files and access links from WordPress without collecting any customer data. Buyers pay in cryptocurrency through NOWPayments and receive their download as a PDF; no accounts, no e-mail addresses, no personal data stored.', 'tyb-event-calendar'),
        ],
        [
            'name' => 'TalkToYourBlog',
            'url' => 'https://talktoyourblog.com/',
            'description' => __('Connect your WordPress blog to AI assistants such as Claude, ChatGPT or Grok through the Model Context Protocol. Ask the assistant to write drafts, update posts, manage comments, categories and media; your login and application password are never stored on the server.', 'tyb-event-calendar'),
        ],
        [
            'name' => __('Report a bug or idea', 'tyb-event-calendar'),
            'url' => 'https://issues.michalfutera.com/?project=talktoyourblog-event-calendar',
            'description' => __('Found something wrong, or have an idea for this calendar? Report it here -- it goes straight to the author, with a status page to follow up.', 'tyb-event-calendar'),
        ],
    ];
}

function tyb_eventcalendar_render_about_page() {
    ?>
    <div class="wrap tyb-admin-wrap">
        <img class="tyb-admin-logo" src="<?php echo esc_url(TYB_EVENT_CALENDAR_PLUGIN_URL . 'assets/admin/img/logo.svg'); ?>" alt="" width="56" height="56" />
        <h1><?php esc_html_e('About TalkToYourBlog Event Calendar', 'tyb-event-calendar'); ?></h1>
        <div class="card tyb-admin-card">
            <h2><?php esc_html_e('TalkToYourBlog Event Calendar', 'tyb-event-calendar'); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: %s: version number */
                    esc_html__('Version %s', 'tyb-event-calendar'),
                    '<strong>' . esc_html(TYB_EVENT_CALENDAR_VERSION) . '</strong>'
                );
                ?>
            </p>
            <p>
                <?php
                printf(
                    /* translators: %s: link to the author's site */
                    esc_html__('Event calendar for WordPress by %s.', 'tyb-event-calendar'),
                    '<a href="https://michalfutera.pro/" target="_blank" rel="noopener noreferrer">Michal Futera</a>'
                );
                ?>
            </p>
        </div>
        <div class="card tyb-admin-card">
            <h2><?php esc_html_e('More from MichalFutera.pro', 'tyb-event-calendar'); ?></h2>
            <ul class="tyb-project-list">
                <?php foreach (tyb_eventcalendar_get_related_projects() as $project) : ?>
                    <li>
                        <a href="<?php echo esc_url($project['url']); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo esc_html($project['name']); ?></strong></a><br />
                        <?php echo esc_html($project['description']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
}
