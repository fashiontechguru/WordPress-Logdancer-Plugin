<?php
/**
 * Log Dancer — Deactivator.
 *
 * Runs on plugin deactivation. Stops scheduled cron tasks.
 * Does NOT delete settings or data — that is the job of uninstall.php.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Deactivator {

    /**
     * Main deactivation entry point.
     * Called by register_deactivation_hook().
     */
    public static function deactivate(): void {
        self::clear_scheduled_tasks();
    }

    // -----------------------------------------------------------------------
    // Steps
    // -----------------------------------------------------------------------

    /**
     * Remove scheduled WP-Cron events registered by this plugin.
     * Settings and log data are preserved.
     */
    public static function clear_scheduled_tasks(): void {
        wp_clear_scheduled_hook( 'logdancer_retention_cleanup' );
    }
}
