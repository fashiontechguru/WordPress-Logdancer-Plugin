<?php
/**
 * Log Dancer — DB Storage.
 *
 * Manages the custom events table. The only class that writes to or reads from
 * {prefix}logdancer_events. All inserts must come from LogDancer_Logger.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_DB_Storage {

    /** @var string Table name (with prefix). */
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'logdancer_events';
    }

    // -----------------------------------------------------------------------
    // Schema management
    // -----------------------------------------------------------------------

    /**
     * Create or upgrade the events table using dbDelta.
     */
    public function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table           = $this->table;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_uuid CHAR(36) NOT NULL,
            created_at_utc DATETIME NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            file_path TEXT NULL,
            file_basename VARCHAR(255) NULL,
            line_number INT UNSIGNED NULL,
            plugin_slug VARCHAR(191) NULL,
            theme_slug VARCHAR(191) NULL,
            request_context VARCHAR(50) NULL,
            request_uri TEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            user_roles VARCHAR(255) NULL,
            site_id BIGINT UNSIGNED NULL,
            memory_usage BIGINT UNSIGNED NULL,
            peak_memory_usage BIGINT UNSIGNED NULL,
            event_hash CHAR(64) NULL,
            extra_json LONGTEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_uuid (event_uuid),
            KEY created_at_utc (created_at_utc),
            KEY severity (severity),
            KEY source_type (source_type),
            KEY event_type (event_type),
            KEY plugin_slug (plugin_slug),
            KEY theme_slug (theme_slug),
            KEY request_context (request_context),
            KEY user_id (user_id),
            KEY site_id (site_id),
            KEY event_hash (event_hash)
        ) {$charset_collate};";
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        dbDelta( $sql );

        update_option( LogDancer_Settings::DB_VERSION_KEY, LOGDANCER_DB_VERSION );
    }

    /**
     * Check whether the events table exists.
     *
     * @return bool
     */
    public function table_exists(): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
        return $found === $this->table;
    }

    // -----------------------------------------------------------------------
    // Write
    // -----------------------------------------------------------------------

    /**
     * Insert a fully-prepared event row.
     *
     * @param array $event Normalised, redacted event data.
     * @return int|false Inserted row ID or false on failure.
     */
    public function write( array $event ) {
        global $wpdb;

        $row = array(
            'event_uuid'       => $event['event_uuid']       ?? logdancer_generate_uuid4(),
            'created_at_utc'   => $event['created_at_utc']   ?? gmdate( 'Y-m-d H:i:s' ),
            'source_type'      => $event['source_type']      ?? 'system',
            'event_type'       => $event['event_type']       ?? 'generic',
            'severity'         => $event['severity']         ?? 'info',
            'message'          => $event['message']          ?? '',
            'file_path'        => $event['file_path']        ?? null,
            'file_basename'    => $event['file_basename']    ?? null,
            'line_number'      => isset( $event['line_number'] ) ? (int) $event['line_number'] : null,
            'plugin_slug'      => $event['plugin_slug']      ?? null,
            'theme_slug'       => $event['theme_slug']       ?? null,
            'request_context'  => $event['request_context']  ?? null,
            'request_uri'      => $event['request_uri']      ?? null,
            'user_id'          => isset( $event['user_id'] ) ? (int) $event['user_id'] : null,
            'user_roles'       => $event['user_roles']       ?? null,
            'site_id'          => isset( $event['site_id'] ) ? (int) $event['site_id'] : get_current_blog_id(),
            'memory_usage'     => isset( $event['memory_usage'] ) ? (int) $event['memory_usage'] : null,
            'peak_memory_usage' => isset( $event['peak_memory_usage'] ) ? (int) $event['peak_memory_usage'] : null,
            'event_hash'       => $event['event_hash']       ?? null,
            'extra_json'       => $event['extra_json']       ?? null,
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->insert( $this->table, $row );
        return $result ? (int) $wpdb->insert_id : false;
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    /**
     * Query events with optional filters and pagination.
     *
     * @param array $filters {
     *   @type string $severity        Filter by severity level.
     *   @type string $source_type     Filter by source type.
     *   @type string $event_type      Filter by event type.
     *   @type string $plugin_slug     Filter by plugin slug.
     *   @type int    $user_id         Filter by user ID.
     *   @type string $date_from       YYYY-MM-DD lower bound.
     *   @type string $date_to         YYYY-MM-DD upper bound.
     *   @type int    $per_page        Rows per page (default 50).
     *   @type int    $offset          Row offset (default 0).
     *   @type string $orderby         Column name (default created_at_utc).
     *   @type string $order           ASC or DESC (default DESC).
     * }
     * @return array Array of row objects.
     */
    public function query( array $filters = [] ): array {
        global $wpdb;

        [ $where, $values ] = $this->build_where( $filters );

        $per_page = max( 1, (int) ( $filters['per_page'] ?? 50 ) );
        $offset   = max( 0, (int) ( $filters['offset'] ?? 0 ) );

        $allowed_order_cols = array(
            'id', 'created_at_utc', 'severity', 'source_type',
            'event_type', 'plugin_slug', 'user_id',
        );
        $orderby = in_array( $filters['orderby'] ?? '', $allowed_order_cols, true )
            ? $filters['orderby']
            : 'created_at_utc';
        $order   = strtoupper( $filters['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

        $table = $this->table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) ?: array();
    }

    /**
     * Count events matching given filters.
     *
     * @param array $filters Same keys as query(), pagination keys ignored.
     * @return int
     */
    public function count( array $filters = [] ): int {
        global $wpdb;

        [ $where, $values ] = $this->build_where( $filters );
        $table = $this->table;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM {$table} {$where}";

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Return summary counts grouped by severity for overview widgets.
     *
     * @param int $hours Look-back window in hours (default 24).
     * @return array Keyed by severity.
     */
    public function severity_summary( int $hours = 24 ): array {
        global $wpdb;
        $table   = $this->table;
        $cutoff  = gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT severity, COUNT(*) AS cnt FROM {$table} WHERE created_at_utc >= %s GROUP BY severity",
                $cutoff
            )
        );

        $out = array( 'debug' => 0, 'info' => 0, 'notice' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0 );
        foreach ( $rows as $row ) {
            if ( isset( $out[ $row->severity ] ) ) {
                $out[ $row->severity ] = (int) $row->cnt;
            }
        }
        return $out;
    }

    /**
     * Return the most recent event matching given filters, or null.
     *
     * @param array $filters
     * @return object|null
     */
    public function latest( array $filters = [] ): ?object {
        $rows = $this->query( array_merge( $filters, array( 'per_page' => 1, 'offset' => 0, 'order' => 'DESC' ) ) );
        return $rows[0] ?? null;
    }

    // -----------------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------------

    /**
     * Delete rows older than a given number of days.
     *
     * @param int $days
     * @return int Rows deleted.
     */
    public function delete_older_than( int $days ): int {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
        $table  = $this->table;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at_utc < %s", $cutoff ) );
        return (int) $wpdb->rows_affected;
    }

    /**
     * Delete all rows in the table.
     *
     * @return int Rows deleted.
     */
    public function truncate(): int {
        global $wpdb;
        $table = $this->table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "TRUNCATE TABLE {$table}" );
        return (int) $wpdb->rows_affected;
    }

    // -----------------------------------------------------------------------
    // Health
    // -----------------------------------------------------------------------

    /**
     * Return basic health info about this storage backend.
     *
     * @return array{ok: bool, table_exists: bool, row_count: int, oldest_event: string|null}
     */
    public function health_check(): array {
        $exists = $this->table_exists();
        return array(
            'ok'           => $exists,
            'table_exists' => $exists,
            'row_count'    => $exists ? $this->count() : 0,
            'oldest_event' => $exists ? $this->oldest_event_date() : null,
        );
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Build a WHERE clause and values array from filters.
     *
     * @param array $filters
     * @return array{0: string, 1: array} [$where_sql, $values]
     */
    private function build_where( array $filters ): array {
        $clauses = array();
        $values  = array();

        $string_filters = array(
            'severity'       => 'severity',
            'source_type'    => 'source_type',
            'event_type'     => 'event_type',
            'plugin_slug'    => 'plugin_slug',
            'request_context' => 'request_context',
        );
        foreach ( $string_filters as $param => $col ) {
            if ( ! empty( $filters[ $param ] ) ) {
                $clauses[] = "{$col} = %s";
                $values[]  = $filters[ $param ];
            }
        }

        if ( ! empty( $filters['user_id'] ) ) {
            $clauses[] = 'user_id = %d';
            $values[]  = (int) $filters['user_id'];
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $clauses[] = 'created_at_utc >= %s';
            $values[]  = $filters['date_from'] . ' 00:00:00';
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $clauses[] = 'created_at_utc <= %s';
            $values[]  = $filters['date_to'] . ' 23:59:59';
        }

        $where = empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses );
        return array( $where, $values );
    }

    /**
     * Return the UTC date of the oldest stored event, or null.
     */
    private function oldest_event_date(): ?string {
        global $wpdb;
        $table = $this->table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_var( "SELECT MIN(created_at_utc) FROM {$table}" ) ?: null;
    }
}
