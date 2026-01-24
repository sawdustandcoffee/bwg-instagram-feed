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
        <h1><?php esc_html_e( 'BWG Instagram Feed', 'bwg-instagram-feed' ); ?></h1>
    </div>

    <div class="bwg-igf-dashboard-widgets">
        <!-- Quick Stats -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Quick Stats', 'bwg-instagram-feed' ); ?></h2>
            <?php
            global $wpdb;
            $feeds_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_feeds" );
            $accounts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_accounts WHERE status = 'active'" );
            ?>
            <p><strong><?php esc_html_e( 'Total Feeds:', 'bwg-instagram-feed' ); ?></strong> <?php echo esc_html( $feeds_count ); ?></p>
            <p><strong><?php esc_html_e( 'Connected Accounts:', 'bwg-instagram-feed' ); ?></strong> <?php echo esc_html( $accounts_count ); ?></p>
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
