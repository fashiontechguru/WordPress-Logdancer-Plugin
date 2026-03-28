<?php
/**
 * Log Dancer — helper utilities.
 *
 * Small, reusable, stateless helpers only. No major logic here.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'logdancer_array_get' ) ) {
    /**
     * Safe array value getter with default.
     *
     * @param array  $array   Source array.
     * @param string $key     Key to retrieve.
     * @param mixed  $default Fallback value.
     * @return mixed
     */
    function logdancer_array_get( array $array, string $key, $default = null ) {
        return array_key_exists( $key, $array ) ? $array[ $key ] : $default;
    }
}

if ( ! function_exists( 'logdancer_generate_uuid4' ) ) {
    /**
     * Generate a random UUID v4 string.
     *
     * @return string e.g. "550e8400-e29b-41d4-a716-446655440000"
     */
    function logdancer_generate_uuid4(): string {
        $bytes = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );

        // Set version 4 bits.
        $bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
        // Set variant bits.
        $bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split( bin2hex( $bytes ), 4 )
        );
    }
}

if ( ! function_exists( 'logdancer_format_bytes' ) ) {
    /**
     * Format bytes as a human-readable string.
     *
     * @param int $bytes Raw byte count.
     * @return string e.g. "4.2 MB"
     */
    function logdancer_format_bytes( int $bytes ): string {
        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 1 ) . ' MB';
        }
        if ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 1 ) . ' KB';
        }
        return $bytes . ' B';
    }
}

if ( ! function_exists( 'logdancer_admin_notices' ) ) {
    /**
     * Output any admin notice passed in via query string.
     * Used by admin view templates.
     */
    function logdancer_admin_notices(): void {
        // phpcs:ignore WordPress.Security.NonceVerification
        if ( empty( $_GET['logdancer_notice'] ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification
        $type    = sanitize_key( $_GET['logdancer_type'] ?? 'success' );
        // phpcs:ignore WordPress.Security.NonceVerification
        $message = sanitize_text_field( wp_unslash( $_GET['logdancer_notice'] ) );
        $class   = $type === 'error' ? 'notice-error' : 'notice-success';
        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr( $class ),
            esc_html( $message )
        );
    }
}

if ( ! function_exists( 'logdancer_severity_badge_class' ) ) {
    /**
     * Return a CSS class suffix for a given severity level.
     *
     * @param string $severity One of: debug, info, notice, warning, error, critical.
     * @return string
     */
    function logdancer_severity_badge_class( string $severity ): string {
        $map = array(
            'debug'    => 'debug',
            'info'     => 'info',
            'notice'   => 'notice',
            'warning'  => 'warning',
            'error'    => 'error',
            'critical' => 'critical',
        );
        return $map[ $severity ] ?? 'info';
    }
}
