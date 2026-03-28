<?php
/**
 * Plugin Name: Log Dancer
 * Plugin URI:  https://github.com/fashiontechguru/logdancer
 * Description: WordPress observability plugin. Captures plugin/theme lifecycle events, fatal errors, HTTP API failures, and cron anomalies into a structured admin-visible log.
 * Version:     2.0.0
 * Author:      FashionTechGuru
 * Author URI:  https://github.com/fashiontechguru
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: logdancer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit( 'No direct script access allowed.' );

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'LOGDANCER_VERSION',  '2.0.0' );
define( 'LOGDANCER_DB_VERSION', '1' );
define( 'LOGDANCER_FILE',     __FILE__ );
define( 'LOGDANCER_DIR',      plugin_dir_path( __FILE__ ) );
define( 'LOGDANCER_URL',      plugin_dir_url( __FILE__ ) );
define( 'LOGDANCER_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// Core class autoload
// ---------------------------------------------------------------------------

$logdancer_includes = array(
    'includes/helpers.php',
    'includes/class-logdancer-settings.php',
    'includes/class-logdancer-policy.php',
    'includes/class-logdancer-redactor.php',
    'includes/class-logdancer-request-context.php',
    'includes/class-logdancer-db-storage.php',
    'includes/class-logdancer-logger.php',
    'includes/class-logdancer-shutdown-monitor.php',
    'includes/class-logdancer-events.php',
    'includes/class-logdancer-health-check.php',
    'includes/class-logdancer-exporter.php',
    'includes/class-logdancer-admin-table.php',
    'includes/class-logdancer-admin.php',
    'includes/class-logdancer-activator.php',
    'includes/class-logdancer-deactivator.php',
    'includes/class-logdancer-plugin.php',
);

foreach ( $logdancer_includes as $logdancer_file ) {
    $logdancer_path = LOGDANCER_DIR . $logdancer_file;
    if ( file_exists( $logdancer_path ) ) {
        require_once $logdancer_path;
    }
}

// ---------------------------------------------------------------------------
// Lifecycle hooks — registered before plugin class boots
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, array( 'LogDancer_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LogDancer_Deactivator', 'deactivate' ) );

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------

add_action( 'plugins_loaded', static function () {
    $plugin = new LogDancer_Plugin();
    $plugin->boot();
}, 5 );
