<?php
/**
 * Feed Editor Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$feed = null;
$is_edit_action = isset( $_GET['action'] ) && 'edit' === $_GET['action'];

if ( $feed_id > 0 ) {
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
        $feed_id
    ) );
}

// Security check: If user requested to edit a specific feed but it doesn't exist, show error
if ( $is_edit_action && $feed_id > 0 && empty( $feed ) ) {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Feed Not Found', 'bwg-instagram-feed' ); ?></h1>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'The feed you are trying to edit does not exist or has been deleted.', 'bwg-instagram-feed' ); ?></p>
        </div>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds' ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'Return to Feeds List', 'bwg-instagram-feed' ); ?>
            </a>
        </p>
    </div>
    <?php
    return;
}

$is_new = empty( $feed );

// Feature #35: Check if this feed's connected account has issues (for existing feeds only).
$connected_account_warning = '';
$connected_account_username = '';
if ( ! $is_new && $feed && 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) ) {
    $account_check = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, username, status, expires_at FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
        $feed->connected_account_id
    ) );

    if ( ! $account_check ) {
        // Account was deleted.
        $connected_account_warning = __( 'The connected Instagram account for this feed has been removed. Consider switching to a Public feed type as an alternative.', 'bwg-instagram-feed' );
    } elseif ( 'active' !== $account_check->status ) {
        // Account is inactive/disconnected.
        $connected_account_warning = sprintf(
            /* translators: %s: Instagram username */
            __( 'The Instagram account @%s is no longer connected. Consider switching to a Public feed type as an alternative.', 'bwg-instagram-feed' ),
            esc_html( $account_check->username )
        );
        $connected_account_username = $account_check->username;
    } elseif ( ! empty( $account_check->expires_at ) && strtotime( $account_check->expires_at ) < time() ) {
        // Token has expired.
        $connected_account_warning = sprintf(
            /* translators: %s: Instagram username */
            __( 'The access token for @%s has expired. Please reconnect the account, or switch to a Public feed type as an alternative.', 'bwg-instagram-feed' ),
            esc_html( $account_check->username )
        );
        $connected_account_username = $account_check->username;
    }
}
?>
<div class="wrap">
    <!-- Breadcrumb Navigation -->
    <nav class="bwg-igf-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'bwg-instagram-feed' ); ?>">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf' ) ); ?>" class="bwg-igf-breadcrumb-link">
            <?php esc_html_e( 'Dashboard', 'bwg-instagram-feed' ); ?>
        </a>
        <span class="bwg-igf-breadcrumb-separator" aria-hidden="true">&rsaquo;</span>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds' ) ); ?>" class="bwg-igf-breadcrumb-link">
            <?php esc_html_e( 'Feeds', 'bwg-instagram-feed' ); ?>
        </a>
        <span class="bwg-igf-breadcrumb-separator" aria-hidden="true">&rsaquo;</span>
        <span class="bwg-igf-breadcrumb-current" aria-current="page">
            <?php echo $is_new ? esc_html__( 'Create', 'bwg-instagram-feed' ) : esc_html__( 'Edit', 'bwg-instagram-feed' ); ?>
        </span>
    </nav>

    <div class="bwg-igf-header">
        <h1>
            <?php echo $is_new ? esc_html__( 'Create New Feed', 'bwg-instagram-feed' ) : esc_html__( 'Edit Feed', 'bwg-instagram-feed' ); ?>
        </h1>
    </div>

    <?php
    // Feature #35: Show warning notice if connected account has issues.
    if ( ! empty( $connected_account_warning ) ) :
    ?>
        <div class="notice notice-warning bwg-igf-connected-account-warning" style="margin: 15px 0;">
            <p>
                <strong><?php esc_html_e( 'Connected Account Issue', 'bwg-instagram-feed' ); ?></strong>
            </p>
            <p><?php echo esc_html( $connected_account_warning ); ?></p>
            <p>
                <strong><?php esc_html_e( 'Quick Fix:', 'bwg-instagram-feed' ); ?></strong>
                <?php esc_html_e( 'Change "Feed Type" to "Public (Username)" and enter the Instagram username to display.', 'bwg-instagram-feed' ); ?>
                <?php if ( ! empty( $connected_account_username ) ) : ?>
                    <br>
                    <em>
                        <?php
                        printf(
                            /* translators: %s: Instagram username */
                            esc_html__( 'Tip: You can enter "%s" as the username to show the same account\'s posts.', 'bwg-instagram-feed' ),
                            esc_html( $connected_account_username )
                        );
                        ?>
                    </em>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <form id="bwg-igf-feed-form" method="post">
        <input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>">

        <div class="bwg-igf-editor">
            <!-- Configuration Panel -->
            <div class="bwg-igf-editor-panel">
                <div class="bwg-igf-editor-tabs">
                    <button type="button" class="bwg-igf-editor-tab active" data-tab="source">
                        <?php esc_html_e( 'Source', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="layout">
                        <?php esc_html_e( 'Layout', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="display">
                        <?php esc_html_e( 'Display', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="styling">
                        <?php esc_html_e( 'Styling', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="popup">
                        <?php esc_html_e( 'Popup', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="advanced">
                        <?php esc_html_e( 'Advanced', 'bwg-instagram-feed' ); ?>
                    </button>
                </div>

                <div class="bwg-igf-editor-content">
                    <!-- Source Tab -->
                    <div id="bwg-igf-tab-source" class="bwg-igf-tab-content active">
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-name"><?php esc_html_e( 'Feed Name', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-name" name="name" value="<?php echo $feed ? esc_attr( $feed->name ) : ''; ?>" required>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-feed-type"><?php esc_html_e( 'Feed Type', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-feed-type" name="feed_type">
                                <option value="public" <?php selected( $feed ? $feed->feed_type : '', 'public' ); ?>><?php esc_html_e( 'Public (Username)', 'bwg-instagram-feed' ); ?></option>
                                <option value="connected" <?php selected( $feed ? $feed->feed_type : '', 'connected' ); ?>><?php esc_html_e( 'Connected Account', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field" id="bwg-igf-username-field">
                            <label for="bwg-igf-username"><?php esc_html_e( 'Instagram Username(s)', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-username" name="instagram_usernames" value="<?php echo $feed ? esc_attr( $feed->instagram_usernames ) : ''; ?>" placeholder="<?php esc_attr_e( 'username or username1, username2', 'bwg-instagram-feed' ); ?>">
                            <span class="bwg-igf-validation-indicator"></span>
                            <p class="description"><?php esc_html_e( 'Enter one or more Instagram usernames (comma-separated for multiple).', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <?php
                        // Get connected accounts for the dropdown with expiration info for status indicators (Feature #32).
                        // Only show active accounts, but include expires_at for health status display.
                        $connected_accounts = $wpdb->get_results(
                            "SELECT id, username, account_type, status, expires_at FROM {$wpdb->prefix}bwg_igf_accounts WHERE status = 'active' ORDER BY username ASC"
                        );
                        $selected_account_id = $feed ? absint( $feed->connected_account_id ) : 0;
                        ?>
                        <div class="bwg-igf-field" id="bwg-igf-connected-account-field" style="display: none;">
                            <label for="bwg-igf-connected-account"><?php esc_html_e( 'Connected Account', 'bwg-instagram-feed' ); ?></label>
                            <?php if ( empty( $connected_accounts ) ) : ?>
                                <p class="description" style="color: #d63638;">
                                    <?php esc_html_e( 'No connected Instagram accounts found.', 'bwg-instagram-feed' ); ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-accounts' ) ); ?>">
                                        <?php esc_html_e( 'Connect an account', 'bwg-instagram-feed' ); ?>
                                    </a>
                                </p>
                                <input type="hidden" name="connected_account_id" value="">
                            <?php else : ?>
                                <select id="bwg-igf-connected-account" name="connected_account_id">
                                    <option value=""><?php esc_html_e( '— Select an account —', 'bwg-instagram-feed' ); ?></option>
                                    <?php foreach ( $connected_accounts as $account ) :
                                        // Feature #32: Determine account health status for dropdown display.
                                        $health_status = 'connected';
                                        $status_emoji = '✓'; // Green checkmark for connected.
                                        $status_text = __( 'Connected', 'bwg-instagram-feed' );

                                        // Check for rate limiting (highest priority indicator).
                                        $is_rate_limited = false;
                                        if ( class_exists( 'BWG_IGF_API_Tracker' ) ) {
                                            $rate_status = BWG_IGF_API_Tracker::get_rate_limit_status( $account->id );
                                            $is_in_backoff = BWG_IGF_API_Tracker::should_backoff( $account->id );
                                            if ( $rate_status['is_limited'] || $is_in_backoff ) {
                                                $health_status = 'rate_limited';
                                                $status_emoji = '⚠'; // Warning for rate limited.
                                                $status_text = __( 'Rate Limited', 'bwg-instagram-feed' );
                                            }
                                        }

                                        // Check token expiration (second priority).
                                        if ( 'rate_limited' !== $health_status && $account->expires_at ) {
                                            $expires = strtotime( $account->expires_at );
                                            $days_left = ceil( ( $expires - time() ) / DAY_IN_SECONDS );
                                            if ( $days_left <= 0 ) {
                                                $health_status = 'expired';
                                                $status_emoji = '✗'; // X for expired.
                                                $status_text = __( 'Expired', 'bwg-instagram-feed' );
                                            } elseif ( $days_left <= 7 ) {
                                                $health_status = 'expiring';
                                                $status_emoji = '⚠'; // Warning for expiring soon.
                                                $status_text = __( 'Expiring Soon', 'bwg-instagram-feed' );
                                            }
                                        }

                                        // Build the option label with status indicator.
                                        $option_label = sprintf(
                                            '@%s (%s) — %s %s',
                                            $account->username,
                                            ucfirst( $account->account_type ),
                                            $status_emoji,
                                            $status_text
                                        );
                                    ?>
                                        <option value="<?php echo esc_attr( $account->id ); ?>" <?php selected( $selected_account_id, $account->id ); ?> data-health-status="<?php echo esc_attr( $health_status ); ?>">
                                            <?php echo esc_html( $option_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Select a connected Instagram account to display posts from.', 'bwg-instagram-feed' ); ?></p>
                                <?php
                                // Feature #32: Show legend for status indicators.
                                ?>
                                <p class="description bwg-igf-account-status-legend" style="margin-top: 8px; font-size: 11px; color: #666;">
                                    <span style="color: #46b450;">✓</span> <?php esc_html_e( 'Connected', 'bwg-instagram-feed' ); ?> &nbsp;|&nbsp;
                                    <span style="color: #dba617;">⚠</span> <?php esc_html_e( 'Expiring/Limited', 'bwg-instagram-feed' ); ?> &nbsp;|&nbsp;
                                    <span style="color: #dc3232;">✗</span> <?php esc_html_e( 'Error', 'bwg-instagram-feed' ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Layout Tab -->
                    <div id="bwg-igf-tab-layout" class="bwg-igf-tab-content">
                        <?php
                        // Get layout settings from feed
                        $layout_settings = $feed && ! empty( $feed->layout_settings ) ? json_decode( $feed->layout_settings, true ) : array();
                        $layout_type = $feed ? $feed->layout_type : 'grid';
                        $columns = isset( $layout_settings['columns'] ) ? $layout_settings['columns'] : 3;
                        $gap = isset( $layout_settings['gap'] ) ? $layout_settings['gap'] : 10;
                        $slides_to_show = isset( $layout_settings['slides_to_show'] ) ? $layout_settings['slides_to_show'] : 3;
                        $slides_to_scroll = isset( $layout_settings['slides_to_scroll'] ) ? $layout_settings['slides_to_scroll'] : 1;
                        $autoplay = isset( $layout_settings['autoplay'] ) ? $layout_settings['autoplay'] : false;
                        $autoplay_speed = isset( $layout_settings['autoplay_speed'] ) ? $layout_settings['autoplay_speed'] : 3000;
                        $show_arrows = isset( $layout_settings['show_arrows'] ) ? $layout_settings['show_arrows'] : true;
                        $show_dots = isset( $layout_settings['show_dots'] ) ? $layout_settings['show_dots'] : true;
                        $infinite_loop = isset( $layout_settings['infinite_loop'] ) ? $layout_settings['infinite_loop'] : true;

                        // Responsive settings
                        $mobile_columns = isset( $layout_settings['mobile_columns'] ) ? $layout_settings['mobile_columns'] : 2;
                        $mobile_rows = isset( $layout_settings['mobile_rows'] ) ? $layout_settings['mobile_rows'] : 0;
                        $tablet_columns = isset( $layout_settings['tablet_columns'] ) ? $layout_settings['tablet_columns'] : 3;
                        $tablet_rows = isset( $layout_settings['tablet_rows'] ) ? $layout_settings['tablet_rows'] : 0;
                        ?>
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-layout-type"><?php esc_html_e( 'Layout Type', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-layout-type" name="layout_type">
                                <option value="grid" <?php selected( $layout_type, 'grid' ); ?>><?php esc_html_e( 'Grid', 'bwg-instagram-feed' ); ?></option>
                                <option value="slider" <?php selected( $layout_type, 'slider' ); ?>><?php esc_html_e( 'Slider', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <!-- Grid-specific options -->
                        <div class="bwg-igf-grid-options">
                            <div class="bwg-igf-field">
                                <label for="bwg-igf-columns"><?php esc_html_e( 'Columns', 'bwg-instagram-feed' ); ?></label>
                                <input type="number" id="bwg-igf-columns" name="columns" value="<?php echo esc_attr( $columns ); ?>" min="1" max="6">
                            </div>

                            <div class="bwg-igf-field">
                                <label for="bwg-igf-gap"><?php esc_html_e( 'Gap (px)', 'bwg-instagram-feed' ); ?></label>
                                <input type="number" id="bwg-igf-gap" name="gap" value="<?php echo esc_attr( $gap ); ?>" min="0" max="50">
                            </div>
                        </div>

                        <!-- Slider-specific options -->
                        <div class="bwg-igf-slider-options" style="display: none;">
                            <div class="bwg-igf-field">
                                <label for="bwg-igf-slides-to-show"><?php esc_html_e( 'Slides to Show', 'bwg-instagram-feed' ); ?></label>
                                <input type="number" id="bwg-igf-slides-to-show" name="slides_to_show" value="<?php echo esc_attr( $slides_to_show ); ?>" min="1" max="5">
                            </div>

                            <div class="bwg-igf-field">
                                <label for="bwg-igf-slides-to-scroll"><?php esc_html_e( 'Slides to Scroll', 'bwg-instagram-feed' ); ?></label>
                                <input type="number" id="bwg-igf-slides-to-scroll" name="slides_to_scroll" value="<?php echo esc_attr( $slides_to_scroll ); ?>" min="1" max="5">
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" id="bwg-igf-autoplay" name="autoplay" value="1" <?php checked( $autoplay, true ); ?>>
                                    <?php esc_html_e( 'Enable Autoplay', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>

                            <div class="bwg-igf-field bwg-igf-autoplay-speed-field">
                                <label for="bwg-igf-autoplay-speed"><?php esc_html_e( 'Autoplay Speed (ms)', 'bwg-instagram-feed' ); ?></label>
                                <input type="number" id="bwg-igf-autoplay-speed" name="autoplay_speed" value="<?php echo esc_attr( $autoplay_speed ); ?>" min="1000" max="10000" step="500">
                                <p class="description"><?php esc_html_e( 'Time between slides in milliseconds (1000ms = 1 second)', 'bwg-instagram-feed' ); ?></p>
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" id="bwg-igf-show-arrows" name="show_arrows" value="1" <?php checked( $show_arrows, true ); ?>>
                                    <?php esc_html_e( 'Show Navigation Arrows', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" id="bwg-igf-show-dots" name="show_dots" value="1" <?php checked( $show_dots, true ); ?>>
                                    <?php esc_html_e( 'Show Pagination Dots', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" id="bwg-igf-infinite-loop" name="infinite_loop" value="1" <?php checked( $infinite_loop, true ); ?>>
                                    <?php esc_html_e( 'Infinite Loop', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>
                        </div>

                        <!-- Responsive Settings -->
                        <div class="bwg-igf-responsive-settings">
                            <h3><?php esc_html_e( 'Responsive Settings', 'bwg-instagram-feed' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'Configure how your feed displays on different screen sizes. Leave rows at 0 to show all posts.', 'bwg-instagram-feed' ); ?></p>

                            <!-- Tablet Settings -->
                            <div class="bwg-igf-responsive-group">
                                <h4><?php esc_html_e( 'Tablet (768px - 1024px)', 'bwg-instagram-feed' ); ?></h4>
                                <div class="bwg-igf-responsive-fields">
                                    <div class="bwg-igf-field">
                                        <label for="bwg-igf-tablet-columns"><?php esc_html_e( 'Columns', 'bwg-instagram-feed' ); ?></label>
                                        <input type="number" id="bwg-igf-tablet-columns" name="tablet_columns" value="<?php echo esc_attr( $tablet_columns ); ?>" min="1" max="6">
                                    </div>
                                    <div class="bwg-igf-field">
                                        <label for="bwg-igf-tablet-rows"><?php esc_html_e( 'Rows', 'bwg-instagram-feed' ); ?></label>
                                        <input type="number" id="bwg-igf-tablet-rows" name="tablet_rows" value="<?php echo esc_attr( $tablet_rows ); ?>" min="0" max="10">
                                        <p class="description"><?php esc_html_e( '0 = show all posts', 'bwg-instagram-feed' ); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Settings -->
                            <div class="bwg-igf-responsive-group">
                                <h4><?php esc_html_e( 'Mobile (< 768px)', 'bwg-instagram-feed' ); ?></h4>
                                <div class="bwg-igf-responsive-fields">
                                    <div class="bwg-igf-field">
                                        <label for="bwg-igf-mobile-columns"><?php esc_html_e( 'Columns', 'bwg-instagram-feed' ); ?></label>
                                        <input type="number" id="bwg-igf-mobile-columns" name="mobile_columns" value="<?php echo esc_attr( $mobile_columns ); ?>" min="1" max="4">
                                    </div>
                                    <div class="bwg-igf-field">
                                        <label for="bwg-igf-mobile-rows"><?php esc_html_e( 'Rows', 'bwg-instagram-feed' ); ?></label>
                                        <input type="number" id="bwg-igf-mobile-rows" name="mobile_rows" value="<?php echo esc_attr( $mobile_rows ); ?>" min="0" max="10">
                                        <p class="description"><?php esc_html_e( '0 = show all posts', 'bwg-instagram-feed' ); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Display Tab -->
                    <div id="bwg-igf-tab-display" class="bwg-igf-tab-content">
                        <?php
                        // Get display settings from feed
                        $display_settings = $feed && ! empty( $feed->display_settings ) ? json_decode( $feed->display_settings, true ) : array();
                        $show_account_name = isset( $display_settings['show_account_name'] ) ? (bool) $display_settings['show_account_name'] : false;
                        $show_likes = isset( $display_settings['show_likes'] ) ? (bool) $display_settings['show_likes'] : true;
                        $show_comments = isset( $display_settings['show_comments'] ) ? (bool) $display_settings['show_comments'] : true;
                        $show_caption = isset( $display_settings['show_caption'] ) ? (bool) $display_settings['show_caption'] : false;
                        $show_follow_button = isset( $display_settings['show_follow_button'] ) ? (bool) $display_settings['show_follow_button'] : true;
                        $follow_button_text = isset( $display_settings['follow_button_text'] ) ? $display_settings['follow_button_text'] : '';
                        $follow_button_style = isset( $display_settings['follow_button_style'] ) ? $display_settings['follow_button_style'] : 'gradient';
                        ?>
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-post-count"><?php esc_html_e( 'Number of Posts', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-post-count" name="post_count" value="<?php echo $feed ? esc_attr( $feed->post_count ) : '9'; ?>" min="1" max="50">
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" id="bwg-igf-show-account-name" name="show_account_name" value="1" <?php checked( $show_account_name, true ); ?>>
                                <?php esc_html_e( 'Show account name', 'bwg-instagram-feed' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Display the Instagram username in the feed header.', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" id="bwg-igf-show-likes" name="show_likes" value="1" <?php checked( $show_likes, true ); ?>>
                                <?php esc_html_e( 'Show like count', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" id="bwg-igf-show-comments" name="show_comments" value="1" <?php checked( $show_comments, true ); ?>>
                                <?php esc_html_e( 'Show comment count', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" name="show_caption" value="1" <?php checked( $show_caption, true ); ?>>
                                <?php esc_html_e( 'Show caption', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" id="bwg-igf-show-follow-button" name="show_follow_button" value="1" <?php checked( $show_follow_button, true ); ?>>
                                <?php esc_html_e( 'Show "Follow on Instagram" button', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <!-- Follow Button Options (shown when follow button is enabled) -->
                        <div class="bwg-igf-follow-button-options" id="bwg-igf-follow-button-options" style="<?php echo $show_follow_button ? '' : 'display: none;'; ?>">
                            <div class="bwg-igf-field">
                                <label for="bwg-igf-follow-button-text"><?php esc_html_e( 'Button Text', 'bwg-instagram-feed' ); ?></label>
                                <input type="text" id="bwg-igf-follow-button-text" name="follow_button_text" value="<?php echo esc_attr( $follow_button_text ); ?>" placeholder="<?php esc_attr_e( 'Follow on Instagram', 'bwg-instagram-feed' ); ?>">
                                <p class="description"><?php esc_html_e( 'Customize the follow button text. Leave empty for default.', 'bwg-instagram-feed' ); ?></p>
                            </div>

                            <div class="bwg-igf-field">
                                <label for="bwg-igf-follow-button-style"><?php esc_html_e( 'Button Style', 'bwg-instagram-feed' ); ?></label>
                                <select id="bwg-igf-follow-button-style" name="follow_button_style">
                                    <option value="gradient" <?php selected( $follow_button_style, 'gradient' ); ?>><?php esc_html_e( 'Instagram Gradient', 'bwg-instagram-feed' ); ?></option>
                                    <option value="solid" <?php selected( $follow_button_style, 'solid' ); ?>><?php esc_html_e( 'Solid Color', 'bwg-instagram-feed' ); ?></option>
                                    <option value="outline" <?php selected( $follow_button_style, 'outline' ); ?>><?php esc_html_e( 'Outline', 'bwg-instagram-feed' ); ?></option>
                                    <option value="minimal" <?php selected( $follow_button_style, 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'bwg-instagram-feed' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Styling Tab -->
                    <div id="bwg-igf-tab-styling" class="bwg-igf-tab-content">
                        <?php
                        // Get existing styling settings for Feed Size options
                        $styling_data = $feed && ! empty( $feed->styling_settings ) ? json_decode( $feed->styling_settings, true ) : array();
                        $feed_width = isset( $styling_data['feed_width'] ) ? $styling_data['feed_width'] : '100%';
                        $feed_max_width = isset( $styling_data['feed_max_width'] ) ? $styling_data['feed_max_width'] : '';
                        $feed_padding = isset( $styling_data['feed_padding'] ) ? absint( $styling_data['feed_padding'] ) : 0;
                        $image_height_mode = isset( $styling_data['image_height_mode'] ) ? $styling_data['image_height_mode'] : 'square';
                        $image_fixed_height = isset( $styling_data['image_fixed_height'] ) ? absint( $styling_data['image_fixed_height'] ) : 200;
                        ?>
                        <h4 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;"><?php esc_html_e( 'Feed Size', 'bwg-instagram-feed' ); ?></h4>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-feed-width"><?php esc_html_e( 'Feed Width', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-feed-width" name="feed_width" value="<?php echo esc_attr( $feed_width ); ?>" placeholder="100%">
                            <p class="description"><?php esc_html_e( 'Width of the feed container (e.g., 100%, 800px, auto). Default: 100%', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-feed-max-width"><?php esc_html_e( 'Feed Max Width', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-feed-max-width" name="feed_max_width" value="<?php echo esc_attr( $feed_max_width ); ?>" placeholder="<?php esc_attr_e( 'e.g., 1200px or none', 'bwg-instagram-feed' ); ?>">
                            <p class="description"><?php esc_html_e( 'Maximum width of the feed (e.g., 1200px, 100%, none). Leave empty for no limit.', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-feed-padding"><?php esc_html_e( 'Feed Padding (px)', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-feed-padding" name="feed_padding" value="<?php echo esc_attr( $feed_padding ); ?>" min="0" max="100">
                            <p class="description"><?php esc_html_e( 'Padding around the feed container. Default: 0', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-image-height-mode"><?php esc_html_e( 'Image Height Mode', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-image-height-mode" name="image_height_mode">
                                <option value="square" <?php selected( $image_height_mode, 'square' ); ?>><?php esc_html_e( 'Square (1:1 aspect ratio)', 'bwg-instagram-feed' ); ?></option>
                                <option value="original" <?php selected( $image_height_mode, 'original' ); ?>><?php esc_html_e( 'Original (preserve aspect ratio)', 'bwg-instagram-feed' ); ?></option>
                                <option value="fixed" <?php selected( $image_height_mode, 'fixed' ); ?>><?php esc_html_e( 'Fixed Height', 'bwg-instagram-feed' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How images should be displayed in the grid.', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <div class="bwg-igf-field bwg-igf-fixed-height-field" id="bwg-igf-fixed-height-field" style="<?php echo 'fixed' !== $image_height_mode ? 'display: none;' : ''; ?>">
                            <label for="bwg-igf-image-fixed-height"><?php esc_html_e( 'Fixed Image Height (px)', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-image-fixed-height" name="image_fixed_height" value="<?php echo esc_attr( $image_fixed_height ); ?>" min="50" max="800">
                            <p class="description"><?php esc_html_e( 'Set a fixed height for all images in the grid.', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <h4 style="margin-top: 25px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;"><?php esc_html_e( 'Appearance', 'bwg-instagram-feed' ); ?></h4>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-background-color"><?php esc_html_e( 'Background Color', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-background-color" name="background_color" class="bwg-igf-color-picker" value="<?php echo $feed && isset( json_decode( $feed->styling_settings, true )['background_color'] ) ? esc_attr( json_decode( $feed->styling_settings, true )['background_color'] ) : ''; ?>" data-default-color="">
                            <p class="description"><?php esc_html_e( 'Leave empty for transparent/no background.', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-border-radius"><?php esc_html_e( 'Border Radius (px)', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-border-radius" name="border_radius" value="<?php echo $feed && isset( json_decode( $feed->styling_settings, true )['border_radius'] ) ? esc_attr( json_decode( $feed->styling_settings, true )['border_radius'] ) : '0'; ?>" min="0" max="50">
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-hover-effect"><?php esc_html_e( 'Hover Effect', 'bwg-instagram-feed' ); ?></label>
                            <?php
                            $hover_effect = 'none';
                            if ( $feed && ! empty( $feed->styling_settings ) ) {
                                $styling = json_decode( $feed->styling_settings, true );
                                $hover_effect = isset( $styling['hover_effect'] ) ? $styling['hover_effect'] : 'none';
                            }
                            ?>
                            <select id="bwg-igf-hover-effect" name="hover_effect">
                                <option value="none" <?php selected( $hover_effect, 'none' ); ?>><?php esc_html_e( 'None', 'bwg-instagram-feed' ); ?></option>
                                <option value="zoom" <?php selected( $hover_effect, 'zoom' ); ?>><?php esc_html_e( 'Zoom', 'bwg-instagram-feed' ); ?></option>
                                <option value="overlay" <?php selected( $hover_effect, 'overlay' ); ?>><?php esc_html_e( 'Overlay', 'bwg-instagram-feed' ); ?></option>
                                <option value="brightness" <?php selected( $hover_effect, 'brightness' ); ?>><?php esc_html_e( 'Brightness', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-custom-css"><?php esc_html_e( 'Custom CSS', 'bwg-instagram-feed' ); ?></label>
                            <textarea id="bwg-igf-custom-css" name="custom_css" rows="5" placeholder=".bwg-igf-feed { /* your styles */ }"><?php
                                if ( $feed && ! empty( $feed->styling_settings ) ) {
                                    $styling = json_decode( $feed->styling_settings, true );
                                    echo esc_textarea( isset( $styling['custom_css'] ) ? $styling['custom_css'] : '' );
                                }
                            ?></textarea>
                            <p class="description"><?php esc_html_e( 'Add custom CSS to style your feed. Use .bwg-igf-feed as the container selector.', 'bwg-instagram-feed' ); ?></p>
                        </div>
                    </div>

                    <!-- Popup Tab -->
                    <div id="bwg-igf-tab-popup" class="bwg-igf-tab-content">
                        <?php
                        // Get popup settings from feed
                        $popup_settings_data = $feed && ! empty( $feed->popup_settings ) ? json_decode( $feed->popup_settings, true ) : array();
                        $popup_enabled = isset( $popup_settings_data['enabled'] ) ? (bool) $popup_settings_data['enabled'] : true;
                        $popup_show_caption = isset( $popup_settings_data['show_caption'] ) ? (bool) $popup_settings_data['show_caption'] : true;
                        $popup_show_likes = isset( $popup_settings_data['show_likes'] ) ? (bool) $popup_settings_data['show_likes'] : true;
                        $popup_show_comments = isset( $popup_settings_data['show_comments'] ) ? (bool) $popup_settings_data['show_comments'] : true;
                        $popup_show_instagram_link = isset( $popup_settings_data['show_instagram_link'] ) ? (bool) $popup_settings_data['show_instagram_link'] : true;
                        $link_to_instagram = isset( $popup_settings_data['link_to_instagram'] ) ? (bool) $popup_settings_data['link_to_instagram'] : false;
                        ?>
                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" id="bwg-igf-popup-enabled" name="popup_enabled" value="1" <?php checked( $popup_enabled, true ); ?>>
                                <?php esc_html_e( 'Enable popup/lightbox', 'bwg-instagram-feed' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Opens posts in a lightbox when clicked.', 'bwg-instagram-feed' ); ?></p>
                        </div>

                        <!-- Link to Instagram Option (shown when popup is DISABLED) -->
                        <div class="bwg-igf-link-to-instagram-option" id="bwg-igf-link-to-instagram-option" style="<?php echo $popup_enabled ? 'display: none;' : ''; ?>">
                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" id="bwg-igf-link-to-instagram" name="link_to_instagram" value="1" <?php checked( $link_to_instagram, true ); ?>>
                                    <?php esc_html_e( 'Link posts to Instagram', 'bwg-instagram-feed' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Make feed items clickable links that open the original Instagram post in a new tab.', 'bwg-instagram-feed' ); ?></p>
                            </div>
                        </div>

                        <!-- Additional Popup Options (shown when popup is enabled) -->
                        <div class="bwg-igf-popup-options" id="bwg-igf-popup-options" style="<?php echo $popup_enabled ? '' : 'display: none;'; ?>">
                            <h4 style="margin-top: 20px; margin-bottom: 15px; padding-top: 15px; border-top: 1px solid #ddd;"><?php esc_html_e( 'Popup Display Options', 'bwg-instagram-feed' ); ?></h4>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" name="popup_show_caption" value="1" <?php checked( $popup_show_caption, true ); ?>>
                                    <?php esc_html_e( 'Show caption in popup', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" name="popup_show_likes" value="1" <?php checked( $popup_show_likes, true ); ?>>
                                    <?php esc_html_e( 'Show like count in popup', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" name="popup_show_comments" value="1" <?php checked( $popup_show_comments, true ); ?>>
                                    <?php esc_html_e( 'Show comment count in popup', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>

                            <div class="bwg-igf-field">
                                <label>
                                    <input type="checkbox" name="popup_show_instagram_link" value="1" <?php checked( $popup_show_instagram_link, true ); ?>>
                                    <?php esc_html_e( 'Show "View on Instagram" link', 'bwg-instagram-feed' ); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Tab -->
                    <div id="bwg-igf-tab-advanced" class="bwg-igf-tab-content">
                        <?php
                        // Get filter settings from feed
                        $filter_settings = $feed && ! empty( $feed->filter_settings ) ? json_decode( $feed->filter_settings, true ) : array();
                        $hashtag_include = isset( $filter_settings['hashtag_include'] ) ? $filter_settings['hashtag_include'] : '';
                        $hashtag_exclude = isset( $filter_settings['hashtag_exclude'] ) ? $filter_settings['hashtag_exclude'] : '';
                        ?>

                        <!-- Hashtag Filters (Connected Feeds Only) -->
                        <div class="bwg-igf-connected-filters" id="bwg-igf-connected-filters" style="display: none;">
                            <h4 style="margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;"><?php esc_html_e( 'Hashtag Filters', 'bwg-instagram-feed' ); ?></h4>

                            <div class="bwg-igf-field">
                                <label for="bwg-igf-hashtag-include"><?php esc_html_e( 'Include Hashtags', 'bwg-instagram-feed' ); ?></label>
                                <input type="text" id="bwg-igf-hashtag-include" name="hashtag_include" value="<?php echo esc_attr( $hashtag_include ); ?>" placeholder="<?php esc_attr_e( 'travel, photography, nature', 'bwg-instagram-feed' ); ?>">
                                <p class="description"><?php esc_html_e( 'Only show posts containing these hashtags (comma-separated, without #). Leave empty to show all posts.', 'bwg-instagram-feed' ); ?></p>
                            </div>

                            <div class="bwg-igf-field">
                                <label for="bwg-igf-hashtag-exclude"><?php esc_html_e( 'Exclude Hashtags', 'bwg-instagram-feed' ); ?></label>
                                <input type="text" id="bwg-igf-hashtag-exclude" name="hashtag_exclude" value="<?php echo esc_attr( $hashtag_exclude ); ?>" placeholder="<?php esc_attr_e( 'ad, sponsored, promo', 'bwg-instagram-feed' ); ?>">
                                <p class="description"><?php esc_html_e( 'Hide posts containing these hashtags (comma-separated, without #).', 'bwg-instagram-feed' ); ?></p>
                            </div>

                            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-ordering"><?php esc_html_e( 'Post Ordering', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-ordering" name="ordering">
                                <option value="newest"><?php esc_html_e( 'Newest First', 'bwg-instagram-feed' ); ?></option>
                                <option value="oldest"><?php esc_html_e( 'Oldest First', 'bwg-instagram-feed' ); ?></option>
                                <option value="random"><?php esc_html_e( 'Random', 'bwg-instagram-feed' ); ?></option>
                                <option value="most_liked"><?php esc_html_e( 'Most Liked', 'bwg-instagram-feed' ); ?></option>
                                <option value="most_commented"><?php esc_html_e( 'Most Commented', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <?php
                        // Get current cache duration from feed or use default from settings
                        $default_cache_duration = get_option( 'bwg_igf_default_cache_duration', 3600 );
                        $current_cache_duration = $feed ? intval( $feed->cache_duration ) : intval( $default_cache_duration );
                        ?>
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-cache-duration"><?php esc_html_e( 'Cache Duration', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-cache-duration" name="cache_duration">
                                <option value="900" <?php selected( $current_cache_duration, 900 ); ?>><?php esc_html_e( '15 Minutes', 'bwg-instagram-feed' ); ?></option>
                                <option value="1800" <?php selected( $current_cache_duration, 1800 ); ?>><?php esc_html_e( '30 Minutes', 'bwg-instagram-feed' ); ?></option>
                                <option value="3600" <?php selected( $current_cache_duration, 3600 ); ?>><?php esc_html_e( '1 Hour', 'bwg-instagram-feed' ); ?></option>
                                <option value="21600" <?php selected( $current_cache_duration, 21600 ); ?>><?php esc_html_e( '6 Hours', 'bwg-instagram-feed' ); ?></option>
                                <option value="43200" <?php selected( $current_cache_duration, 43200 ); ?>><?php esc_html_e( '12 Hours', 'bwg-instagram-feed' ); ?></option>
                                <option value="86400" <?php selected( $current_cache_duration, 86400 ); ?>><?php esc_html_e( '24 Hours', 'bwg-instagram-feed' ); ?></option>
                            </select>
                            <p id="bwg-igf-cache-warning" class="description bwg-igf-cache-warning" style="color: #d63638; display: none;">
                                <?php esc_html_e( '⚠️ Warning: Short cache durations may cause rate limiting from Instagram. Consider using a longer duration unless you need very frequent updates.', 'bwg-instagram-feed' ); ?>
                            </p>
                        </div>

                        <div class="bwg-igf-field bwg-igf-cache-refresh-field">
                            <button type="button" class="button bwg-igf-refresh-cache" data-feed-id="<?php echo esc_attr( $feed_id ); ?>">
                                <?php esc_html_e( 'Refresh Cache Now', 'bwg-instagram-feed' ); ?>
                            </button>
                            <?php
                            // Get cache timestamp if it exists
                            if ( $feed_id > 0 ) {
                                $cache_timestamp = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT created_at FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d ORDER BY created_at DESC LIMIT 1",
                                    $feed_id
                                ) );
                                if ( $cache_timestamp ) {
                                    $formatted_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $cache_timestamp ) );
                                    echo '<p class="description bwg-igf-cache-timestamp">' . esc_html__( 'Last refreshed: ', 'bwg-instagram-feed' ) . esc_html( $formatted_time ) . '</p>';
                                } else {
                                    echo '<p class="description bwg-igf-cache-timestamp">' . esc_html__( 'Cache not yet created', 'bwg-instagram-feed' ) . '</p>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="bwg-igf-editor-footer">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds' ) ); ?>" class="button">
                        <?php esc_html_e( 'Cancel', 'bwg-instagram-feed' ); ?>
                    </a>
                    <button type="submit" class="button button-primary">
                        <?php echo $is_new ? esc_html__( 'Create Feed', 'bwg-instagram-feed' ) : esc_html__( 'Save Changes', 'bwg-instagram-feed' ); ?>
                    </button>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="bwg-igf-preview">
                <h3><?php esc_html_e( 'Preview', 'bwg-instagram-feed' ); ?></h3>
                <div class="bwg-igf-preview-content bwg-igf-grid bwg-igf-grid-3">
                    <?php
                    // Use realistic placeholder images from picsum.photos
                    // Each image has a unique seed to show variety like a real Instagram feed
                    // Feature #49: Some items are marked as videos to show video indicator
                    $placeholder_seeds = array( 10, 22, 35, 48, 51, 64, 77, 83, 96 );
                    $video_indices = array( 2, 5, 7 ); // Items at indices 2, 5, 7 will show video indicator
                    for ( $i = 0; $i < 9; $i++ ) :
                        $seed = $placeholder_seeds[ $i ];
                        $placeholder_url = "https://picsum.photos/seed/{$seed}/400/400";
                        $is_video = in_array( $i, $video_indices, true );
                        $item_class = 'bwg-igf-item' . ( $is_video ? ' bwg-igf-video-preview-item' : '' );
                    ?>
                        <div class="<?php echo esc_attr( $item_class ); ?>" data-placeholder-seed="<?php echo esc_attr( $seed ); ?>" data-media-type="<?php echo $is_video ? 'VIDEO' : 'IMAGE'; ?>">
                            <img src="<?php echo esc_url( $placeholder_url ); ?>" alt="<?php esc_attr_e( 'Preview placeholder', 'bwg-instagram-feed' ); ?>" loading="lazy">
                            <?php if ( $is_video ) : ?>
                            <!-- Feature #49: Video indicator icon -->
                            <div class="bwg-igf-preview-video-icon" aria-label="<?php esc_attr_e( 'Video', 'bwg-instagram-feed' ); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </form>
</div>
