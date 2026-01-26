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

// Handle OAuth callback.
$oauth_message = '';
$oauth_error = '';

// Check for simulated OAuth success (for testing).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test parameter
if ( isset( $_GET['oauth_simulated'] ) && '1' === $_GET['oauth_simulated'] ) {
    $simulated_success = get_transient( 'bwg_igf_oauth_success' );
    if ( $simulated_success ) {
        $oauth_message = $simulated_success;
        delete_transient( 'bwg_igf_oauth_success' );
    }
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback comes from Instagram, can't use nonce
if ( isset( $_GET['oauth_callback'] ) && '1' === $_GET['oauth_callback'] ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback comes from Instagram
    if ( isset( $_GET['code'] ) ) {
        // Exchange authorization code for access token.
        $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $app_id = BWG_IGF_Instagram_Credentials::get_app_id();
        $app_secret = BWG_IGF_Instagram_Credentials::get_app_secret();
        $redirect_uri = BWG_IGF_Instagram_Credentials::get_redirect_uri();

        // Step 1: Exchange code for short-lived access token.
        $token_url = 'https://api.instagram.com/oauth/access_token';
        $response = wp_remote_post( $token_url, array(
            'body' => array(
                'client_id'     => $app_id,
                'client_secret' => $app_secret,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirect_uri,
                'code'          => $code,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $oauth_error = sprintf(
                /* translators: %s: Error message */
                __( 'Failed to connect to Instagram: %s', 'bwg-instagram-feed' ),
                $response->get_error_message()
            );
        } else {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['error_message'] ) ) {
                $oauth_error = sprintf(
                    /* translators: %s: Error message */
                    __( 'Instagram error: %s', 'bwg-instagram-feed' ),
                    esc_html( $data['error_message'] )
                );
            } elseif ( isset( $data['access_token'] ) && isset( $data['user_id'] ) ) {
                $short_lived_token = $data['access_token'];
                $instagram_user_id = $data['user_id'];

                // Step 2: Exchange for long-lived access token.
                $long_token_url = sprintf(
                    'https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=%s&access_token=%s',
                    rawurlencode( $app_secret ),
                    rawurlencode( $short_lived_token )
                );

                $long_response = wp_remote_get( $long_token_url );
                $long_body = wp_remote_retrieve_body( $long_response );
                $long_data = json_decode( $long_body, true );

                $access_token = isset( $long_data['access_token'] ) ? $long_data['access_token'] : $short_lived_token;
                $expires_in = isset( $long_data['expires_in'] ) ? intval( $long_data['expires_in'] ) : ( 60 * DAY_IN_SECONDS );

                // Step 3: Get user info.
                $user_url = sprintf(
                    'https://graph.instagram.com/me?fields=id,username,account_type&access_token=%s',
                    rawurlencode( $access_token )
                );

                $user_response = wp_remote_get( $user_url );
                $user_body = wp_remote_retrieve_body( $user_response );
                $user_data = json_decode( $user_body, true );

                if ( isset( $user_data['username'] ) ) {
                    $username = $user_data['username'];
                    $account_type = isset( $user_data['account_type'] ) ? strtolower( $user_data['account_type'] ) : 'basic';

                    // Normalize account type to match our ENUM.
                    if ( ! in_array( $account_type, array( 'basic', 'business', 'creator' ), true ) ) {
                        $account_type = 'basic';
                    }

                    // Step 4: Encrypt and save the token.
                    $encrypted_token = BWG_IGF_Encryption::encrypt( $access_token );
                    $expires_at = gmdate( 'Y-m-d H:i:s', time() + $expires_in );

                    global $wpdb;

                    // Check if account exists.
                    $existing = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}bwg_igf_accounts WHERE instagram_user_id = %d",
                            $instagram_user_id
                        )
                    );

                    if ( $existing ) {
                        // Update existing account.
                        $wpdb->update(
                            $wpdb->prefix . 'bwg_igf_accounts',
                            array(
                                'username'       => $username,
                                'access_token'   => $encrypted_token,
                                'token_type'     => 'bearer',
                                'expires_at'     => $expires_at,
                                'account_type'   => $account_type,
                                'last_refreshed' => current_time( 'mysql' ),
                                'status'         => 'active',
                            ),
                            array( 'instagram_user_id' => $instagram_user_id ),
                            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                            array( '%d' )
                        );
                        $oauth_message = sprintf(
                            /* translators: %s: Instagram username */
                            __( 'Instagram account @%s reconnected successfully!', 'bwg-instagram-feed' ),
                            esc_html( $username )
                        );
                    } else {
                        // Insert new account.
                        $wpdb->insert(
                            $wpdb->prefix . 'bwg_igf_accounts',
                            array(
                                'instagram_user_id' => $instagram_user_id,
                                'username'          => $username,
                                'access_token'      => $encrypted_token,
                                'token_type'        => 'bearer',
                                'expires_at'        => $expires_at,
                                'account_type'      => $account_type,
                                'connected_at'      => current_time( 'mysql' ),
                                'status'            => 'active',
                            ),
                            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                        );
                        $oauth_message = sprintf(
                            /* translators: %s: Instagram username */
                            __( 'Instagram account @%s connected successfully!', 'bwg-instagram-feed' ),
                            esc_html( $username )
                        );
                    }
                } else {
                    $oauth_error = __( 'Failed to retrieve Instagram user information.', 'bwg-instagram-feed' );
                }
            } else {
                $oauth_error = __( 'Failed to obtain access token from Instagram.', 'bwg-instagram-feed' );
            }
        }
    } elseif ( isset( $_GET['error'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback comes from Instagram
        $error_reason = isset( $_GET['error_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['error_reason'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback comes from Instagram
        $error_desc = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';

        if ( 'user_denied' === $error_reason ) {
            $oauth_error = __( 'You denied the authorization request. No account was connected.', 'bwg-instagram-feed' );
        } else {
            $oauth_error = sprintf(
                /* translators: %s: Error description */
                __( 'Authorization failed: %s', 'bwg-instagram-feed' ),
                esc_html( $error_desc ?: $error_reason )
            );
        }
    }
}
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <h1><?php esc_html_e( 'Connected Instagram Accounts', 'bwg-instagram-feed' ); ?></h1>
    </div>

    <?php if ( ! empty( $oauth_message ) ) : ?>
        <div class="notice notice-success is-dismissible bwg-igf-oauth-notice">
            <p><strong><?php echo esc_html( $oauth_message ); ?></strong></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $oauth_error ) ) : ?>
        <div class="notice notice-error is-dismissible bwg-igf-oauth-notice">
            <p><strong><?php echo esc_html( $oauth_error ); ?></strong></p>
        </div>
    <?php endif; ?>

    <div class="bwg-igf-widget">
        <h2><?php esc_html_e( 'Connect an Account', 'bwg-instagram-feed' ); ?></h2>
        <p><?php esc_html_e( 'Connect your Instagram account to access more features like hashtag filtering and reliable data access.', 'bwg-instagram-feed' ); ?></p>

        <?php
        // Use built-in Instagram App credentials
        $oauth_url = BWG_IGF_Instagram_Credentials::get_oauth_url();
        ?>
            <a href="<?php echo esc_url( $oauth_url ); ?>" class="button button-primary bwg-igf-connect-account" id="bwg-igf-oauth-connect-btn" data-oauth-url="<?php echo esc_url( $oauth_url ); ?>">
                <?php esc_html_e( 'Connect Instagram Account', 'bwg-instagram-feed' ); ?>
            </a>
            <p class="description" style="margin-top: 10px;">
                <?php esc_html_e( 'You will be redirected to Instagram to authorize the connection.', 'bwg-instagram-feed' ); ?>
            </p>
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
                                    if ( $days_left <= 0 ) {
                                        // Token has expired
                                        ?>
                                        <div class="bwg-igf-token-warning bwg-igf-token-expired">
                                            <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                                            <span style="color: #dc3232; font-weight: bold;"><?php esc_html_e( 'Expired', 'bwg-instagram-feed' ); ?></span>
                                            <p class="description" style="margin: 5px 0 0 0; color: #dc3232;">
                                                <?php esc_html_e( 'Token has expired. Please reconnect your account to continue using this feed.', 'bwg-instagram-feed' ); ?>
                                            </p>
                                        </div>
                                        <?php
                                    } elseif ( $days_left <= 7 ) {
                                        // Token expiring soon (within 7 days)
                                        ?>
                                        <div class="bwg-igf-token-warning bwg-igf-token-expiring">
                                            <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                            <span style="color: #dba617; font-weight: bold;">
                                                <?php
                                                printf(
                                                    /* translators: %d: number of days until token expires */
                                                    esc_html__( '%d days', 'bwg-instagram-feed' ),
                                                    $days_left
                                                );
                                                ?>
                                            </span>
                                            <p class="description" style="margin: 5px 0 0 0; color: #826200;">
                                                <?php esc_html_e( 'Token expiring soon. Reconnect your account to refresh the token.', 'bwg-instagram-feed' ); ?>
                                            </p>
                                        </div>
                                        <?php
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
