<?php
/**
 * Uninstall Log Dancer.
 *
 * Runs when the user clicks "Delete" on the Plugins screen.
 * Deletes options and, if the user opted in, the custom DB table and log files.
 *
 * @package LogDancer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Load settings so we can honour the full_cleanup_on_uninstall flag.
$settings = get_option( 'logdancer_settings', array() );
$full_cleanup = ! empty( $settings['full_cleanup_on_uninstall'] );

// Always remove plugin options.
delete_option( 'logdancer_settings' );
delete_option( 'logdancer_version' );
delete_option( 'logdancer_db_version' );

// Remove scheduled cron event if it exists.
$timestamp = wp_next_scheduled( 'logdancer_retention_cleanup' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'logdancer_retention_cleanup' );
}
wp_clear_scheduled_hook( 'logdancer_retention_cleanup' );

if ( $full_cleanup ) {
    // Drop the events table.
    $table = $wpdb->prefix . 'logdancer_events';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}
