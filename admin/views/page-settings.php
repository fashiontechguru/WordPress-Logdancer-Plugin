<?php
/**
 * Log Dancer — Settings page template.
 *
 * Variables provided by LogDancer_Admin::render_settings_page():
 *   $current_settings  array  Merged current settings.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper: render a checkbox setting row.
 *
 * @param string $key     Settings key.
 * @param string $label   Label text.
 * @param string $description Description text.
 * @param array  $settings Current settings.
 */
function logdancer_setting_checkbox( string $key, string $label, string $description, array $settings ): void {
    $checked = ! empty( $settings[ $key ] );
    $id      = 'logdancer_' . $key;
    printf(
        '<tr><th scope="row"><label for="%s">%s</label></th><td><input type="checkbox" id="%s" name="%s[%s]" value="1" %s><p class="description">%s</p></td></tr>',
        esc_attr( $id ),
        esc_html( $label ),
        esc_attr( $id ),
        esc_attr( LogDancer_Settings::OPTION_KEY ),
        esc_attr( $key ),
        checked( $checked, true, false ),
        esc_html( $description )
    );
}
?>
<div class="wrap logdancer-wrap">

    <h1 class="logdancer-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e( 'Log Dancer — Settings', 'logdancer' ); ?>
    </h1>

    <?php settings_errors( 'logdancer_settings_group' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'logdancer_settings_group' ); ?>

        <!-- ================================================================
             General
             ================================================================ -->
        <h2><?php esc_html_e( 'General', 'logdancer' ); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <?php logdancer_setting_checkbox( 'enabled', __( 'Enable Log Dancer', 'logdancer' ), __( 'Uncheck to disable all event capture without deactivating the plugin.', 'logdancer' ), $current_settings ); ?>
            </tbody>
        </table>

        <!-- ================================================================
             Capture Settings
             ================================================================ -->
        <h2><?php esc_html_e( 'Capture Settings', 'logdancer' ); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <?php
                logdancer_setting_checkbox( 'capture_fatals',             __( 'Fatal errors', 'logdancer' ),          __( 'Capture fatal PHP errors detected at shutdown.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'capture_plugin_events',       __( 'Plugin activation / deactivation', 'logdancer' ), __( 'Log when plugins are activated or deactivated.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'capture_theme_events',        __( 'Theme switches', 'logdancer' ),        __( 'Log when the active theme is changed.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'capture_updater_events',      __( 'Plugin / theme updates', 'logdancer' ), __( 'Log when updates complete via the WordPress upgrader.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'capture_http_api_failures',   __( 'HTTP API failures', 'logdancer' ),     __( 'Log outbound HTTP requests that fail or return 4xx/5xx.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'capture_cron_anomalies',      __( 'Cron anomalies', 'logdancer' ),        __( 'Log when WP-Cron lock appears stale.', 'logdancer' ), $current_settings );
                ?>
            </tbody>
        </table>

        <!-- ================================================================
             Advanced Capture
             ================================================================ -->
        <h2><?php esc_html_e( 'Advanced Capture', 'logdancer' ); ?></h2>
        <div class="logdancer-alert logdancer-alert--warning">
            <?php esc_html_e( 'Advanced PHP warning capture replaces the global PHP error handler. This may interfere with other plugins. Enable only for active debugging.', 'logdancer' ); ?>
        </div>
        <table class="form-table" role="presentation">
            <tbody>
                <?php logdancer_setting_checkbox( 'capture_php_warnings_advanced', __( 'PHP warnings / notices (advanced)', 'logdancer' ), __( 'Intercept PHP warnings and notices via set_error_handler(). Not recommended for production.', 'logdancer' ), $current_settings ); ?>
            </tbody>
        </table>

        <!-- ================================================================
             Privacy
             ================================================================ -->
        <h2><?php esc_html_e( 'Privacy', 'logdancer' ); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <?php
                logdancer_setting_checkbox( 'redact_paths',           __( 'Redact file paths', 'logdancer' ),       __( 'Store only file basename, not full server path.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'redact_query_strings',   __( 'Redact query strings', 'logdancer' ),    __( 'Strip query parameters from stored request URIs.', 'logdancer' ), $current_settings );
                logdancer_setting_checkbox( 'anonymize_ip',           __( 'Anonymize IP addresses', 'logdancer' ),  __( 'Mask the last octet of stored IP addresses.', 'logdancer' ), $current_settings );
                ?>
                <tr>
                    <th scope="row">
                        <label for="logdancer_privacy_mode"><?php esc_html_e( 'Privacy mode', 'logdancer' ); ?></label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr( LogDancer_Settings::OPTION_KEY ); ?>[privacy_mode]" id="logdancer_privacy_mode">
                            <?php foreach ( array( 'strict' => __( 'Strict', 'logdancer' ), 'balanced' => __( 'Balanced (recommended)', 'logdancer' ), 'verbose' => __( 'Verbose', 'logdancer' ) ) as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_settings['privacy_mode'] ?? 'balanced', $val ); ?>>
                                    <?php echo esc_html( $lbl ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Strict: minimum data stored. Verbose: full context including query strings and file paths.', 'logdancer' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ================================================================
             Retention
             ================================================================ -->
        <h2><?php esc_html_e( 'Retention', 'logdancer' ); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="logdancer_retention_days"><?php esc_html_e( 'Retain events for (days)', 'logdancer' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="logdancer_retention_days"
                            name="<?php echo esc_attr( LogDancer_Settings::OPTION_KEY ); ?>[retention_days]"
                            value="<?php echo esc_attr( $current_settings['retention_days'] ?? 30 ); ?>"
                            min="1" max="365" class="small-text">
                        <p class="description"><?php esc_html_e( 'Events older than this will be deleted by the daily cleanup job. Range: 1–365.', 'logdancer' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="logdancer_max_events"><?php esc_html_e( 'Maximum stored events', 'logdancer' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="logdancer_max_events"
                            name="<?php echo esc_attr( LogDancer_Settings::OPTION_KEY ); ?>[max_events]"
                            value="<?php echo esc_attr( $current_settings['max_events'] ?? 10000 ); ?>"
                            min="100" max="100000" class="small-text">
                        <p class="description"><?php esc_html_e( 'Upper row limit. Events beyond this limit may be trimmed during cleanup.', 'logdancer' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ================================================================
             Uninstall
             ================================================================ -->
        <h2><?php esc_html_e( 'Uninstall Behaviour', 'logdancer' ); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <?php logdancer_setting_checkbox( 'full_cleanup_on_uninstall', __( 'Delete all data on uninstall', 'logdancer' ), __( 'If checked, the events table will be dropped when the plugin is deleted. Logs and settings will be permanently removed.', 'logdancer' ), $current_settings ); ?>
            </tbody>
        </table>

        <?php submit_button(); ?>

    </form>

</div>
