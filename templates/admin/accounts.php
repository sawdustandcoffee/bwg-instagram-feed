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
                        $account_id = $existing->id;
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
                        $account_id = $wpdb->insert_id;
                        $oauth_message = sprintf(
                            /* translators: %s: Instagram username */
                            __( 'Instagram account @%s connected successfully!', 'bwg-instagram-feed' ),
                            esc_html( $username )
                        );
                    }

                    // Feature #24: Cache warming on account connection.
                    // Immediately fetch and cache posts after connecting an account.
                    // This ensures feeds using this account display immediately without additional API calls.
                    if ( $account_id > 0 && ! empty( $access_token ) ) {
                        // Load the Instagram API class.
                        if ( ! class_exists( 'BWG_IGF_Instagram_API' ) ) {
                            require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-api.php';
                        }

                        $instagram_api = new BWG_IGF_Instagram_API();
                        $instagram_api->set_current_account_id( $account_id );

                        // Fetch initial posts (12 posts for cache warming).
                        $warmed_posts = $instagram_api->fetch_connected_posts( $access_token, 12, $account_id );

                        if ( ! is_wp_error( $warmed_posts ) && ! empty( $warmed_posts ) ) {
                            // Store the warmed cache in a transient keyed by account ID.
                            // This cache will be used when creating feeds with this account.
                            // Cache duration: 1 hour (3600 seconds).
                            set_transient(
                                'bwg_igf_account_cache_' . $account_id,
                                array(
                                    'posts'      => $warmed_posts,
                                    'fetched_at' => time(),
                                    'username'   => $username,
                                ),
                                3600
                            );

                            // Update success message to indicate posts were fetched.
                            $oauth_message = sprintf(
                                /* translators: 1: Instagram username, 2: Number of posts fetched */
                                __( 'Instagram account @%1$s connected successfully! %2$d posts cached and ready to display.', 'bwg-instagram-feed' ),
                                esc_html( $username ),
                                count( $warmed_posts )
                            );
                        }
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
        $has_credentials = BWG_IGF_Instagram_Credentials::has_credentials();
        $using_placeholder = BWG_IGF_Instagram_Credentials::is_using_placeholder_credentials();
        ?>

        <?php if ( $using_placeholder ) : ?>
            <!-- Instagram API Setup Instructions -->
            <div class="bwg-igf-setup-notice" style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #826200;">
                    <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                    <?php esc_html_e( 'Instagram API Setup Required', 'bwg-instagram-feed' ); ?>
                </h3>
                <p style="color: #826200;">
                    <?php esc_html_e( 'To connect your Instagram account, you need to configure your own Instagram/Meta App credentials. Follow these steps:', 'bwg-instagram-feed' ); ?>
                </p>

                <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 15px 0;">
                    <h4 style="margin-top: 0;"><?php esc_html_e( 'Step 1: Create a Meta Developer App', 'bwg-instagram-feed' ); ?></h4>
                    <ol style="margin-left: 20px;">
                        <li><?php echo wp_kses_post( __( 'Go to <a href="https://developers.facebook.com/" target="_blank" rel="noopener noreferrer">Meta for Developers</a> and log in with your Facebook account.', 'bwg-instagram-feed' ) ); ?></li>
                        <li><?php esc_html_e( 'Click "My Apps" → "Create App" → Choose "Consumer" or "Business" type.', 'bwg-instagram-feed' ); ?></li>
                        <li><?php esc_html_e( 'Add the "Instagram Basic Display" product to your app.', 'bwg-instagram-feed' ); ?></li>
                        <li><?php esc_html_e( 'In Instagram Basic Display settings, add this as a Valid OAuth Redirect URI:', 'bwg-instagram-feed' ); ?>
                            <br>
                            <code style="background: #f0f0f0; padding: 5px 10px; display: inline-block; margin: 5px 0; word-break: break-all;"><?php echo esc_html( BWG_IGF_Instagram_Credentials::get_redirect_uri() ); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( BWG_IGF_Instagram_Credentials::get_redirect_uri() ); ?>'); this.innerText='Copied!';">
                                <?php esc_html_e( 'Copy', 'bwg-instagram-feed' ); ?>
                            </button>
                        </li>
                        <li><?php esc_html_e( 'Copy your Instagram App ID and Instagram App Secret from the app settings.', 'bwg-instagram-feed' ); ?></li>
                    </ol>

                    <h4><?php esc_html_e( 'Step 2: Add Credentials to WordPress', 'bwg-instagram-feed' ); ?></h4>
                    <p><?php esc_html_e( 'Add the following lines to your wp-config.php file (before the line that says "That\'s all, stop editing!"):', 'bwg-instagram-feed' ); ?></p>
                    <pre style="background: #23282d; color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px;">
<span style="color: #9cdcfe;">// BWG Instagram Feed - API Credentials</span>
<span style="color: #dcdcaa;">define</span>( <span style="color: #ce9178;">'BWG_IGF_INSTAGRAM_APP_ID'</span>, <span style="color: #ce9178;">'your_app_id_here'</span> );
<span style="color: #dcdcaa;">define</span>( <span style="color: #ce9178;">'BWG_IGF_INSTAGRAM_APP_SECRET'</span>, <span style="color: #ce9178;">'your_app_secret_here'</span> );</pre>
                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(&quot;// BWG Instagram Feed - API Credentials\ndefine( 'BWG_IGF_INSTAGRAM_APP_ID', 'your_app_id_here' );\ndefine( 'BWG_IGF_INSTAGRAM_APP_SECRET', 'your_app_secret_here' );&quot;); this.innerText='Copied!';">
                        <?php esc_html_e( 'Copy Code', 'bwg-instagram-feed' ); ?>
                    </button>

                    <h4><?php esc_html_e( 'Step 3: Refresh This Page', 'bwg-instagram-feed' ); ?></h4>
                    <p><?php esc_html_e( 'After adding your credentials to wp-config.php, refresh this page. The "Connect Instagram Account" button will then work.', 'bwg-instagram-feed' ); ?></p>
                </div>

                <p style="margin-bottom: 0;">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php echo wp_kses_post( __( 'Need help? Check out the <a href="https://developers.facebook.com/docs/instagram-basic-display-api/getting-started" target="_blank" rel="noopener noreferrer">Instagram Basic Display API documentation</a>.', 'bwg-instagram-feed' ) ); ?>
                </p>
            </div>

            <button type="button" class="button button-secondary" disabled style="opacity: 0.6; cursor: not-allowed;">
                <?php esc_html_e( 'Connect Instagram Account', 'bwg-instagram-feed' ); ?>
            </button>
            <p class="description" style="margin-top: 10px; color: #826200;">
                <?php esc_html_e( 'Please configure your Instagram API credentials above to enable account connections.', 'bwg-instagram-feed' ); ?>
            </p>
        <?php else : ?>
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
                        <th><?php esc_html_e( 'API Quota', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Expires', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'bwg-instagram-feed' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $accounts as $account ) :
                        // Determine account health status (Feature #27).
                        $health_status = 'connected'; // Default: healthy.
                        $health_class = 'active';
                        $health_label = __( 'Connected', 'bwg-instagram-feed' );
                        $health_icon = 'yes-alt';
                        $health_icon_color = '#46b450';

                        // Check for rate limiting first (highest priority indicator).
                        // Uses both is_rate_limited (429 errors) and should_backoff (exponential backoff state).
                        $rate_status = null;
                        $default_rate_limit = 200; // Instagram default hourly rate limit (Feature #29).
                        $is_in_backoff = false;
                        if ( class_exists( 'BWG_IGF_API_Tracker' ) ) {
                            $rate_status = BWG_IGF_API_Tracker::get_rate_limit_status( $account->id );
                            $is_in_backoff = BWG_IGF_API_Tracker::should_backoff( $account->id );
                            if ( $rate_status['is_limited'] || $is_in_backoff ) {
                                $health_status = 'rate_limited';
                                $health_class = 'inactive';
                                $health_label = __( 'Rate Limited', 'bwg-instagram-feed' );
                                $health_icon = 'dismiss';
                                $health_icon_color = '#dc3232';
                            }
                        }

                        // Check token expiration (second priority).
                        if ( 'rate_limited' !== $health_status && $account->expires_at ) {
                            $expires = strtotime( $account->expires_at );
                            $days_left = ceil( ( $expires - time() ) / DAY_IN_SECONDS );
                            if ( $days_left <= 0 ) {
                                $health_status = 'expired';
                                $health_class = 'inactive';
                                $health_label = __( 'Expired', 'bwg-instagram-feed' );
                                $health_icon = 'warning';
                                $health_icon_color = '#dc3232';
                            } elseif ( $days_left <= 7 ) {
                                $health_status = 'expiring';
                                $health_class = 'error';
                                $health_label = __( 'Expiring Soon', 'bwg-instagram-feed' );
                                $health_icon = 'warning';
                                $health_icon_color = '#dba617';
                            }
                        }

                        // Check account status (error state).
                        if ( 'active' !== $account->status ) {
                            $health_status = 'error';
                            $health_class = 'inactive';
                            $health_label = ucfirst( $account->status );
                            $health_icon = 'dismiss';
                            $health_icon_color = '#dc3232';
                        }
                    ?>
                        <tr>
                            <td>
                                <strong>@<?php echo esc_html( $account->username ); ?></strong>
                            </td>
                            <td><?php echo esc_html( ucfirst( $account->account_type ) ); ?></td>
                            <td>
                                <span class="bwg-igf-status bwg-igf-status-<?php echo esc_attr( $health_class ); ?>">
                                    <span class="dashicons dashicons-<?php echo esc_attr( $health_icon ); ?>" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-right: 3px; color: <?php echo esc_attr( $health_icon_color ); ?>;"></span>
                                    <?php echo esc_html( $health_label ); ?>
                                </span>
                            </td>
                            <!-- Feature #29: Display remaining API quota when available -->
                            <td>
                                <?php
                                if ( $rate_status && null !== $rate_status['remaining'] ) :
                                    $remaining = intval( $rate_status['remaining'] );
                                    $total = $default_rate_limit;
                                    $percentage_used = ( ( $total - $remaining ) / $total ) * 100;
                                    $quota_color = '#46b450'; // Green.
                                    if ( $percentage_used >= 80 ) {
                                        $quota_color = '#dc3232'; // Red.
                                    } elseif ( $percentage_used >= 60 ) {
                                        $quota_color = '#dba617'; // Yellow.
                                    }
                                    ?>
                                    <div class="bwg-igf-quota-indicator" style="display: inline-block;">
                                        <span style="color: <?php echo esc_attr( $quota_color ); ?>; font-weight: bold;">
                                            <?php
                                            /* translators: 1: remaining API calls, 2: total API calls */
                                            printf(
                                                esc_html__( '%1$d/%2$d', 'bwg-instagram-feed' ),
                                                $remaining,
                                                $total
                                            );
                                            ?>
                                        </span>
                                        <span style="color: #666; font-size: 12px;"><?php esc_html_e( 'calls remaining', 'bwg-instagram-feed' ); ?></span>
                                        <?php if ( $rate_status['last_call'] ) : ?>
                                            <br>
                                            <small style="color: #999;">
                                                <?php
                                                $last_call_time = strtotime( $rate_status['last_call'] );
                                                $human_diff = human_time_diff( $last_call_time, current_time( 'timestamp' ) );
                                                /* translators: %s: human-readable time difference */
                                                printf( esc_html__( 'Updated %s ago', 'bwg-instagram-feed' ), esc_html( $human_diff ) );
                                                ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <span style="color: #999;"><?php esc_html_e( 'N/A', 'bwg-instagram-feed' ); ?></span>
                                    <br>
                                    <small style="color: #999;"><?php esc_html_e( 'No recent API calls', 'bwg-instagram-feed' ); ?></small>
                                <?php endif; ?>
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
