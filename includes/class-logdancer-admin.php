<?php
/**
 * Log Dancer — Admin.
 *
 * Registers admin menus, settings, and page renderers.
 * All output is escaped. All state-changing actions require nonce + capability.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Admin {

    /** @var LogDancer_DB_Storage */
    private LogDancer_DB_Storage $storage;

    /** @var LogDancer_Settings */
    private LogDancer_Settings $settings;

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

    /** @var LogDancer_Health_Check */
    private LogDancer_Health_Check $health;

    /** @var LogDancer_Exporter */
    private LogDancer_Exporter $exporter;

    /** @var string Admin page slug. */
    const MENU_SLUG = 'logdancer';

    /**
     * @param LogDancer_DB_Storage  $storage
     * @param LogDancer_Settings    $settings
     * @param LogDancer_Policy      $policy
     * @param LogDancer_Health_Check $health
     * @param LogDancer_Exporter    $exporter
     */
    public function __construct(
        LogDancer_DB_Storage $storage,
        LogDancer_Settings $settings,
        LogDancer_Policy $policy,
        LogDancer_Health_Check $health,
        LogDancer_Exporter $exporter
    ) {
        $this->storage  = $storage;
        $this->settings = $settings;
        $this->policy   = $policy;
        $this->health   = $health;
        $this->exporter = $exporter;
    }

    // -----------------------------------------------------------------------
    // Hook registrations
    // -----------------------------------------------------------------------

    /**
     * Register admin hooks. Called by LogDancer_Plugin::register_admin_hooks().
     */
    public function register_hooks(): void {
        add_action( 'admin_menu',        array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',        array( $this, 'register_settings' ) );
        add_action( 'admin_init',        array( $this, 'handle_actions' ) );
    }

    // -----------------------------------------------------------------------
    // Menu
    // -----------------------------------------------------------------------

    /**
     * Add the top-level "Log Dancer" admin menu.
     */
    public function register_menu(): void {
        add_menu_page(
            __( 'Log Dancer', 'logdancer' ),
            __( 'Log Dancer', 'logdancer' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_overview_page' ),
            'dashicons-list-view',
            75
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Overview', 'logdancer' ),
            __( 'Overview', 'logdancer' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_overview_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Events', 'logdancer' ),
            __( 'Events', 'logdancer' ),
            'manage_options',
            self::MENU_SLUG . '-events',
            array( $this, 'render_events_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'logdancer' ),
            __( 'Settings', 'logdancer' ),
            'manage_options',
            self::MENU_SLUG . '-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Health', 'logdancer' ),
            __( 'Health', 'logdancer' ),
            'manage_options',
            self::MENU_SLUG . '-health',
            array( $this, 'render_health_page' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Export', 'logdancer' ),
            __( 'Export', 'logdancer' ),
            'manage_options',
            self::MENU_SLUG . '-export',
            array( $this, 'render_export_page' )
        );
    }

    // -----------------------------------------------------------------------
    // Assets
    // -----------------------------------------------------------------------

    /**
     * Enqueue admin styles on Log Dancer pages.
     *
     * @param string $hook_suffix Current page hook.
     */
    public function enqueue_assets( string $hook_suffix ): void {
        if ( false === strpos( $hook_suffix, 'logdancer' ) ) {
            return;
        }
        wp_enqueue_style(
            'logdancer-admin',
            LOGDANCER_URL . 'assets/css/admin.css',
            array(),
            LOGDANCER_VERSION
        );
    }

    // -----------------------------------------------------------------------
    // Settings API registration
    // -----------------------------------------------------------------------

    /**
     * Register the settings group for use with settings_fields().
     */
    public function register_settings(): void {
        register_setting(
            'logdancer_settings_group',
            LogDancer_Settings::OPTION_KEY,
            array(
                'sanitize_callback' => array( $this->settings, 'sanitize' ),
            )
        );
    }

    // -----------------------------------------------------------------------
    // Action handlers
    // -----------------------------------------------------------------------

    /**
     * Handle admin form actions (clear events, export triggers).
     */
    public function handle_actions(): void {
        if ( ! isset( $_POST['logdancer_action'] ) && ! isset( $_GET['logdancer_action'] ) ) {
            return;
        }

        $action = sanitize_key( $_POST['logdancer_action'] ?? $_GET['logdancer_action'] ?? '' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'logdancer' ), 403 );
        }

        switch ( $action ) {
            case 'clear_events':
                check_admin_referer( 'logdancer_clear_events' );
                $deleted = $this->storage->truncate();
                $this->redirect_with_notice(
                    self::MENU_SLUG . '-export',
                    sprintf( _n( 'Cleared %d event.', 'Cleared %d events.', $deleted, 'logdancer' ), $deleted ),
                    'success'
                );
                break;

            case 'export_csv':
                check_admin_referer( 'logdancer_export' );
                $this->exporter->export_csv( $this->filters_from_request() );
                break;

            case 'export_json':
                check_admin_referer( 'logdancer_export' );
                $this->exporter->export_json( $this->filters_from_request() );
                break;
        }
    }

    // -----------------------------------------------------------------------
    // Page renderers
    // -----------------------------------------------------------------------

    /**
     * Overview / dashboard page.
     */
    public function render_overview_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $summary  = $this->storage->table_exists() ? $this->storage->severity_summary( 24 ) : array();
        $critical = $this->storage->table_exists() ? $this->storage->count( array( 'severity' => 'critical', 'date_from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ) ) ) : 0;
        $last_fatal = $this->storage->table_exists() ? $this->storage->latest( array( 'source_type' => 'fatal' ) ) : null;
        require LOGDANCER_DIR . 'admin/views/page-overview.php';
    }

    /**
     * Events list page.
     */
    public function render_events_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $table = new LogDancer_Admin_Table( $this->storage );
        $table->prepare_items();
        require LOGDANCER_DIR . 'admin/views/page-events.php';
    }

    /**
     * Settings page.
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $current_settings = $this->settings->get_all();
        require LOGDANCER_DIR . 'admin/views/page-settings.php';
    }

    /**
     * Health page.
     */
    public function render_health_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $checks = $this->health->run_all();
        require LOGDANCER_DIR . 'admin/views/page-health.php';
    }

    /**
     * Export page.
     */
    public function render_export_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $total = $this->storage->table_exists() ? $this->storage->count() : 0;
        require LOGDANCER_DIR . 'admin/views/page-export.php';
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a filters array from sanitised GET/POST params.
     *
     * @return array
     */
    private function filters_from_request(): array {
        $filters = array();
        $source  = array_merge( $_GET, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
        foreach ( array( 'severity', 'source_type', 'event_type', 'plugin_slug' ) as $key ) {
            if ( ! empty( $source[ $key ] ) ) {
                $filters[ $key ] = sanitize_key( $source[ $key ] );
            }
        }
        foreach ( array( 'date_from', 'date_to' ) as $key ) {
            if ( ! empty( $source[ $key ] ) ) {
                $filters[ $key ] = sanitize_text_field( wp_unslash( $source[ $key ] ) );
            }
        }
        return $filters;
    }

    /**
     * Redirect back to an admin page with a notice in the query string.
     *
     * @param string $page
     * @param string $message
     * @param string $type 'success' | 'error'
     */
    private function redirect_with_notice( string $page, string $message, string $type = 'success' ): void {
        $url = add_query_arg(
            array(
                'page'             => $page,
                'logdancer_notice' => rawurlencode( $message ),
                'logdancer_type'   => $type,
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }
}
