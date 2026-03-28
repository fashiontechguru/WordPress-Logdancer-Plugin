<?php
/**
 * Log Dancer — Health Check.
 *
 * Evaluates environment and plugin health and returns a structured report
 * for the admin Health page.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Health_Check {

    /** @var LogDancer_DB_Storage */
    private LogDancer_DB_Storage $storage;

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

    /** @var LogDancer_Settings */
    private LogDancer_Settings $settings;

    /**
     * @param LogDancer_DB_Storage $storage
     * @param LogDancer_Policy     $policy
     * @param LogDancer_Settings   $settings
     */
    public function __construct(
        LogDancer_DB_Storage $storage,
        LogDancer_Policy $policy,
        LogDancer_Settings $settings
    ) {
        $this->storage  = $storage;
        $this->policy   = $policy;
        $this->settings = $settings;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Run all checks and return an array of result objects.
     *
     * @return array Each element: {label, status, message}
     *               status: 'ok' | 'warning' | 'error'
     */
    public function run_all(): array {
        return array(
            $this->check_schema(),
            $this->check_retention_schedule(),
            $this->check_cron_system(),
            $this->check_php_version(),
            $this->check_wp_version(),
            $this->check_advanced_mode_warning(),
            $this->check_recent_fatals(),
        );
    }

    // -----------------------------------------------------------------------
    // Individual checks
    // -----------------------------------------------------------------------

    /**
     * Check that the events table exists and is accessible.
     */
    public function check_schema(): array {
        if ( $this->storage->table_exists() ) {
            $count = $this->storage->count();
            return $this->result(
                'Database table',
                'ok',
                sprintf( 'Table exists. %d event(s) stored.', $count )
            );
        }
        return $this->result(
            'Database table',
            'error',
            'Events table does not exist. Try deactivating and re-activating the plugin.'
        );
    }

    /**
     * Check that the retention cron event is scheduled.
     */
    public function check_retention_schedule(): array {
        $next = wp_next_scheduled( 'logdancer_retention_cleanup' );
        if ( $next ) {
            return $this->result(
                'Retention cleanup schedule',
                'ok',
                sprintf( 'Scheduled. Next run: %s UTC.', gmdate( 'Y-m-d H:i', $next ) )
            );
        }
        return $this->result(
            'Retention cleanup schedule',
            'warning',
            'Retention cleanup cron job is not scheduled. It will be registered on next plugin boot.'
        );
    }

    /**
     * Check that WordPress cron appears functional.
     */
    public function check_cron_system(): array {
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            return $this->result(
                'WordPress cron',
                'warning',
                'WP_CRON is disabled (DISABLE_WP_CRON=true). Ensure an external cron job is configured.'
            );
        }
        return $this->result( 'WordPress cron', 'ok', 'WP-Cron is enabled.' );
    }

    /**
     * Check PHP version meets minimum requirement.
     */
    public function check_php_version(): array {
        $version = PHP_VERSION;
        if ( version_compare( $version, '7.4', '>=' ) ) {
            return $this->result( 'PHP version', 'ok', "PHP {$version} — meets requirements." );
        }
        return $this->result( 'PHP version', 'error', "PHP {$version} — requires 7.4 or higher." );
    }

    /**
     * Check WordPress version meets minimum requirement.
     */
    public function check_wp_version(): array {
        global $wp_version;
        if ( version_compare( $wp_version, '5.8', '>=' ) ) {
            return $this->result( 'WordPress version', 'ok', "WordPress {$wp_version} — meets requirements." );
        }
        return $this->result( 'WordPress version', 'warning', "WordPress {$wp_version} — recommends 5.8 or higher." );
    }

    /**
     * Warn if advanced PHP warning capture is enabled.
     */
    public function check_advanced_mode_warning(): array {
        if ( $this->policy->should_capture_advanced_php_warnings() ) {
            return $this->result(
                'Advanced PHP warning capture',
                'warning',
                'Advanced PHP warning capture is enabled. This replaces the PHP error handler and may interfere with other plugins.'
            );
        }
        return $this->result( 'Advanced PHP warning capture', 'ok', 'Disabled (recommended).' );
    }

    /**
     * Check for critical/fatal events in the last 7 days.
     */
    public function check_recent_fatals(): array {
        if ( ! $this->storage->table_exists() ) {
            return $this->result( 'Recent fatal events', 'ok', 'No data yet.' );
        }
        $count = $this->storage->count( array(
            'severity'  => 'critical',
            'date_from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
        ) );
        if ( $count > 0 ) {
            return $this->result(
                'Recent fatal events',
                'warning',
                sprintf( '%d critical event(s) in the last 7 days. Review the Events tab.', $count )
            );
        }
        return $this->result( 'Recent fatal events', 'ok', 'No critical events in the last 7 days.' );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a result array.
     *
     * @param string $label
     * @param string $status  'ok' | 'warning' | 'error'
     * @param string $message
     * @return array
     */
    private function result( string $label, string $status, string $message ): array {
        return compact( 'label', 'status', 'message' );
    }
}
