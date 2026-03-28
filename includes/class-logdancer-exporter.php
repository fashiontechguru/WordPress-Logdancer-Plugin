<?php
/**
 * Log Dancer — Exporter.
 *
 * Exports filtered event sets as CSV or JSON downloads.
 * All exports require manage_options capability and a valid nonce.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Exporter {

    /** @var LogDancer_DB_Storage */
    private LogDancer_DB_Storage $storage;

    /**
     * @param LogDancer_DB_Storage $storage
     */
    public function __construct( LogDancer_DB_Storage $storage ) {
        $this->storage = $storage;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Stream a CSV export as a download response.
     * Exits after sending headers and body.
     *
     * @param array $filters Same filters accepted by LogDancer_DB_Storage::query().
     */
    public function export_csv( array $filters = [] ): void {
        $this->check_export_permission();

        $rows = $this->storage->query( array_merge( $filters, array( 'per_page' => 10000, 'offset' => 0 ) ) );

        $filename = 'logdancer-events-' . gmdate( 'Y-m-d-His' ) . '.csv';
        $this->stream_headers( $filename, 'text/csv; charset=UTF-8' );

        $fh = fopen( 'php://output', 'wb' );
        if ( ! $fh ) {
            wp_die( esc_html__( 'Could not open output stream.', 'logdancer' ) );
        }

        // Header row.
        fputcsv( $fh, array(
            'event_uuid', 'created_at_utc', 'severity', 'source_type',
            'event_type', 'message', 'file_basename', 'line_number',
            'plugin_slug', 'theme_slug', 'request_context', 'user_id',
        ) );

        foreach ( $rows as $row ) {
            fputcsv( $fh, array(
                $this->csv_safe( $row->event_uuid ),
                $this->csv_safe( $row->created_at_utc ),
                $this->csv_safe( $row->severity ),
                $this->csv_safe( $row->source_type ),
                $this->csv_safe( $row->event_type ),
                $this->csv_safe( $row->message ),
                $this->csv_safe( $row->file_basename ),
                $this->csv_safe( $row->line_number ),
                $this->csv_safe( $row->plugin_slug ),
                $this->csv_safe( $row->theme_slug ),
                $this->csv_safe( $row->request_context ),
                $this->csv_safe( $row->user_id ),
            ) );
        }

        fclose( $fh );
        exit;
    }

    /**
     * Stream a JSON export as a download response.
     * Exits after sending headers and body.
     *
     * @param array $filters
     */
    public function export_json( array $filters = [] ): void {
        $this->check_export_permission();

        $rows = $this->storage->query( array_merge( $filters, array( 'per_page' => 10000, 'offset' => 0 ) ) );

        $filename = 'logdancer-events-' . gmdate( 'Y-m-d-His' ) . '.json';
        $this->stream_headers( $filename, 'application/json; charset=UTF-8' );

        // Decode extra_json fields for richer JSON output.
        $output = array();
        foreach ( $rows as $row ) {
            $item = (array) $row;
            if ( isset( $item['extra_json'] ) && is_string( $item['extra_json'] ) ) {
                $decoded = json_decode( $item['extra_json'], true );
                if ( is_array( $decoded ) ) {
                    $item['extra'] = $decoded;
                }
                unset( $item['extra_json'] );
            }
            $output[] = $item;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
        echo wp_json_encode( array(
            'exported_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'count'       => count( $output ),
            'events'      => $output,
        ), JSON_PRETTY_PRINT );

        exit;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Abort with a 403 if the current user lacks the required capability or nonce.
     */
    private function check_export_permission(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export Log Dancer data.', 'logdancer' ), 403 );
        }
        // Nonce must be verified by the caller (admin action handler) before reaching here.
    }

    /**
     * Send file download headers and disable output buffering.
     *
     * @param string $filename
     * @param string $content_type
     */
    private function stream_headers( string $filename, string $content_type ): void {
        // Flush any output buffers so nothing precedes the binary download.
        if ( ob_get_length() ) {
            ob_end_clean();
        }
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
    }

    /**
     * Protect a CSV cell against formula injection.
     *
     * Prefixes values starting with formula trigger characters with a tab.
     *
     * @param mixed $value
     * @return string
     */
    private function csv_safe( $value ): string {
        $v = (string) ( $value ?? '' );
        if ( $v !== '' && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
            $v = "\t" . $v;
        }
        return $v;
    }
}
