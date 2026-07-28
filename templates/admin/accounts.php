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

// OAuth callback results are produced by the REST endpoint
// (/wp-json/bwg-igf/v1/instagram-oauth) which handles the token exchange and
// then redirects here. The outcome is stashed in a short-lived per-user
// transient; read it (and delete it, single-use) so we can display it once.
$oauth_message = '';
$oauth_error = '';

$bwg_oauth_result_key = 'bwg_igf_oauth_result_' . get_current_user_id();
$bwg_oauth_result     = get_transient( $bwg_oauth_result_key );
if ( is_array( $bwg_oauth_result ) && ! empty( $bwg_oauth_result['message'] ) ) {
    delete_transient( $bwg_oauth_result_key );

    if ( isset( $bwg_oauth_result['type'] ) && 'error' === $bwg_oauth_result['type'] ) {
        $oauth_error = $bwg_oauth_result['message'];
    } else {
        $oauth_message = $bwg_oauth_result['message'];
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

    <?php if ( BWG_IGF_Instagram_Credentials::redirect_uri_has_query_string() ) : ?>
        <?php // Plain permalinks make rest_url() emit a ?rest_route= query string, which Instagram rejects. ?>
        <div class="notice notice-error bwg-igf-permalink-warning">
            <p>
                <strong><?php esc_html_e( 'Instagram login will not work with Plain permalinks.', 'bwg-instagram-feed' ); ?></strong>
                <?php
                printf(
                    /* translators: %s: link to the Permalinks settings screen */
                    esc_html__( 'Your OAuth Redirect URI currently contains a query string, which Instagram rejects. Enable pretty permalinks under %s (any option other than "Plain") and then re-copy the redirect URI into your Meta app.', 'bwg-instagram-feed' ),
                    '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'bwg-instagram-feed' ) . '</a>'
                );
                ?>
            </p>
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
                    <p style="color: #826200; margin-top: 0;"><?php esc_html_e( 'Note: The Instagram account you connect must be a Business or Creator account. Personal accounts are no longer supported.', 'bwg-instagram-feed' ); ?></p>
                    <ol style="margin-left: 20px;">
                        <li><?php echo wp_kses_post( __( 'Go to <a href="https://developers.facebook.com/" target="_blank" rel="noopener noreferrer">Meta for Developers</a> and log in with your Facebook account.', 'bwg-instagram-feed' ) ); ?></li>
                        <li><?php esc_html_e( 'Click "My Apps" → "Create App" → choose the "Business" app type.', 'bwg-instagram-feed' ); ?></li>
                        <li><?php esc_html_e( 'Add the "Instagram" product to your app, then choose "API setup with Instagram login" (use case: "Manage messaging and content on Instagram").', 'bwg-instagram-feed' ); ?></li>
                        <li><?php esc_html_e( 'Under "Business login settings", add this as an OAuth Redirect URI:', 'bwg-instagram-feed' ); ?>
                            <br>
                            <code style="background: #f0f0f0; padding: 5px 10px; display: inline-block; margin: 5px 0; word-break: break-all;"><?php echo esc_html( BWG_IGF_Instagram_Credentials::get_redirect_uri() ); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( BWG_IGF_Instagram_Credentials::get_redirect_uri() ); ?>'); this.innerText='Copied!';">
                                <?php esc_html_e( 'Copy', 'bwg-instagram-feed' ); ?>
                            </button>
                        </li>
                        <li><?php esc_html_e( 'Copy the Instagram App ID and Instagram App Secret from the Instagram product settings (not the top-level Meta App ID / App Secret).', 'bwg-instagram-feed' ); ?></li>
                    </ol>

                    <h4><?php esc_html_e( 'Step 2: Add Credentials in Settings', 'bwg-instagram-feed' ); ?></h4>
                    <p style="color: #826200;"><?php esc_html_e( 'The easiest way to add your credentials is on the plugin Settings page. Open the "Instagram App Credentials" section, paste in your Instagram App ID and Instagram App Secret, and save. The App Secret is stored encrypted in the database.', 'bwg-instagram-feed' ); ?></p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-settings' ) ); ?>" class="button button-secondary">
                            <?php esc_html_e( 'Go to Settings', 'bwg-instagram-feed' ); ?>
                        </a>
                    </p>
                    <p style="color: #826200;"><em><?php esc_html_e( 'Optional (advanced): instead of the Settings page, you can define these constants in wp-config.php. When defined, they override the values saved in Settings:', 'bwg-instagram-feed' ); ?></em></p>
                    <pre style="background: #23282d; color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px;">
<span style="color: #9cdcfe;">// BWG Instagram Feed - API Credentials</span>
<span style="color: #dcdcaa;">define</span>( <span style="color: #ce9178;">'BWG_IGF_INSTAGRAM_APP_ID'</span>, <span style="color: #ce9178;">'your_app_id_here'</span> );
<span style="color: #dcdcaa;">define</span>( <span style="color: #ce9178;">'BWG_IGF_INSTAGRAM_APP_SECRET'</span>, <span style="color: #ce9178;">'your_app_secret_here'</span> );</pre>
                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(&quot;// BWG Instagram Feed - API Credentials\ndefine( 'BWG_IGF_INSTAGRAM_APP_ID', 'your_app_id_here' );\ndefine( 'BWG_IGF_INSTAGRAM_APP_SECRET', 'your_app_secret_here' );&quot;); this.innerText='Copied!';">
                        <?php esc_html_e( 'Copy Code', 'bwg-instagram-feed' ); ?>
                    </button>

                    <h4><?php esc_html_e( 'Step 3: Refresh This Page', 'bwg-instagram-feed' ); ?></h4>
                    <p><?php esc_html_e( 'After saving your credentials in Settings (or adding them to wp-config.php), refresh this page. The "Connect Instagram Account" button will then work.', 'bwg-instagram-feed' ); ?></p>
                </div>

                <p style="margin-bottom: 0;">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php echo wp_kses_post( __( 'Need help? Check out the <a href="https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login" target="_blank" rel="noopener noreferrer">Instagram API with Instagram Login documentation</a>.', 'bwg-instagram-feed' ) ); ?>
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
