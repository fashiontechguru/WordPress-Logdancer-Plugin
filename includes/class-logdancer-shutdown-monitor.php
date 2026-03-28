<?php
/**
 * Log Dancer — Shutdown Monitor.
 *
 * Registers a PHP shutdown function. On shutdown, calls error_get_last()
 * and writes a critical event if a fatal-like error occurred.
 *
 * Constraints:
 * - Must assume WordPress may be in a degraded state.
 * - Must not call complex WP functions.
 * - Must prevent recursive failure.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Shutdown_Monitor {

    /** @var LogDancer_Logger */
    private LogDancer_Logger $logger;

    /** @var bool Prevent double-registration. */
    private bool $registered = false;

    /**
     * @param LogDancer_Logger $logger
     */
    public function __construct( LogDancer_Logger $logger ) {
        $this->logger = $logger;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Register the PHP shutdown function.
     * Safe to call multiple times — only registers once.
     */
    public function register(): void {
        if ( $this->registered ) {
            return;
        }
        $this->registered = true;
        register_shutdown_function( array( $this, 'handle_shutdown' ) );
    }

    /**
     * Shutdown handler. Called by PHP at end of request.
     */
    public function handle_shutdown(): void {
        $error = error_get_last();
        if ( null === $error ) {
            return;
        }
        if ( ! $this->is_fatal_error( $error ) ) {
            return;
        }
        try {
            $event = $this->build_fatal_event( $error );
            $this->logger->log( $event );
        } catch ( \Throwable $e ) {
            // Absolute last resort: swallow and do nothing to prevent output corruption.
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Return true if the error type is fatal or fatal-like.
     *
     * @param array $error From error_get_last().
     * @return bool
     */
    public function is_fatal_error( array $error ): bool {
        $fatal_types = array(
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR,
        );
        return in_array( $error['type'] ?? 0, $fatal_types, true );
    }

    /**
     * Build the event array for a fatal error.
     *
     * Deliberately minimal — avoids complex calls in degraded state.
     *
     * @param array $error From error_get_last().
     * @return array
     */
    public function build_fatal_event( array $error ): array {
        $type_label = $this->error_type_label( $error['type'] ?? 0 );
        $message    = sprintf(
            '[%s] %s in %s on line %d',
            $type_label,
            $error['message'] ?? 'Unknown error',
            basename( $error['file'] ?? '' ),
            (int) ( $error['line'] ?? 0 )
        );

        return array(
            'source_type'    => 'fatal',
            'event_type'     => 'fatal_error',
            'severity'       => 'critical',
            'message'        => $message,
            'file_path'      => $error['file'] ?? null,
            'file_basename'  => basename( $error['file'] ?? '' ),
            'line_number'    => (int) ( $error['line'] ?? 0 ),
            'memory_usage'   => memory_get_usage( true ),
            'peak_memory_usage' => memory_get_peak_usage( true ),
            'extra_json'     => wp_json_encode( array(
                'php_error_type'  => $error['type'] ?? null,
                'php_error_label' => $type_label,
            ) ),
        );
    }

    /**
     * Return a human-readable label for a PHP error type integer.
     *
     * @param int $type
     * @return string
     */
    private function error_type_label( int $type ): string {
        $labels = array(
            E_ERROR           => 'E_ERROR',
            E_PARSE           => 'E_PARSE',
            E_CORE_ERROR      => 'E_CORE_ERROR',
            E_COMPILE_ERROR   => 'E_COMPILE_ERROR',
            E_USER_ERROR      => 'E_USER_ERROR',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        );
        return $labels[ $type ] ?? "PHP_ERROR({$type})";
    }
}
