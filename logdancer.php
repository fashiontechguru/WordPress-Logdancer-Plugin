<?php
/**
 * Plugin Name: Log Dancer
 * Plugin URI: https://github.com/fashiontechguru/logdancer
 * Description: Observes plugin activations and logs abnormal behavior to the uploads/logdancer directory.
 * Version: 1.0.0
 * Author: FashionTechGuru
 * Author URI: https://github.com/fashiontechguru
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: logdancer
 * Requires at least: 5.2
 * Requires PHP: 7.4
 *
 * @package LogDancer
 */

defined('ABSPATH') or exit('No direct script access allowed.');

/**
 * Checks for PHP version and other prerequisites before activation.
 */
function logdancer_activation_check() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires a minimum PHP version of 7.4');
    }
}
register_activation_hook(__FILE__, 'logdancer_activation_check');

/**
 * Initializes the plugin's functionality by setting up error handlers and other hooks.
 */
function logdancer_init() {
    if (get_option('logdancer_enable_global_error_handler', false)) {
        set_error_handler('logdancer_error_handler');
    }

    add_action('init', 'logdancer_protect_log_directory');
}
add_action('plugins_loaded', 'logdancer_init');

/**
 * Custom error handler function to log PHP errors, including a detailed error types array.
 *
 * @param  int    $severity The error level.
 * @param  string $message  The error message.
 * @param  string $file     The filename where the error was raised.
 * @param  int    $line     The line number where the error was raised.
 * @return bool Whether to continue with the standard error handler.
 */
function logdancer_error_handler($severity, $message, $file, $line) {
    $error_types = [
        E_ERROR             => 'ERROR',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'PARSING ERROR',
        E_NOTICE            => 'NOTICE',
        E_CORE_ERROR        => 'CORE ERROR',
        E_CORE_WARNING      => 'CORE WARNING',
        E_COMPILE_ERROR     => 'COMPILE ERROR',
        E_COMPILE_WARNING   => 'COMPILE WARNING',
        E_USER_ERROR        => 'USER ERROR',
        E_USER_WARNING      => 'USER WARNING',
        E_USER_NOTICE       => 'USER NOTICE',
        E_STRICT            => 'STRICT NOTICE',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED        => 'DEPRECATED',
        E_USER_DEPRECATED   => 'USER DEPRECATED',
    ];

    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting.
        return false;
    }

    $log_entry = sprintf(
        "[%s] Type: %s - Message: %s in %s on line %d\n",
        date('Y-m-d H:i:s'),
        $error_types[$severity] ?? 'UNKNOWN ERROR TYPE',
        $message,
        $file,
        $line
    );

    logdancer_log_event($log_entry);

    // Do not execute PHP internal error handler.
    return true;
}

/**
 * Logs events to the log file.
 *
 * @param string $log_entry The log entry to add.
 */
function logdancer_log_event($log_entry) {
    $log_file = wp_upload_dir()['basedir'] . '/logdancer/php_errors.log';
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Ensures the log directory is protected from direct access.
 */
function logdancer_protect_log_directory() {
    $upload_dir = wp_upload_dir();
    $logdancer_dir = $upload_dir['basedir'] . '/logdancer';
    if (!file_exists($logdancer_dir)) {
        wp_mkdir_p($logdancer_dir);
    }

    $htaccess_content = "Order deny,allow\nDeny from all\n";
    $htaccess_file = $logdancer_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        file_put_contents($htaccess_file, $htaccess_content);
    }
}

/**
 * Utility function to add an option to enable or disable the global error handler on plugin activation.
 */
function logdancer_activate() {
    add_option('logdancer_enable_global_error_handler', true);
}
register_activation_hook(__FILE__, 'logdancer_activate');

/**
 * Cleanup function to remove the plugin's options and settings upon deactivation.
 */
function logdancer_deactivate() {
    delete_option('logdancer_enable_global_error_handler');
}
register_deactivation_hook(__FILE__, 'logdancer_deactivate');