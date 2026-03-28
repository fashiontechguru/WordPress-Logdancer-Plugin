<?php
/**
 * Log Dancer — Admin Table.
 *
 * Extends WP_List_Table to display paginated, filterable event records.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

// WP_List_Table is not auto-loaded outside wp-admin.
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LogDancer_Admin_Table extends WP_List_Table {

    /** @var LogDancer_DB_Storage */
    private LogDancer_DB_Storage $storage;

    /** @var int Total rows for current filter. */
    private int $total_items = 0;

    /**
     * @param LogDancer_DB_Storage $storage
     */
    public function __construct( LogDancer_DB_Storage $storage ) {
        $this->storage = $storage;
        parent::__construct( array(
            'singular' => 'event',
            'plural'   => 'events',
            'ajax'     => false,
        ) );
    }

    // -----------------------------------------------------------------------
    // Columns
    // -----------------------------------------------------------------------

    /**
     * Define table columns.
     *
     * @return array
     */
    public function get_columns(): array {
        return array(
            'severity'        => __( 'Severity', 'logdancer' ),
            'created_at_utc'  => __( 'Time (UTC)', 'logdancer' ),
            'source_type'     => __( 'Source', 'logdancer' ),
            'event_type'      => __( 'Event', 'logdancer' ),
            'message'         => __( 'Message', 'logdancer' ),
            'plugin_slug'     => __( 'Plugin / Theme', 'logdancer' ),
            'request_context' => __( 'Context', 'logdancer' ),
            'user_id'         => __( 'User', 'logdancer' ),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns(): array {
        return array(
            'severity'       => array( 'severity', false ),
            'created_at_utc' => array( 'created_at_utc', true ),
            'source_type'    => array( 'source_type', false ),
            'event_type'     => array( 'event_type', false ),
        );
    }

    // -----------------------------------------------------------------------
    // Data
    // -----------------------------------------------------------------------

    /**
     * Prepare items for display. Reads filter params from $_GET.
     */
    public function prepare_items(): void {
        $per_page = $this->get_items_per_page( 'logdancer_events_per_page', 50 );
        $page_num = $this->get_pagenum();
        $offset   = ( $page_num - 1 ) * $per_page;

        $filters = $this->current_filters();
        $filters['per_page'] = $per_page;
        $filters['offset']   = $offset;
        $filters['orderby']  = $this->get_safe_orderby();
        $filters['order']    = $this->get_safe_order();

        $this->items       = $this->storage->query( $filters );
        $this->total_items = $this->storage->count( $filters );

        $this->set_pagination_args( array(
            'total_items' => $this->total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $this->total_items / $per_page ),
        ) );

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
    }

    /**
     * Default column renderer.
     *
     * @param object $item        Row object.
     * @param string $column_name Column key.
     * @return string
     */
    public function column_default( $item, $column_name ): string {
        return esc_html( $item->$column_name ?? '' );
    }

    /**
     * Severity column with colour badge.
     *
     * @param object $item
     * @return string
     */
    public function column_severity( object $item ): string {
        $severity = esc_html( $item->severity ?? 'info' );
        $cls      = 'logdancer-badge logdancer-badge--' . logdancer_severity_badge_class( $item->severity ?? 'info' );
        return '<span class="' . esc_attr( $cls ) . '">' . $severity . '</span>';
    }

    /**
     * Message column — truncated for readability.
     *
     * @param object $item
     * @return string
     */
    public function column_message( object $item ): string {
        $msg = esc_html( $item->message ?? '' );
        if ( mb_strlen( $msg ) > 120 ) {
            $short = esc_html( mb_substr( $item->message, 0, 120 ) ) . '&hellip;';
            return '<span title="' . $msg . '">' . $short . '</span>';
        }
        return $msg;
    }

    /**
     * Plugin / Theme column — shows whichever is set.
     *
     * @param object $item
     * @return string
     */
    public function column_plugin_slug( object $item ): string {
        if ( ! empty( $item->plugin_slug ) ) {
            return esc_html( $item->plugin_slug );
        }
        if ( ! empty( $item->theme_slug ) ) {
            return '<em>' . esc_html( $item->theme_slug ) . '</em>';
        }
        return '<span class="logdancer-muted">—</span>';
    }

    /**
     * User ID column — link to user profile when possible.
     *
     * @param object $item
     * @return string
     */
    public function column_user_id( object $item ): string {
        if ( empty( $item->user_id ) ) {
            return '<span class="logdancer-muted">—</span>';
        }
        $user = get_userdata( (int) $item->user_id );
        if ( $user ) {
            return '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
        }
        return esc_html( (string) $item->user_id );
    }

    /**
     * No items message.
     */
    public function no_items(): void {
        esc_html_e( 'No events found matching the current filters.', 'logdancer' );
    }

    // -----------------------------------------------------------------------
    // Filters
    // -----------------------------------------------------------------------

    /**
     * Output the filter form above the table.
     *
     * @param string $which 'top' or 'bottom'
     */
    public function extra_tablenav( $which ): void {
        if ( 'top' !== $which ) {
            return;
        }
        $filters = $this->current_filters();
        ?>
        <div class="alignleft actions logdancer-filters">
            <select name="severity">
                <option value=""><?php esc_html_e( 'All Severities', 'logdancer' ); ?></option>
                <?php foreach ( array( 'debug', 'info', 'notice', 'warning', 'error', 'critical' ) as $sev ) : ?>
                    <option value="<?php echo esc_attr( $sev ); ?>"
                        <?php selected( $filters['severity'] ?? '', $sev ); ?>>
                        <?php echo esc_html( ucfirst( $sev ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="source_type">
                <option value=""><?php esc_html_e( 'All Sources', 'logdancer' ); ?></option>
                <?php foreach ( array( 'plugin', 'theme', 'updater', 'fatal', 'http_api', 'cron', 'database', 'system' ) as $src ) : ?>
                    <option value="<?php echo esc_attr( $src ); ?>"
                        <?php selected( $filters['source_type'] ?? '', $src ); ?>>
                        <?php echo esc_html( ucfirst( str_replace( '_', ' ', $src ) ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'From', 'logdancer' ); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'To', 'logdancer' ); ?>">
            <?php submit_button( __( 'Filter', 'logdancer' ), 'secondary', 'filter_action', false ); ?>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a filters array from sanitised GET params.
     *
     * @return array
     */
    private function current_filters(): array {
        $filters = array();
        foreach ( array( 'severity', 'source_type', 'event_type', 'plugin_slug' ) as $key ) {
            // phpcs:ignore WordPress.Security.NonceVerification
            if ( ! empty( $_GET[ $key ] ) ) {
                $filters[ $key ] = sanitize_key( $_GET[ $key ] );
            }
        }
        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! empty( $_GET['date_from'] ) ) {
            $filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! empty( $_GET['date_to'] ) ) {
            $filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
        }
        return $filters;
    }

    /**
     * Return a safe ORDER BY column name.
     */
    private function get_safe_orderby(): string {
        $allowed = array( 'id', 'created_at_utc', 'severity', 'source_type', 'event_type' );
        // phpcs:ignore WordPress.Security.NonceVerification
        $orderby = sanitize_key( $_GET['orderby'] ?? 'created_at_utc' );
        return in_array( $orderby, $allowed, true ) ? $orderby : 'created_at_utc';
    }

    /**
     * Return 'ASC' or 'DESC'.
     */
    private function get_safe_order(): string {
        // phpcs:ignore WordPress.Security.NonceVerification
        $order = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) );
        return $order === 'ASC' ? 'ASC' : 'DESC';
    }
}
