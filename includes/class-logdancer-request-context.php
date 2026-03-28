<?php
/**
 * Log Dancer — Request Context.
 *
 * Builds a normalised snapshot of the current WordPress request for attaching
 * to log events.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Request_Context {

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

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
     * Return an array snapshot of the current request context.
     *
     * @return array{
     *   request_context: string,
     *   request_uri: string,
     *   user_id: int|null,
     *   user_roles: string,
     *   site_id: int,
     *   memory_usage: int,
     *   peak_memory_usage: int
     * }
     */
    public function build_context(): array {
        return array(
            'request_context'   => $this->detect_request_type(),
            'request_uri'       => $this->get_request_uri(),
            'user_id'           => $this->get_current_user_id(),
            'user_roles'        => $this->get_current_user_roles(),
            'site_id'           => get_current_blog_id(),
            'memory_usage'      => memory_get_usage( true ),
            'peak_memory_usage' => memory_get_peak_usage( true ),
        );
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Detect the type of the current WordPress request.
     *
     * @return string admin|ajax|rest|cron|cli|front|unknown
     */
    public function detect_request_type(): string {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return 'cli';
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return 'cron';
        }
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return 'ajax';
        }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return 'rest';
        }
        if ( is_admin() ) {
            return 'admin';
        }
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            return 'front';
        }
        return 'unknown';
    }

    /**
     * Return the current request URI, applying redaction policy.
     *
     * @return string
     */
    public function get_request_uri(): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $raw = $_SERVER['REQUEST_URI'] ?? '';
        if ( ! is_string( $raw ) ) {
            return '';
        }
        $uri = esc_url_raw( $raw );
        if ( $this->policy->should_redact_query_strings() ) {
            $path = wp_parse_url( $uri, PHP_URL_PATH );
            return is_string( $path ) ? $path : $uri;
        }
        return $uri;
    }

    /**
     * Return the current user ID (0 if not logged in).
     *
     * @return int|null
     */
    private function get_current_user_id(): ?int {
        if ( ! function_exists( 'get_current_user_id' ) ) {
            return null;
        }
        $uid = get_current_user_id();
        return $uid > 0 ? $uid : null;
    }

    /**
     * Return the current user's roles as a comma-separated string.
     *
     * @return string
     */
    private function get_current_user_roles(): string {
        if ( ! function_exists( 'wp_get_current_user' ) ) {
            return '';
        }
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return '';
        }
        return implode( ',', (array) $user->roles );
    }
}
