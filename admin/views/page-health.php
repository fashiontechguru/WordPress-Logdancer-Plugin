<?php
/**
 * Log Dancer — Health page template.
 *
 * Variables provided by LogDancer_Admin::render_health_page():
 *   $checks  array  Results from LogDancer_Health_Check::run_all().
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap logdancer-wrap">

    <h1 class="logdancer-page-title">
        <span class="dashicons dashicons-heart"></span>
        <?php esc_html_e( 'Log Dancer — Health', 'logdancer' ); ?>
    </h1>

    <table class="widefat logdancer-table logdancer-health-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Check', 'logdancer' ); ?></th>
                <th><?php esc_html_e( 'Status', 'logdancer' ); ?></th>
                <th><?php esc_html_e( 'Detail', 'logdancer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $checks as $check ) :
                $status = esc_attr( $check['status'] ?? 'ok' );
                $icon = array(
                    'ok'      => '<span class="dashicons dashicons-yes-alt logdancer-check--ok"></span>',
                    'warning' => '<span class="dashicons dashicons-warning logdancer-check--warning"></span>',
                    'error'   => '<span class="dashicons dashicons-dismiss logdancer-check--error"></span>',
                )[ $check['status'] ] ?? '';
            ?>
                <tr class="logdancer-health-row logdancer-health-row--<?php echo esc_attr( $status ); ?>">
                    <td><?php echo esc_html( $check['label'] ?? '' ); ?></td>
                    <td><?php echo wp_kses_post( $icon ); ?> <?php echo esc_html( ucfirst( $check['status'] ?? 'ok' ) ); ?></td>
                    <td><?php echo esc_html( $check['message'] ?? '' ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="logdancer-nav-links">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=logdancer' ) ); ?>" class="button">
            &larr; <?php esc_html_e( 'Overview', 'logdancer' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=logdancer-settings' ) ); ?>" class="button">
            <?php esc_html_e( 'Settings', 'logdancer' ); ?>
        </a>
    </p>

</div>
