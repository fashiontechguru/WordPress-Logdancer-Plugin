<?php
/**
 * Log Dancer — Settings.
 *
 * Single-option settings store. Merges saved values with defaults and
 * provides typed getters. Handles simple version-to-version migration.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Settings {

    /** @var string WordPress option name. */
    const OPTION_KEY = 'logdancer_settings';

    /** @var string Version option name. */
    const VERSION_KEY = 'logdancer_version';

    /** @var string DB schema version option name. */
    const DB_VERSION_KEY = 'logdancer_db_version';

    /** @var array Resolved settings (defaults merged with saved). */
    private array $settings;

    /**
     * Load settings from DB, merging with defaults.
     */
    public function __construct() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }
        $this->settings = array_merge( $this->defaults(), $saved );
    }

    // -----------------------------------------------------------------------
    // Defaults
    // -----------------------------------------------------------------------

    /**
     * Return the full default settings array.
     *
     * @return array
     */
    public function defaults(): array {
        return array(
            'enabled'                         => true,
            'capture_fatals'                  => true,
            'capture_plugin_events'           => true,
            'capture_theme_events'            => true,
            'capture_updater_events'          => true,
            'capture_http_api_failures'       => true,
            'capture_cron_anomalies'          => true,
            'capture_db_errors'               => false,
            'capture_auth_events'             => false,
            'capture_php_warnings_advanced'   => false,
            'privacy_mode'                    => 'balanced',
            'redact_paths'                    => true,
            'redact_query_strings'            => true,
            'anonymize_ip'                    => true,
            'retention_days'                  => 30,
            'max_events'                      => 10000,
            'full_cleanup_on_uninstall'       => false,
        );
    }

    // -----------------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------------

    /**
     * Get a single setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Fallback if key is not found.
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
    }

    /**
     * Return all resolved settings.
     *
     * @return array
     */
    public function get_all(): array {
        return $this->settings;
    }

    // -----------------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------------

    /**
     * Sanitize and save a new settings array.
     *
     * @param array $input Raw input from settings form.
     * @return bool Whether the option was updated.
     */
    public function save( array $input ): bool {
        $clean = $this->sanitize( $input );
        $this->settings = $clean;
        return update_option( self::OPTION_KEY, $clean );
    }

    /**
     * Sanitize settings input. Boolean fields use checkbox-presence logic.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings merged with defaults.
     */
    public function sanitize( array $input ): array {
        $defaults = $this->defaults();
        $clean    = array();

        // Boolean (checkbox) settings.
        $booleans = array(
            'enabled', 'capture_fatals', 'capture_plugin_events',
            'capture_theme_events', 'capture_updater_events',
            'capture_http_api_failures', 'capture_cron_anomalies',
            'capture_db_errors', 'capture_auth_events',
            'capture_php_warnings_advanced', 'redact_paths',
            'redact_query_strings', 'anonymize_ip',
            'full_cleanup_on_uninstall',
        );
        foreach ( $booleans as $key ) {
            $clean[ $key ] = ! empty( $input[ $key ] );
        }

        // String with allowlist.
        $privacy_mode = sanitize_key( $input['privacy_mode'] ?? 'balanced' );
        $clean['privacy_mode'] = in_array( $privacy_mode, array( 'strict', 'balanced', 'verbose' ), true )
            ? $privacy_mode
            : 'balanced';

        // Integer settings.
        $clean['retention_days'] = max( 1, min( 365, (int) ( $input['retention_days'] ?? 30 ) ) );
        $clean['max_events']     = max( 100, min( 100000, (int) ( $input['max_events'] ?? 10000 ) ) );

        return array_merge( $defaults, $clean );
    }

    // -----------------------------------------------------------------------
    // Installation helpers
    // -----------------------------------------------------------------------

    /**
     * Write default settings if none exist yet.
     */
    public function create_defaults_if_missing(): void {
        if ( false === get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, $this->defaults() );
        }
    }
}
