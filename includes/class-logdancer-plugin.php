<?php
/**
 * Log Dancer — Plugin orchestrator.
 *
 * Builds all service instances, wires dependencies, and registers hooks.
 * Contains no business logic itself.
 *
 * Boot sequence:
 *   1. Load settings + policy
 *   2. Build services
 *   3. Register runtime hooks
 *   4. Register admin hooks (wp-admin only)
 *   5. Register shutdown monitor if fatal capture is enabled
 *   6. Register retention cron handler
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Plugin {

    // -----------------------------------------------------------------------
    // Services (set during build_services)
    // -----------------------------------------------------------------------

    /** @var LogDancer_Settings */
    private LogDancer_Settings $settings;

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

    /** @var LogDancer_Redactor */
    private LogDancer_Redactor $redactor;

    /** @var LogDancer_Request_Context */
    private LogDancer_Request_Context $request_context;

    /** @var LogDancer_DB_Storage */
    private LogDancer_DB_Storage $storage;

    /** @var LogDancer_Logger */
    private LogDancer_Logger $logger;

    /** @var LogDancer_Shutdown_Monitor */
    private LogDancer_Shutdown_Monitor $shutdown_monitor;

    /** @var LogDancer_Events */
    private LogDancer_Events $events;

    /** @var LogDancer_Health_Check */
    private LogDancer_Health_Check $health;

    /** @var LogDancer_Exporter */
    private LogDancer_Exporter $exporter;

    /** @var LogDancer_Admin */
    private LogDancer_Admin $admin;

    // -----------------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------------

    /**
     * Boot the plugin.
     */
    public function boot(): void {
        $this->build_services();

        if ( ! $this->policy->is_enabled() ) {
            return; // Plugin is disabled via settings — do nothing.
        }

        $this->register_runtime_hooks();

        if ( $this->is_admin_request() ) {
            $this->register_admin_hooks();
        }

        if ( $this->policy->allow_source_type( 'fatal' ) ) {
            $this->shutdown_monitor->register();
        }
    }

    // -----------------------------------------------------------------------
    // Service wiring
    // -----------------------------------------------------------------------

    /**
     * Instantiate all services and inject dependencies.
     */
    public function build_services(): void {
        $this->settings        = new LogDancer_Settings();
        $this->policy          = new LogDancer_Policy( $this->settings );
        $this->redactor        = new LogDancer_Redactor( $this->policy );
        $this->request_context = new LogDancer_Request_Context( $this->policy );
        $this->storage         = new LogDancer_DB_Storage();
        $this->logger          = new LogDancer_Logger(
            $this->storage,
            $this->redactor,
            $this->policy,
            $this->request_context
        );
        $this->shutdown_monitor = new LogDancer_Shutdown_Monitor( $this->logger );
        $this->events           = new LogDancer_Events( $this->logger, $this->policy );
        $this->health           = new LogDancer_Health_Check( $this->storage, $this->policy, $this->settings );
        $this->exporter         = new LogDancer_Exporter( $this->storage );
        $this->admin            = new LogDancer_Admin(
            $this->storage,
            $this->settings,
            $this->policy,
            $this->health,
            $this->exporter
        );
    }

    // -----------------------------------------------------------------------
    // Hook registration
    // -----------------------------------------------------------------------

    /**
     * Register non-admin runtime hooks.
     */
    public function register_runtime_hooks(): void {
        $this->events->register_hooks();

        // Retention cleanup cron callback.
        add_action( 'logdancer_retention_cleanup', array( $this, 'run_retention_cleanup' ) );

        // Ensure cron schedule exists (re-schedules if it was dropped).
        if ( ! wp_next_scheduled( 'logdancer_retention_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'logdancer_retention_cleanup' );
        }
    }

    /**
     * Register wp-admin hooks.
     */
    public function register_admin_hooks(): void {
        $this->admin->register_hooks();
    }

    // -----------------------------------------------------------------------
    // Cron callback
    // -----------------------------------------------------------------------

    /**
     * Called by WP-Cron daily. Deletes events older than the retention window.
     */
    public function run_retention_cleanup(): void {
        if ( $this->storage->table_exists() ) {
            $this->storage->delete_older_than( $this->policy->retention_days() );
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Return true if the current request is an admin (wp-admin) request.
     *
     * @return bool
     */
    public function is_admin_request(): bool {
        return is_admin() || ( defined( 'WP_CLI' ) && WP_CLI );
    }

    /**
     * Whether a specific feature is enabled by policy.
     *
     * @param string $source_type
     * @return bool
     */
    public function is_feature_enabled( string $source_type ): bool {
        return $this->policy->allow_source_type( $source_type );
    }
}
