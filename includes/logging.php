<?php
// Optional debug log, off by default (Events -> Error log). What is logged: which ability
// ran, on what path, and whether it succeeded or failed, with the WP_Error code/message on
// failure -- never file content. The file is capped at 1 MB and starts again when it grows past
// that. Modelled on anonymousSalesPlugin's own error-log library.

defined('ABSPATH') || exit;

function talktoyourblog_eventcalendar_write_log_guard_files($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $htaccess_path = trailingslashit($dir) . '.htaccess';
    if (!file_exists($htaccess_path)) {
        $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        file_put_contents($htaccess_path, $rules);
    }

    $index_path = trailingslashit($dir) . 'index.php';
    if (!file_exists($index_path)) {
        file_put_contents($index_path, "<?php\n// Silence is golden.\n");
    }
}

// The log folder lives in the uploads directory, not the plugin folder, which WordPress
// replaces on every update. Apache/LiteSpeed honour the .htaccess; on nginx add a "deny all"
// for wp-content/uploads/talktoyourblog-event-calendar/ in the server config.
function talktoyourblog_eventcalendar_protect_var_dir() {
    wp_mkdir_p(TALKTOYOURBLOG_EVENT_CALENDAR_VAR_DIR);
    talktoyourblog_eventcalendar_write_log_guard_files(TALKTOYOURBLOG_EVENT_CALENDAR_VAR_DIR);
}

function talktoyourblog_eventcalendar_get_error_log_file_path() {
    $logs_root = TALKTOYOURBLOG_EVENT_CALENDAR_VAR_DIR . '/logs';
    $error_dir = $logs_root . '/error';

    wp_mkdir_p($error_dir);

    talktoyourblog_eventcalendar_protect_var_dir();
    talktoyourblog_eventcalendar_write_log_guard_files($logs_root);
    talktoyourblog_eventcalendar_write_log_guard_files($error_dir);

    return $error_dir . '/error.log';
}

function talktoyourblog_eventcalendar_logging_enabled() {
    return get_option('talktoyourblog_eventcalendar_debug_logging', '0') === '1';
}

function talktoyourblog_eventcalendar_log($level, $message, $context = []) {
    if (!talktoyourblog_eventcalendar_logging_enabled()) {
        return;
    }

    $allowed_levels = ['debug', 'info', 'warning', 'error'];
    $level = strtolower(trim((string) $level));
    if (!in_array($level, $allowed_levels, true)) {
        $level = 'info';
    }

    $log_file = talktoyourblog_eventcalendar_get_error_log_file_path();
    if (file_exists($log_file) && filesize($log_file) > 1048576) {
        file_put_contents($log_file, '[' . gmdate('Y-m-d H:i:s') . " UTC] [INFO] The log was larger than 1 MB and has been reset." . PHP_EOL, LOCK_EX);
    }

    $safe_message = (string) $message;
    $safe_context = '';
    if (is_array($context) && !empty($context)) {
        $context_json = wp_json_encode($context);
        if (is_string($context_json) && $context_json !== '') {
            $safe_context = ' | context=' . $context_json;
        }
    }

    $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] [' . strtoupper($level) . '] ' . $safe_message . $safe_context . PHP_EOL;
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

function talktoyourblog_eventcalendar_log_debug($message, $context = []) {
    talktoyourblog_eventcalendar_log('debug', $message, $context);
}

function talktoyourblog_eventcalendar_log_info($message, $context = []) {
    talktoyourblog_eventcalendar_log('info', $message, $context);
}

function talktoyourblog_eventcalendar_log_warning($message, $context = []) {
    talktoyourblog_eventcalendar_log('warning', $message, $context);
}

function talktoyourblog_eventcalendar_log_error($message, $context = []) {
    talktoyourblog_eventcalendar_log('error', $message, $context);
}
