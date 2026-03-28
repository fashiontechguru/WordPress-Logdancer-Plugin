<?php
/**
 * Log Dancer — Export page template.
 *
 * Variables provided by LogDancer_Admin::render_export_page():
 *   $total  int  Total events currently stored.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap logdancer-wrap">

    <h1 class="logdancer-page-title">
        <span class="dashicons dashicons-download"></span>
        <?php esc_html_e( 'Log Dancer — Export', 'logdancer' ); ?>
    </h1>

    <?php
    // phpcs:ignore WordPress.Security.NonceVerification
    if ( ! empty( $_GET['logdancer_notice'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification
        $type    = sanitize_key( $_GET['logdancer_type'] ?? 'success' );
        // phpcs:ignore WordPress.Security.NonceVerification
        $message = sanitize_text_field( wp_unslash( $_GET['logdancer_notice'] ) );
        $class   = $type === 'error' ? 'notice-error' : 'notice-success';
        printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
    ?>

    <p>
        <?php
        printf(
            /* translators: %d: number of stored events */
            esc_html( _n( 'There is %d event currently stored.', 'There are %d events currently stored.', $total, 'logdancer' ) ),
            esc_html( number_format_i18n( $total ) )
        );
        ?>
    </p>

    <!-- ====================================================================
         Export section
         ==================================================================== -->
    <h2><?php esc_html_e( 'Download Events', 'logdancer' ); ?></h2>
    <p class="description">
        <?php esc_html_e( 'Exports are limited to the most recent 10,000 events. You can optionally filter before exporting.', 'logdancer' ); ?>
    </p>

    <form method="post" action="">
        <?php wp_nonce_field( 'logdancer_export' ); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="export_severity"><?php esc_html_e( 'Filter by severity', 'logdancer' ); ?></label></th>
                    <td>
                        <select name="severity" id="export_severity">
                            <option value=""><?php esc_html_e( 'All', 'logdancer' ); ?></option>
                            <?php foreach ( array( 'debug', 'info', 'notice', 'warning', 'error', 'critical' ) as $sev ) : ?>
                                <option value="<?php echo esc_attr( $sev ); ?>"><?php echo esc_html( ucfirst( $sev ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="export_source_type"><?php esc_html_e( 'Filter by source', 'logdancer' ); ?></label></th>
                    <td>
                        <select name="source_type" id="export_source_type">
                            <option value=""><?php esc_html_e( 'All', 'logdancer' ); ?></option>
                            <?php foreach ( array( 'plugin', 'theme', 'updater', 'fatal', 'http_api', 'cron', 'database', 'system' ) as $src ) : ?>
                                <option value="<?php echo esc_attr( $src ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $src ) ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Date range', 'logdancer' ); ?></th>
                    <td>
                        <label>
                            <?php esc_html_e( 'From:', 'logdancer' ); ?>
                            <input type="date" name="date_from" value="">
                        </label>
                        &nbsp;
                        <label>
                            <?php esc_html_e( 'To:', 'logdancer' ); ?>
                            <input type="date" name="date_to" value="">
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <p>
            <button type="submit" name="logdancer_action" value="export_csv" class="button button-primary">
                <?php esc_html_e( 'Download CSV', 'logdancer' ); ?>
            </button>
            &nbsp;
            <button type="submit" name="logdancer_action" value="export_json" class="button">
                <?php esc_html_e( 'Download JSON', 'logdancer' ); ?>
            </button>
        </p>
    </form>

    <!-- ====================================================================
         Clear section
         ==================================================================== -->
    <hr>
    <h2><?php esc_html_e( 'Clear Events', 'logdancer' ); ?></h2>
    <p class="description logdancer-danger-text">
        <?php esc_html_e( 'This permanently deletes all stored events. This action cannot be undone.', 'logdancer' ); ?>
    </p>

    <form method="post" action=""
          onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure? All events will be permanently deleted.', 'logdancer' ) ); ?>')">
        <?php wp_nonce_field( 'logdancer_clear_events' ); ?>
        <input type="hidden" name="logdancer_action" value="clear_events">
        <?php submit_button( __( 'Clear All Events', 'logdancer' ), 'delete', 'submit', false ); ?>
    </form>

</div>
