<?php
/**
 * Log Dancer — Events.
 *
 * Registers WordPress-native hooks and forwards operational events to the
 * logger. Does NOT write to storage directly.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Events {

    /** @var LogDancer_Logger */
    private LogDancer_Logger $logger;

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

    /**
     * @param LogDancer_Logger $logger
     * @param LogDancer_Policy $policy
     */
    public function __construct( LogDancer_Logger $logger, LogDancer_Policy $policy ) {
        $this->logger = $logger;
        $this->policy = $policy;
    }

    // -----------------------------------------------------------------------
    // Hook registration
    // -----------------------------------------------------------------------

    /**
     * Register all event capture hooks.
     * Called by LogDancer_Plugin::register_runtime_hooks().
     */
    public function register_hooks(): void {
        if ( $this->policy->allow_source_type( 'plugin' ) ) {
            add_action( 'activated_plugin',   array( $this, 'capture_plugin_activation' ),   10, 2 );
            add_action( 'deactivated_plugin', array( $this, 'capture_plugin_deactivation' ), 10, 2 );
        }

        if ( $this->policy->allow_source_type( 'theme' ) ) {
            add_action( 'switch_theme', array( $this, 'capture_theme_switch' ), 10, 3 );
        }

        if ( $this->policy->allow_source_type( 'updater' ) ) {
            add_action( 'upgrader_process_complete', array( $this, 'capture_upgrader_process_complete' ), 10, 2 );
        }

        if ( $this->policy->allow_source_type( 'http_api' ) ) {
            add_action( 'http_api_debug', array( $this, 'capture_http_api_debug' ), 10, 5 );
        }

        if ( $this->policy->allow_source_type( 'cron' ) ) {
            add_filter( 'cron_schedules', array( $this, 'check_cron_health' ), 999 );
        }
    }

    // -----------------------------------------------------------------------
    // Plugin lifecycle
    // -----------------------------------------------------------------------

    /**
     * @param string $plugin_file Plugin basename e.g. "my-plugin/my-plugin.php".
     * @param bool   $network_wide
     */
    public function capture_plugin_activation( string $plugin_file, bool $network_wide ): void {
        $this->logger->log( array(
            'source_type' => 'plugin',
            'event_type'  => 'plugin_activated',
            'severity'    => 'info',
            'message'     => sprintf( 'Plugin activated: %s', $plugin_file ),
            'plugin_slug' => $this->slug_from_file( $plugin_file ),
            'extra_json'  => array(
                'plugin_file'  => $plugin_file,
                'network_wide' => $network_wide,
            ),
        ) );
    }

    /**
     * @param string $plugin_file Plugin basename.
     * @param bool   $network_wide
     */
    public function capture_plugin_deactivation( string $plugin_file, bool $network_wide ): void {
        $this->logger->log( array(
            'source_type' => 'plugin',
            'event_type'  => 'plugin_deactivated',
            'severity'    => 'info',
            'message'     => sprintf( 'Plugin deactivated: %s', $plugin_file ),
            'plugin_slug' => $this->slug_from_file( $plugin_file ),
            'extra_json'  => array(
                'plugin_file'  => $plugin_file,
                'network_wide' => $network_wide,
            ),
        ) );
    }

    // -----------------------------------------------------------------------
    // Theme lifecycle
    // -----------------------------------------------------------------------

    /**
     * @param string    $new_name  New theme name.
     * @param \WP_Theme $new_theme New theme object.
     * @param \WP_Theme $old_theme Previous theme object.
     */
    public function capture_theme_switch( string $new_name, \WP_Theme $new_theme, \WP_Theme $old_theme ): void {
        $this->logger->log( array(
            'source_type' => 'theme',
            'event_type'  => 'theme_switched',
            'severity'    => 'info',
            'message'     => sprintf( 'Theme switched to "%s" (was "%s")', $new_name, $old_theme->get( 'Name' ) ),
            'theme_slug'  => $new_theme->get_stylesheet(),
            'extra_json'  => array(
                'new_theme'      => $new_theme->get_stylesheet(),
                'previous_theme' => $old_theme->get_stylesheet(),
            ),
        ) );
    }

    // -----------------------------------------------------------------------
    // Updater events
    // -----------------------------------------------------------------------

    /**
     * @param \WP_Upgrader $upgrader   Upgrader instance.
     * @param array        $hook_extra Upgrade context data.
     */
    public function capture_upgrader_process_complete( \WP_Upgrader $upgrader, array $hook_extra ): void {
        $type   = $hook_extra['type']   ?? 'unknown';
        $action = $hook_extra['action'] ?? 'unknown';

        $plugins = array();
        if ( isset( $hook_extra['plugin'] ) ) {
            $plugins[] = $hook_extra['plugin'];
        } elseif ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
            $plugins = $hook_extra['plugins'];
        }

        $themes = array();
        if ( isset( $hook_extra['theme'] ) ) {
            $themes[] = $hook_extra['theme'];
        } elseif ( isset( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) ) {
            $themes = $hook_extra['themes'];
        }

        $label = ucfirst( $action ) . ' ' . $type;
        if ( $plugins ) {
            $label .= ': ' . implode( ', ', array_map( array( $this, 'slug_from_file' ), $plugins ) );
        }
        if ( $themes ) {
            $label .= ': ' . implode( ', ', $themes );
        }

        $plugin_slug = $plugins ? $this->slug_from_file( $plugins[0] ) : null;
        $theme_slug  = $themes ? $themes[0] : null;

        $this->logger->log( array(
            'source_type' => 'updater',
            'event_type'  => 'upgrader_process_complete',
            'severity'    => 'info',
            'message'     => $label,
            'plugin_slug' => $plugin_slug,
            'theme_slug'  => $theme_slug,
            'extra_json'  => array(
                'upgrade_type'   => $type,
                'upgrade_action' => $action,
                'plugins'        => $plugins,
                'themes'         => $themes,
                'bulk'           => count( $plugins ) + count( $themes ) > 1,
            ),
        ) );
    }

    // -----------------------------------------------------------------------
    // HTTP API failures
    // -----------------------------------------------------------------------

    /**
     * Fires after an HTTP API response is received. Logs failures only.
     *
     * @param array|\WP_Error $response     HTTP response or error.
     * @param string          $context      Context: 'response' or 'error'.
     * @param string          $class        HTTP transport class name.
     * @param array           $parsed_args  Request args.
     * @param string          $url          Request URL.
     */
    public function capture_http_api_debug( $response, string $context, string $class, array $parsed_args, string $url ): void {
        // Only capture actual failures.
        if ( ! is_wp_error( $response ) ) {
            $status = (int) wp_remote_retrieve_response_code( $response );
            if ( $status < 400 ) {
                return; // Success — skip.
            }
        }

        $is_error = is_wp_error( $response );
        $message  = $is_error
            ? sprintf( 'HTTP request failed: %s — %s', wp_parse_url( $url, PHP_URL_HOST ), $response->get_error_message() )
            : sprintf( 'HTTP request returned %d: %s', wp_remote_retrieve_response_code( $response ), wp_parse_url( $url, PHP_URL_HOST ) );

        $this->logger->log( array(
            'source_type' => 'http_api',
            'event_type'  => 'http_request_failed',
            'severity'    => $is_error ? 'error' : 'warning',
            'message'     => $message,
            'extra_json'  => array(
                'url'           => $this->strip_url_credentials( $url ),
                'method'        => $parsed_args['method'] ?? 'GET',
                'status_code'   => $is_error ? null : (int) wp_remote_retrieve_response_code( $response ),
                'wp_error_code' => $is_error ? $response->get_error_code() : null,
                'context'       => $context,
            ),
        ) );
    }

    // -----------------------------------------------------------------------
    // Cron health check
    // -----------------------------------------------------------------------

    /**
     * Hooked to cron_schedules as a passive check.
     * Fires early in the request; checks if WP-Cron appears overdue.
     *
     * @param array $schedules
     * @return array
     */
    public function check_cron_health( array $schedules ): array {
        $doing_cron = get_transient( 'doing_cron' );
        if ( false !== $doing_cron ) {
            $cron_age = time() - (int) $doing_cron;
            if ( $cron_age > 5 * MINUTE_IN_SECONDS ) {
                $this->logger->log( array(
                    'source_type' => 'cron',
                    'event_type'  => 'cron_lock_stale',
                    'severity'    => 'warning',
                    'message'     => sprintf( 'WP-Cron lock appears stale (%d seconds old).', $cron_age ),
                    'extra_json'  => array( 'lock_age_seconds' => $cron_age ),
                ) );
            }
        }
        return $schedules;
    }

    // -----------------------------------------------------------------------
    // Utility
    // -----------------------------------------------------------------------

    /**
     * Extract the plugin slug (directory name) from a plugin file basename.
     *
     * @param string $plugin_file e.g. "woocommerce/woocommerce.php"
     * @return string
     */
    private function slug_from_file( string $plugin_file ): string {
        $parts = explode( '/', $plugin_file );
        return count( $parts ) > 1 ? $parts[0] : pathinfo( $plugin_file, PATHINFO_FILENAME );
    }

    /**
     * Strip credentials from a URL before logging.
     *
     * @param string $url
     * @return string
     */
    private function strip_url_credentials( string $url ): string {
        $parsed = wp_parse_url( $url );
        if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
            $parsed['user'] = '[REDACTED]';
            $parsed['pass'] = null;
        }
        // Rebuild without credentials.
        $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
        $host   = $parsed['host'] ?? '';
        $port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
        $path   = $parsed['path'] ?? '';
        $query  = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
        return $scheme . $host . $port . $path . $query;
    }
}
