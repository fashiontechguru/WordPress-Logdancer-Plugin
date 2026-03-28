<?php
/**
 * Log Dancer — Events page template.
 *
 * Variables provided by LogDancer_Admin::render_events_page():
 *   $table  LogDancer_Admin_Table  Prepared table instance.
 *
 * @package LogDancer
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap logdancer-wrap">

    <h1 class="logdancer-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'Log Dancer — Events', 'logdancer' ); ?>
    </h1>

    <form method="get" action="">
        <input type="hidden" name="page" value="logdancer-events">
        <?php
        $table->search_box( __( 'Search', 'logdancer' ), 'logdancer-search' );
        $table->display();
        ?>
    </form>

    <p class="logdancer-nav-links">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=logdancer-export' ) ); ?>" class="button">
            <?php esc_html_e( 'Export Events', 'logdancer' ); ?>
        </a>
    </p>

</div>
