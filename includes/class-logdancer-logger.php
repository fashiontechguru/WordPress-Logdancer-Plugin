<?php
/**
 * Log Dancer — Logger.
 *
 * Central write pipeline. All event sources call log() here.
 * Normalises, validates, redacts, and persists events.
 * No event source should bypass this class.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

class LogDancer_Logger {

    /** @var LogDancer_DB_Storage */
    private LogDancer_DB_Storage $storage;

    /** @var LogDancer_Redactor */
    private LogDancer_Redactor $redactor;

    /** @var LogDancer_Policy */
    private LogDancer_Policy $policy;

    /** @var LogDancer_Request_Context */
    private LogDancer_Request_Context $context_builder;

    /** @var bool Guard against recursive logging. */
    private bool $writing = false;

    /** @var array Valid severity levels. */
    private const SEVERITY_LEVELS = array(
        'debug', 'info', 'notice', 'warning', 'error', 'critical',
    );

    /**
     * @param LogDancer_DB_Storage      $storage
     * @param LogDancer_Redactor        $redactor
     * @param LogDancer_Policy          $policy
     * @param LogDancer_Request_Context $context_builder
     */
    public function __construct(
        LogDancer_DB_Storage $storage,
        LogDancer_Redactor $redactor,
        LogDancer_Policy $policy,
        LogDancer_Request_Context $context_builder
    ) {
        $this->storage         = $storage;
        $this->redactor        = $redactor;
        $this->policy          = $policy;
        $this->context_builder = $context_builder;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Log an event.
     *
     * @param array $event Raw event data. Must include at minimum:
     *   - source_type (string)
     *   - event_type  (string)
     *   - severity    (string)
     *   - message     (string)
     * @return int|false Inserted row ID or false on failure.
     */
    public function log( array $event ) {
        // Guard against recursion.
        if ( $this->writing ) {
            return false;
        }

        // Policy gate.
        $source = $event['source_type'] ?? 'system';
        if ( ! $this->policy->allow_source_type( $source ) ) {
            return false;
        }

        $this->writing = true;

        try {
            $event     = $this->normalize( $event );
            $validated = $this->validate( $event );
            if ( ! $validated ) {
                $this->writing = false;
                return false;
            }
            $event  = $this->redactor->redact_event( $event );
            $result = $this->storage->write( $event );
        } catch ( \Throwable $e ) {
            // Swallow — logger must not throw.
            $result = false;
        }

        $this->writing = false;
        return $result;
    }

    /**
     * Convenience method to build a partial event array for common cases.
     *
     * @param string $source_type
     * @param string $event_type
     * @param string $severity
     * @param string $message
     * @param array  $extra       Additional fields to merge in.
     * @return array
     */
    public function build_event(
        string $source_type,
        string $event_type,
        string $severity,
        string $message,
        array $extra = []
    ): array {
        return array_merge(
            array(
                'source_type' => $source_type,
                'event_type'  => $event_type,
                'severity'    => $severity,
                'message'     => $message,
            ),
            $extra
        );
    }

    // -----------------------------------------------------------------------
    // Normalization & Validation
    // -----------------------------------------------------------------------

    /**
     * Normalize a raw event array, filling required fields from context.
     *
     * @param array $event
     * @return array
     */
    public function normalize( array $event ): array {
        $ctx = $this->context_builder->build_context();

        $defaults = array(
            'event_uuid'       => logdancer_generate_uuid4(),
            'created_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
            'source_type'      => 'system',
            'event_type'       => 'generic',
            'severity'         => 'info',
            'message'          => '',
            'file_path'        => null,
            'file_basename'    => null,
            'line_number'      => null,
            'plugin_slug'      => null,
            'theme_slug'       => null,
            'request_context'  => $ctx['request_context'],
            'request_uri'      => $ctx['request_uri'],
            'user_id'          => $ctx['user_id'],
            'user_roles'       => $ctx['user_roles'],
            'site_id'          => $ctx['site_id'],
            'memory_usage'     => $ctx['memory_usage'],
            'peak_memory_usage' => $ctx['peak_memory_usage'],
            'event_hash'       => null,
            'extra_json'       => null,
        );

        $merged = array_merge( $defaults, $event );

        // Normalise severity.
        if ( ! in_array( $merged['severity'], self::SEVERITY_LEVELS, true ) ) {
            $merged['severity'] = 'info';
        }

        // Auto-generate event hash from stable fields if not provided.
        if ( null === $merged['event_hash'] ) {
            $sig = implode( '|', array(
                $merged['source_type'],
                $merged['event_type'],
                $merged['severity'],
                $merged['plugin_slug'] ?? '',
                $merged['theme_slug']  ?? '',
                $merged['request_context'] ?? '',
            ) );
            $merged['event_hash'] = hash( 'sha256', $sig );
        }

        // Encode extra_json if array was passed.
        if ( is_array( $merged['extra_json'] ) ) {
            $merged['extra_json'] = wp_json_encode( $merged['extra_json'] );
        }

        // Derive file_basename from file_path if missing.
        if ( empty( $merged['file_basename'] ) && ! empty( $merged['file_path'] ) ) {
            $merged['file_basename'] = basename( $merged['file_path'] );
        }

        return $merged;
    }

    /**
     * Validate that required fields are present and non-empty.
     *
     * @param array $event
     * @return bool
     */
    public function validate( array $event ): bool {
        $required = array( 'source_type', 'event_type', 'severity', 'message', 'created_at_utc' );
        foreach ( $required as $key ) {
            if ( empty( $event[ $key ] ) && $event[ $key ] !== '0' ) {
                return false;
            }
        }
        return true;
    }
}
