<?php
/**
 * Plugin Name:       TalkToYourBlog Event Calendar
 * Plugin URI:        https://michalfutera.pro/my-projects/talktoyourblog-event-calendar/
 * Description:       Events with dates, places and coloured categories. A list or month calendar via shortcode, and a page for every event with structured data for search engines.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Michal Futera
 * Author URI:        https://michalfutera.pro/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       talktoyourblog-event-calendar
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

define('TALKTOYOURBLOG_EVENT_CALENDAR_VERSION', '1.2.0');
define('TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_FILE', __FILE__);
define('TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TALKTOYOURBLOG_EVENT_CALENDAR_POST_TYPE', 'talktoyourblog_event');
define('TALKTOYOURBLOG_EVENT_CALENDAR_TAXONOMY', 'talktoyourblog_event_cat');
define('TALKTOYOURBLOG_EVENT_CALENDAR_DEFAULT_CATEGORY_COLOR', '#2271b1');

// The debug log lives in the uploads directory, not the plugin folder, which WordPress replaces
// on every update.
$talktoyourblog_eventcalendar_uploads = wp_upload_dir(null, false);
define('TALKTOYOURBLOG_EVENT_CALENDAR_VAR_DIR', trailingslashit($talktoyourblog_eventcalendar_uploads['basedir']) . 'talktoyourblog-event-calendar');
unset($talktoyourblog_eventcalendar_uploads);

require_once TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR . 'includes/logging.php';
require_once TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR . 'includes/library.php';
require_once TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR . 'includes/post-types.php';
require_once TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR . 'includes/frontend.php';

if (is_admin()) {
    require_once TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR . 'includes/admin.php';
    require_once TALKTOYOURBLOG_EVENT_CALENDAR_PLUGIN_DIR . 'includes/admin-log.php';
}

function talktoyourblog_eventcalendar_get_option_names() {
    return [
        'plugin_version' => 'talktoyourblog_eventcalendar_plugin_version',
        'settings' => 'talktoyourblog_eventcalendar_settings',
    ];
}

function talktoyourblog_eventcalendar_get_default_settings() {
    return [
        'archive_slug' => 'events',
        'delete_data_on_uninstall' => 0,
    ];
}

function talktoyourblog_eventcalendar_get_settings() {
    $names = talktoyourblog_eventcalendar_get_option_names();
    $stored = get_option($names['settings'], []);

    return array_merge(talktoyourblog_eventcalendar_get_default_settings(), is_array($stored) ? $stored : []);
}

function talktoyourblog_eventcalendar_activate() {
    talktoyourblog_eventcalendar_register_post_types();
    flush_rewrite_rules();

    $names = talktoyourblog_eventcalendar_get_option_names();
    add_option($names['settings'], talktoyourblog_eventcalendar_get_default_settings());
    update_option($names['plugin_version'], TALKTOYOURBLOG_EVENT_CALENDAR_VERSION);

    talktoyourblog_eventcalendar_log_info('Plugin activated.', ['version' => TALKTOYOURBLOG_EVENT_CALENDAR_VERSION]);
}

function talktoyourblog_eventcalendar_deactivate() {
    flush_rewrite_rules();
    talktoyourblog_eventcalendar_log_info('Plugin deactivated.');
}

// Rewrite rules depend on the archive slug, so a version change re-flushes them
// (permalinks would otherwise 404 until the user saves Settings > Permalinks).
function talktoyourblog_eventcalendar_maybe_upgrade() {
    $names = talktoyourblog_eventcalendar_get_option_names();
    if (get_option($names['plugin_version'], '0') === TALKTOYOURBLOG_EVENT_CALENDAR_VERSION) {
        return;
    }

    flush_rewrite_rules();
    update_option($names['plugin_version'], TALKTOYOURBLOG_EVENT_CALENDAR_VERSION);
}

register_activation_hook(__FILE__, 'talktoyourblog_eventcalendar_activate');
register_deactivation_hook(__FILE__, 'talktoyourblog_eventcalendar_deactivate');
add_action('init', 'talktoyourblog_eventcalendar_maybe_upgrade', 20);
