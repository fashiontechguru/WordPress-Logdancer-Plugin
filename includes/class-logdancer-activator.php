<?php
/**
 * Log Dancer — Activator.
 *
 * Runs on plugin activation. Validates requirements, creates default settings,
 * and creates the database table.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Activator {

    /**
     * Main activation entry point.
     * Called by register_activation_hook().
     */
    public static function activate(): void {
        self::check_requirements();
        self::create_default_settings();
        self::prepare_storage();
        self::schedule_cron();

        update_option( LogDancer_Settings::VERSION_KEY, LOGDANCER_VERSION );
    }

    // -----------------------------------------------------------------------
    // Steps
    // -----------------------------------------------------------------------

    /**
     * Check PHP and WordPress requirements. Deactivate if not met.
     */
    public static function check_requirements(): void {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( LOGDANCER_BASENAME );
            wp_die(
                esc_html__( 'Log Dancer requires PHP 7.4 or higher.', 'logdancer' ),
                esc_html__( 'Plugin activation error', 'logdancer' ),
                array( 'back_link' => true )
            );
        }

        global $wp_version;
        if ( version_compare( $wp_version, '5.8', '<' ) ) {
            deactivate_plugins( LOGDANCER_BASENAME );
            wp_die(
                esc_html__( 'Log Dancer requires WordPress 5.8 or higher.', 'logdancer' ),
                esc_html__( 'Plugin activation error', 'logdancer' ),
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Initialise default settings if this is a fresh install.
     */
    public static function create_default_settings(): void {
        $settings = new LogDancer_Settings();
        $settings->create_defaults_if_missing();
    }

    /**
     * Create the database table if it does not exist or is out of date.
     */
    public static function prepare_storage(): void {
        $storage = new LogDancer_DB_Storage();
        $storage->create_table();
    }

    /**
     * Schedule the daily retention cleanup cron event.
     */
    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( 'logdancer_retention_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'logdancer_retention_cleanup' );
        }
    }
}
