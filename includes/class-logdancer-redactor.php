<?php
/**
 * Log Dancer — Redactor.
 *
 * Sanitizes and redacts event data before it is persisted.
 * Called by the logger for every event.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Redactor {

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

    /**
     * Sensitive key patterns to mask in extra_json / messages.
     */
    private const SENSITIVE_KEYS = array(
        'password', 'passwd', 'pass', 'secret', 'token', 'api_key',
        'apikey', 'access_key', 'auth', 'authorization', 'cookie',
        'nonce', 'key', 'private',
    );

    /**
     * @param LogDancer_Policy $policy
     */
    public function __construct( LogDancer_Policy $policy ) {
        $this->policy = $policy;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Redact a full event array before storage.
     *
     * @param array $event
     * @return array
     */
    public function redact_event( array $event ): array {
        if ( isset( $event['message'] ) ) {
            $event['message'] = $this->redact_message( (string) $event['message'] );
        }

        if ( isset( $event['file_path'] ) && $this->policy->should_redact_paths() ) {
            $event['file_basename'] = basename( (string) $event['file_path'] );
            $event['file_path']     = null; // drop full path
        }

        if ( isset( $event['request_uri'] ) ) {
            $event['request_uri'] = $this->redact_request_uri( (string) $event['request_uri'] );
        }

        if ( isset( $event['extra_json'] ) && is_string( $event['extra_json'] ) ) {
            $decoded = json_decode( $event['extra_json'], true );
            if ( is_array( $decoded ) ) {
                $decoded = $this->redact_array_recursive( $decoded );
                $event['extra_json'] = wp_json_encode( $decoded );
            }
        }

        return $event;
    }

    /**
     * Redact a plain text message.
     *
     * Strips patterns that look like secrets from free-text strings.
     *
     * @param string $message
     * @return string
     */
    public function redact_message( string $message ): string {
        // Mask anything that looks like a bearer token or API key inline.
        $message = preg_replace(
            '/Bearer\s+[A-Za-z0-9\-_\.]{8,}/i',
            'Bearer [REDACTED]',
            $message
        );
        $message = preg_replace(
            '/\b(api[_\-]?key|token|secret|password)\s*[:=]\s*\S+/i',
            '$1=[REDACTED]',
            $message
        );
        return $message;
    }

    /**
     * Redact a file path (returns basename or null based on policy).
     *
     * @param string $path
     * @return string|null
     */
    public function redact_path( string $path ): ?string {
        if ( $this->policy->should_redact_paths() ) {
            return null;
        }
        return $path;
    }

    /**
     * Redact a request URI.
     *
     * In balanced/strict mode the query string is stripped.
     * In strict mode only the path is retained.
     *
     * @param string $uri
     * @return string
     */
    public function redact_request_uri( string $uri ): string {
        if ( empty( $uri ) ) {
            return '';
        }
        $parsed = wp_parse_url( $uri );
        $path   = $parsed['path'] ?? '/';

        if ( $this->policy->should_redact_query_strings() ) {
            return $path; // strip query string entirely
        }

        // If verbose: include query but mask known-sensitive param values.
        $query = $parsed['query'] ?? '';
        if ( $query ) {
            parse_str( $query, $params );
            foreach ( $params as $key => $value ) {
                foreach ( self::SENSITIVE_KEYS as $sensitive ) {
                    if ( false !== stripos( $key, $sensitive ) ) {
                        $params[ $key ] = '[REDACTED]';
                    }
                }
            }
            return $path . '?' . http_build_query( $params );
        }

        return $path;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Recursively redact sensitive keys in an associative array.
     *
     * @param array $data
     * @return array
     */
    private function redact_array_recursive( array $data ): array {
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $data[ $key ] = $this->redact_array_recursive( $value );
            } elseif ( is_string( $value ) ) {
                foreach ( self::SENSITIVE_KEYS as $sensitive ) {
                    if ( false !== stripos( (string) $key, $sensitive ) ) {
                        $data[ $key ] = '[REDACTED]';
                        break;
                    }
                }
            }
        }
        return $data;
    }
}
