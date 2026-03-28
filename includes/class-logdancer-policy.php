<?php
/**
 * Log Dancer — Policy.
 *
 * Translates the settings array into runtime policy decisions.
 * All "should we capture X?" questions come through here.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Policy {

    /** @var LogDancer_Settings */
    private LogDancer_Settings $settings;

    /**
     * @param LogDancer_Settings $settings
     */
    public function __construct( LogDancer_Settings $settings ) {
        $this->settings = $settings;
    }

    // -----------------------------------------------------------------------
    // Feature gates
    // -----------------------------------------------------------------------

    /**
     * Is the plugin active at all?
     */
    public function is_enabled(): bool {
        return (bool) $this->settings->get( 'enabled', true );
    }

    /**
     * Should we capture an event of the given source_type?
     *
     * @param string $source_type e.g. 'plugin', 'fatal', 'http_api'
     * @return bool
     */
    public function allow_source_type( string $source_type ): bool {
        if ( ! $this->is_enabled() ) {
            return false;
        }
        $map = array(
            'fatal'    => 'capture_fatals',
            'plugin'   => 'capture_plugin_events',
            'theme'    => 'capture_theme_events',
            'updater'  => 'capture_updater_events',
            'http_api' => 'capture_http_api_failures',
            'cron'     => 'capture_cron_anomalies',
            'database' => 'capture_db_errors',
            'auth'     => 'capture_auth_events',
            'php'      => 'capture_php_warnings_advanced',
            'system'   => 'enabled',
        );
        $setting_key = $map[ $source_type ] ?? null;
        if ( null === $setting_key ) {
            return $this->is_enabled(); // unknown type: allow if generally enabled
        }
        return (bool) $this->settings->get( $setting_key, false );
    }

    // -----------------------------------------------------------------------
    // Privacy policy
    // -----------------------------------------------------------------------

    /**
     * Should file paths be redacted to basename-only?
     */
    public function should_redact_paths(): bool {
        return (bool) $this->settings->get( 'redact_paths', true );
    }

    /**
     * Should query strings be stripped from request URIs?
     */
    public function should_redact_query_strings(): bool {
        return (bool) $this->settings->get( 'redact_query_strings', true );
    }

    /**
     * Should IP addresses be anonymized?
     */
    public function should_anonymize_ip(): bool {
        return (bool) $this->settings->get( 'anonymize_ip', true );
    }

    /**
     * Should advanced PHP warning capture be enabled?
     */
    public function should_capture_advanced_php_warnings(): bool {
        return (bool) $this->settings->get( 'capture_php_warnings_advanced', false );
    }

    /**
     * Return the current privacy mode ('strict', 'balanced', 'verbose').
     */
    public function privacy_mode(): string {
        return (string) $this->settings->get( 'privacy_mode', 'balanced' );
    }

    // -----------------------------------------------------------------------
    // Retention
    // -----------------------------------------------------------------------

    /**
     * How many days should events be retained?
     */
    public function retention_days(): int {
        return (int) $this->settings->get( 'retention_days', 30 );
    }

    /**
     * Maximum event rows to keep in the table.
     */
    public function max_events(): int {
        return (int) $this->settings->get( 'max_events', 10000 );
    }
}
