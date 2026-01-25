<?php
/**
 * Admin Accounts Template
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
        <h1><?php esc_html_e( 'Connected Instagram Accounts', 'bwg-instagram-feed' ); ?></h1>
    </div>

    <div class="bwg-igf-widget">
        <h2><?php esc_html_e( 'Connect an Account', 'bwg-instagram-feed' ); ?></h2>
        <p><?php esc_html_e( 'Connect your Instagram account to access more features like hashtag filtering and reliable data access.', 'bwg-instagram-feed' ); ?></p>

        <?php
        $app_id = get_option( 'bwg_igf_instagram_app_id' );
        if ( empty( $app_id ) ) :
        ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e( 'Please configure your Instagram App credentials in Settings before connecting an account.', 'bwg-instagram-feed' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-settings' ) ); ?>">
                        <?php esc_html_e( 'Go to Settings', 'bwg-instagram-feed' ); ?>
                    </a>
                </p>
            </div>
        <?php else :
            // Build OAuth URL
            $redirect_uri = admin_url( 'admin.php?page=bwg-igf-accounts&oauth_callback=1' );
            $scope = 'user_profile,user_media';
            $oauth_url = sprintf(
                'https://api.instagram.com/oauth/authorize?client_id=%s&redirect_uri=%s&scope=%s&response_type=code',
                rawurlencode( $app_id ),
                rawurlencode( $redirect_uri ),
                rawurlencode( $scope )
            );
        ?>
            <a href="<?php echo esc_url( $oauth_url ); ?>" class="button button-primary bwg-igf-connect-account" id="bwg-igf-oauth-connect-btn" data-oauth-url="<?php echo esc_url( $oauth_url ); ?>">
                <?php esc_html_e( 'Connect Instagram Account', 'bwg-instagram-feed' ); ?>
            </a>
            <p class="description" style="margin-top: 10px;">
                <?php esc_html_e( 'You will be redirected to Instagram to authorize the connection.', 'bwg-instagram-feed' ); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="bwg-igf-widget" style="margin-top: 20px;">
        <h2><?php esc_html_e( 'Connected Accounts', 'bwg-instagram-feed' ); ?></h2>

        <?php
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin list needs fresh data
        $accounts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i ORDER BY connected_at DESC',
                $wpdb->prefix . 'bwg_igf_accounts'
            )
        );
        ?>

        <?php if ( empty( $accounts ) ) : ?>
            <p><?php esc_html_e( 'No accounts connected yet.', 'bwg-instagram-feed' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Username', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Expires', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'bwg-instagram-feed' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $accounts as $account ) : ?>
                        <tr>
                            <td>
                                <strong>@<?php echo esc_html( $account->username ); ?></strong>
                            </td>
                            <td><?php echo esc_html( ucfirst( $account->account_type ) ); ?></td>
                            <td>
                                <span class="bwg-igf-status bwg-igf-status-<?php echo esc_attr( $account->status ); ?>">
                                    <?php echo esc_html( ucfirst( $account->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ( $account->expires_at ) {
                                    $expires = strtotime( $account->expires_at );
                                    $days_left = ceil( ( $expires - time() ) / DAY_IN_SECONDS );
                                    if ( $days_left < 7 ) {
                                        echo '<span style="color: #dc3232;">' . sprintf( esc_html__( '%d days', 'bwg-instagram-feed' ), $days_left ) . '</span>';
                                    } else {
                                        echo esc_html( date_i18n( get_option( 'date_format' ), $expires ) );
                                    }
                                } else {
                                    esc_html_e( 'N/A', 'bwg-instagram-feed' );
                                }
                                ?>
                            </td>
                            <td>
                                <a href="#" class="bwg-igf-verify-encryption" data-account-id="<?php echo esc_attr( $account->id ); ?>">
                                    <?php esc_html_e( 'Verify Encryption', 'bwg-instagram-feed' ); ?>
                                </a>
                                <span class="bwg-igf-encryption-result" style="display: none; margin-left: 10px;"></span>
                                <br>
                                <a href="#" class="bwg-igf-disconnect-account" data-account-id="<?php echo esc_attr( $account->id ); ?>" style="color: #a00;">
                                    <?php esc_html_e( 'Disconnect', 'bwg-instagram-feed' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
