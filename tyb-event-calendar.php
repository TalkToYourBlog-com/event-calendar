<?php
/**
 * Plugin Name:       TalkToYourBlog Event Calendar
 * Plugin URI:        https://michalfutera.pro/my-projects/tyb-event-calendar/
 * Description:       Events with dates, places and coloured categories. A list or month calendar via shortcode, and a page for every event with structured data for search engines.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Michal Futera
 * Author URI:        https://michalfutera.pro/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tyb-event-calendar
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

define('TYB_EVENT_CALENDAR_VERSION', '1.3.0');
define('TYB_EVENT_CALENDAR_PLUGIN_FILE', __FILE__);
define('TYB_EVENT_CALENDAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TYB_EVENT_CALENDAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TYB_EVENT_CALENDAR_POST_TYPE', 'tyb_event');
define('TYB_EVENT_CALENDAR_TAXONOMY', 'tyb_event_cat');
define('TYB_EVENT_CALENDAR_DEFAULT_CATEGORY_COLOR', '#2271b1');

// The debug log lives in the uploads directory, not the plugin folder, which WordPress replaces
// on every update.
$tyb_eventcalendar_uploads = wp_upload_dir(null, false);
define('TYB_EVENT_CALENDAR_VAR_DIR', trailingslashit($tyb_eventcalendar_uploads['basedir']) . 'tyb-event-calendar');
unset($tyb_eventcalendar_uploads);

require_once TYB_EVENT_CALENDAR_PLUGIN_DIR . 'includes/logging.php';
require_once TYB_EVENT_CALENDAR_PLUGIN_DIR . 'includes/library.php';
require_once TYB_EVENT_CALENDAR_PLUGIN_DIR . 'includes/post-types.php';
require_once TYB_EVENT_CALENDAR_PLUGIN_DIR . 'includes/frontend.php';

if (is_admin()) {
    require_once TYB_EVENT_CALENDAR_PLUGIN_DIR . 'includes/admin.php';
    require_once TYB_EVENT_CALENDAR_PLUGIN_DIR . 'includes/admin-log.php';
}

function tyb_eventcalendar_get_option_names() {
    return [
        'plugin_version' => 'tyb_eventcalendar_plugin_version',
        'settings' => 'tyb_eventcalendar_settings',
    ];
}

function tyb_eventcalendar_get_default_settings() {
    return [
        'archive_slug' => 'events',
        'delete_data_on_uninstall' => 0,
    ];
}

function tyb_eventcalendar_get_settings() {
    $names = tyb_eventcalendar_get_option_names();
    $stored = get_option($names['settings'], []);

    return array_merge(tyb_eventcalendar_get_default_settings(), is_array($stored) ? $stored : []);
}

function tyb_eventcalendar_activate() {
    tyb_eventcalendar_register_post_types();
    flush_rewrite_rules();

    $names = tyb_eventcalendar_get_option_names();
    add_option($names['settings'], tyb_eventcalendar_get_default_settings());
    update_option($names['plugin_version'], TYB_EVENT_CALENDAR_VERSION);

    tyb_eventcalendar_log_info('Plugin activated.', ['version' => TYB_EVENT_CALENDAR_VERSION]);
}

function tyb_eventcalendar_deactivate() {
    flush_rewrite_rules();
    tyb_eventcalendar_log_info('Plugin deactivated.');
}

// Rewrite rules depend on the archive slug, so a version change re-flushes them
// (permalinks would otherwise 404 until the user saves Settings > Permalinks).
function tyb_eventcalendar_maybe_upgrade() {
    $names = tyb_eventcalendar_get_option_names();
    if (get_option($names['plugin_version'], '0') === TYB_EVENT_CALENDAR_VERSION) {
        return;
    }

    flush_rewrite_rules();
    update_option($names['plugin_version'], TYB_EVENT_CALENDAR_VERSION);
}

register_activation_hook(__FILE__, 'tyb_eventcalendar_activate');
register_deactivation_hook(__FILE__, 'tyb_eventcalendar_deactivate');
add_action('init', 'tyb_eventcalendar_maybe_upgrade', 20);
