<?php
/**
 * Admin Dashboard Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <div class="bwg-igf-logo">
            <span class="bwg-igf-logo-icon dashicons dashicons-instagram"></span>
        </div>
        <div class="bwg-igf-branding">
            <h1><?php esc_html_e( 'BWG Instagram Feed', 'bwg-instagram-feed' ); ?></h1>
            <span class="bwg-igf-brand-tagline"><?php esc_html_e( 'by Boston Web Group', 'bwg-instagram-feed' ); ?></span>
            <span class="bwg-igf-version"><?php /* translators: %s: plugin version number */ printf( esc_html__( 'Version %s', 'bwg-instagram-feed' ), esc_html( BWG_IGF_VERSION ) ); ?></span>
        </div>
    </div>

    <div class="bwg-igf-dashboard-widgets">
        <!-- Quick Stats -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Quick Stats', 'bwg-instagram-feed' ); ?></h2>
            <?php
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple count query, caching not needed for admin stats
            $feeds_count = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i',
                    $wpdb->prefix . 'bwg_igf_feeds'
                )
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple count query, caching not needed for admin stats
            $accounts_count = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s',
                    $wpdb->prefix . 'bwg_igf_accounts',
                    'active'
                )
            );

            // Get the most recent cache entry (last sync time).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard display, caching not needed
            $last_sync = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT MAX(created_at) FROM %i',
                    $wpdb->prefix . 'bwg_igf_cache'
                )
            );
            ?>
            <p><strong><?php esc_html_e( 'Total Feeds:', 'bwg-instagram-feed' ); ?></strong> <?php echo esc_html( $feeds_count ); ?></p>
            <p><strong><?php esc_html_e( 'Connected Accounts:', 'bwg-instagram-feed' ); ?></strong> <?php echo esc_html( $accounts_count ); ?></p>
            <p>
                <strong><?php esc_html_e( 'Last Sync:', 'bwg-instagram-feed' ); ?></strong>
                <?php
                if ( $last_sync ) {
                    $last_sync_time = strtotime( $last_sync );
                    $formatted_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_time );
                    $human_diff     = human_time_diff( $last_sync_time, current_time( 'timestamp' ) );
                    /* translators: %s: human-readable time difference */
                    printf( esc_html__( '%1$s (%2$s ago)', 'bwg-instagram-feed' ), esc_html( $formatted_time ), esc_html( $human_diff ) );
                } else {
                    esc_html_e( 'Never', 'bwg-instagram-feed' );
                }
                ?>
            </p>

            <!-- Rate Limit Status -->
            <?php
            // Get rate limit status for connected accounts.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard display, caching not needed
            $connected_accounts = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, username FROM %i WHERE status = %s',
                    $wpdb->prefix . 'bwg_igf_accounts',
                    'active'
                )
            );

            $rate_limit_status = 'ok'; // Default status.
            $rate_limit_message = '';
            $any_limited = false;
            $approaching_limit = false;
            $api_quota_info = array(); // Feature #29: Store quota info per account.
            $default_rate_limit = 200; // Instagram default hourly rate limit.

            if ( ! empty( $connected_accounts ) && class_exists( 'BWG_IGF_API_Tracker' ) ) {
                foreach ( $connected_accounts as $account ) {
                    $status = BWG_IGF_API_Tracker::get_rate_limit_status( $account->id );

                    // Feature #29: Collect quota info for each account.
                    if ( null !== $status['remaining'] ) {
                        $api_quota_info[] = array(
                            'username'  => $account->username,
                            'remaining' => $status['remaining'],
                            'total'     => $default_rate_limit,
                            'last_call' => $status['last_call'],
                        );
                    }

                    // Check if rate limited.
                    if ( $status['is_limited'] ) {
                        $any_limited = true;
                        $rate_limit_status = 'limited';
                        /* translators: %s: Instagram username */
                        $rate_limit_message = sprintf( __( 'Account @%s is currently rate limited by Instagram.', 'bwg-instagram-feed' ), $account->username );
                        break;
                    }

                    // Check if approaching limits (80% or more of quota used, if we have that info).
                    if ( null !== $status['remaining'] && $status['remaining'] <= 40 ) {
                        $approaching_limit = true;
                        $rate_limit_status = 'warning';
                        /* translators: 1: Instagram username, 2: remaining API calls */
                        $rate_limit_message = sprintf( __( 'Account @%1$s is approaching rate limits (%2$s calls remaining).', 'bwg-instagram-feed' ), $account->username, $status['remaining'] );
                    }
                }
            }
            ?>
            <p>
                <strong><?php esc_html_e( 'API Status:', 'bwg-instagram-feed' ); ?></strong>
                <?php if ( 'ok' === $rate_limit_status ) : ?>
                    <span class="bwg-igf-status bwg-igf-status-active">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-right: 3px;"></span>
                        <?php esc_html_e( 'OK', 'bwg-instagram-feed' ); ?>
                    </span>
                <?php elseif ( 'warning' === $rate_limit_status ) : ?>
                    <span class="bwg-igf-status bwg-igf-status-error">
                        <span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-right: 3px;"></span>
                        <?php esc_html_e( 'Approaching Limits', 'bwg-instagram-feed' ); ?>
                    </span>
                    <?php if ( $rate_limit_message ) : ?>
                        <br><small style="color: #856404; margin-top: 5px; display: inline-block;"><?php echo esc_html( $rate_limit_message ); ?></small>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="bwg-igf-status bwg-igf-status-inactive">
                        <span class="dashicons dashicons-dismiss" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-right: 3px;"></span>
                        <?php esc_html_e( 'Rate Limited', 'bwg-instagram-feed' ); ?>
                    </span>
                    <?php if ( $rate_limit_message ) : ?>
                        <br><small style="color: #721c24; margin-top: 5px; display: inline-block;"><?php echo esc_html( $rate_limit_message ); ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </p>

            <!-- Feature #29: Display remaining API quota when available -->
            <?php if ( ! empty( $api_quota_info ) ) : ?>
            <p>
                <strong><?php esc_html_e( 'API Quota:', 'bwg-instagram-feed' ); ?></strong>
                <?php
                foreach ( $api_quota_info as $quota ) :
                    $percentage_used = ( ( $quota['total'] - $quota['remaining'] ) / $quota['total'] ) * 100;
                    $bar_color = '#46b450'; // Green.
                    if ( $percentage_used >= 80 ) {
                        $bar_color = '#dc3232'; // Red.
                    } elseif ( $percentage_used >= 60 ) {
                        $bar_color = '#dba617'; // Yellow.
                    }
                    ?>
                    <span class="bwg-igf-quota-display" style="display: block; margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; border-left: 3px solid <?php echo esc_attr( $bar_color ); ?>;">
                        <span style="font-weight: 500;">@<?php echo esc_html( $quota['username'] ); ?>:</span>
                        <span style="color: <?php echo esc_attr( $bar_color ); ?>; font-weight: bold;">
                            <?php
                            /* translators: 1: remaining API calls, 2: total API calls */
                            printf(
                                esc_html__( '%1$d/%2$d calls remaining', 'bwg-instagram-feed' ),
                                intval( $quota['remaining'] ),
                                intval( $quota['total'] )
                            );
                            ?>
                        </span>
                        <?php if ( $quota['last_call'] ) : ?>
                            <br>
                            <small style="color: #666;">
                                <?php
                                $last_call_time = strtotime( $quota['last_call'] );
                                $human_diff = human_time_diff( $last_call_time, current_time( 'timestamp' ) );
                                /* translators: %s: human-readable time difference */
                                printf( esc_html__( 'Last API call: %s ago', 'bwg-instagram-feed' ), esc_html( $human_diff ) );
                                ?>
                            </small>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
        </div>

        <!-- Getting Started -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Getting Started', 'bwg-instagram-feed' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Create a new feed by clicking "Add New Feed"', 'bwg-instagram-feed' ); ?></li>
                <li><?php esc_html_e( 'Enter an Instagram username for public feeds, or connect your account', 'bwg-instagram-feed' ); ?></li>
                <li><?php esc_html_e( 'Customize the layout and styling options', 'bwg-instagram-feed' ); ?></li>
                <li><?php esc_html_e( 'Copy the shortcode and add it to any page or post', 'bwg-instagram-feed' ); ?></li>
            </ol>
        </div>

        <!-- Quick Links -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Quick Links', 'bwg-instagram-feed' ); ?></h2>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=new' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Create New Feed', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds' ) ); ?>" class="button">
                    <?php esc_html_e( 'View All Feeds', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-settings' ) ); ?>" class="button">
                    <?php esc_html_e( 'Settings', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
        </div>

        <!-- Support -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Need Help?', 'bwg-instagram-feed' ); ?></h2>
            <p><?php esc_html_e( 'Check out our documentation or contact support:', 'bwg-instagram-feed' ); ?></p>
            <p>
                <a href="https://bostonwebgroup.com/support" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Visit Support Center', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
        </div>
    </div>
</div>
