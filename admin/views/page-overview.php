<?php
/**
 * Log Dancer — Overview page template.
 *
 * Variables provided by LogDancer_Admin::render_overview_page():
 *   $summary   array  Severity counts for last 24h.
 *   $critical  int    Critical events in last 7 days.
 *   $last_fatal object|null  Most recent fatal event row.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap logdancer-wrap">

    <h1 class="logdancer-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'Log Dancer — Overview', 'logdancer' ); ?>
    </h1>

    <?php logdancer_admin_notices(); ?>

    <div class="logdancer-stats-grid">

        <?php
        $stat_rows = array(
            array(
                'label' => __( 'Events (24h)', 'logdancer' ),
                'value' => array_sum( $summary ),
                'color' => 'neutral',
            ),
            array(
                'label' => __( 'Errors (24h)', 'logdancer' ),
                'value' => ( $summary['error'] ?? 0 ) + ( $summary['critical'] ?? 0 ),
                'color' => ( ( $summary['error'] ?? 0 ) + ( $summary['critical'] ?? 0 ) ) > 0 ? 'danger' : 'ok',
            ),
            array(
                'label' => __( 'Warnings (24h)', 'logdancer' ),
                'value' => $summary['warning'] ?? 0,
                'color' => ( $summary['warning'] ?? 0 ) > 0 ? 'warn' : 'ok',
            ),
            array(
                'label' => __( 'Critical (7d)', 'logdancer' ),
                'value' => $critical,
                'color' => $critical > 0 ? 'danger' : 'ok',
            ),
        );
        foreach ( $stat_rows as $stat ) :
        ?>
            <div class="logdancer-stat-card logdancer-stat--<?php echo esc_attr( $stat['color'] ); ?>">
                <div class="logdancer-stat-value"><?php echo esc_html( $stat['value'] ); ?></div>
                <div class="logdancer-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
            </div>
        <?php endforeach; ?>

    </div>

    <?php if ( $last_fatal ) : ?>
        <div class="logdancer-alert logdancer-alert--critical">
            <strong><?php esc_html_e( 'Last fatal event:', 'logdancer' ); ?></strong>
            <?php echo esc_html( $last_fatal->created_at_utc ); ?> —
            <?php echo esc_html( mb_substr( $last_fatal->message, 0, 160 ) ); ?>
        </div>
    <?php endif; ?>

    <h2><?php esc_html_e( 'Severity Breakdown (last 24 hours)', 'logdancer' ); ?></h2>
    <table class="widefat logdancer-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Severity', 'logdancer' ); ?></th>
                <th><?php esc_html_e( 'Count', 'logdancer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( array( 'critical', 'error', 'warning', 'notice', 'info', 'debug' ) as $sev ) : ?>
                <tr>
                    <td>
                        <span class="logdancer-badge logdancer-badge--<?php echo esc_attr( logdancer_severity_badge_class( $sev ) ); ?>">
                            <?php echo esc_html( ucfirst( $sev ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $summary[ $sev ] ?? 0 ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="logdancer-nav-links">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=logdancer-events' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'View All Events →', 'logdancer' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=logdancer-health' ) ); ?>" class="button">
            <?php esc_html_e( 'Health Check', 'logdancer' ); ?>
        </a>
    </p>

</div>
