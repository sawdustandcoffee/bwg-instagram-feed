<?php
/**
 * Admin AJAX Handler
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin AJAX Handler Class
 */
class BWG_IGF_Admin_Ajax {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_ajax_actions();
    }

    /**
     * Register AJAX actions.
     */
    private function register_ajax_actions() {
        // Save feed.
        add_action( 'wp_ajax_bwg_igf_save_feed', array( $this, 'save_feed' ) );

        // Delete feed.
        add_action( 'wp_ajax_bwg_igf_delete_feed', array( $this, 'delete_feed' ) );

        // Duplicate feed.
        add_action( 'wp_ajax_bwg_igf_duplicate_feed', array( $this, 'duplicate_feed' ) );

        // Validate username.
        add_action( 'wp_ajax_bwg_igf_validate_username', array( $this, 'validate_username' ) );

        // Refresh cache.
        add_action( 'wp_ajax_bwg_igf_refresh_cache', array( $this, 'refresh_cache' ) );

        // Clear all cache.
        add_action( 'wp_ajax_bwg_igf_clear_all_cache', array( $this, 'clear_all_cache' ) );

        // Connect Instagram account.
        add_action( 'wp_ajax_bwg_igf_connect_account', array( $this, 'connect_account' ) );

        // Disconnect Instagram account.
        add_action( 'wp_ajax_bwg_igf_disconnect_account', array( $this, 'disconnect_account' ) );

        // Verify token encryption (for testing/admin).
        add_action( 'wp_ajax_bwg_igf_verify_token_encryption', array( $this, 'verify_token_encryption' ) );
    }

    /**
     * Verify nonce and capability.
     *
     * @return bool True if valid, sends error response otherwise.
     */
    private function verify_request() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bwg_igf_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'bwg-instagram-feed' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'bwg-instagram-feed' ) ) );
        }

        return true;
    }

    /**
     * Generate a unique slug from feed name.
     *
     * @param string $name Feed name.
     * @param int    $exclude_id Feed ID to exclude from uniqueness check.
     * @return string Unique slug.
     */
    private function generate_slug( $name, $exclude_id = 0 ) {
        global $wpdb;

        $slug = sanitize_title( $name );
        $original_slug = $slug;
        $counter = 1;

        while ( true ) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s AND id != %d",
                    $slug,
                    $exclude_id
                )
            );

            if ( ! $existing ) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Save feed (create or update).
     */
    public function save_feed() {
        $this->verify_request();

        global $wpdb;

        // Get and sanitize feed data.
        $feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

        // Validate required fields.
        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Feed name is required.', 'bwg-instagram-feed' ) ) );
        }

        // Get feed type and usernames.
        $feed_type = isset( $_POST['feed_type'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_type'] ) ) : 'public';
        $instagram_usernames = isset( $_POST['instagram_usernames'] ) ? sanitize_text_field( wp_unslash( $_POST['instagram_usernames'] ) ) : '';

        // Validate Instagram usernames for public feeds.
        if ( 'public' === $feed_type ) {
            if ( empty( $instagram_usernames ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Instagram username is required for public feeds.', 'bwg-instagram-feed' ),
                    'field' => 'instagram_usernames',
                ) );
            }

            // Parse usernames (comma-separated).
            $usernames_array = array_map( 'trim', explode( ',', $instagram_usernames ) );
            $usernames_array = array_filter( $usernames_array );

            if ( empty( $usernames_array ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Please enter at least one valid Instagram username.', 'bwg-instagram-feed' ),
                    'field' => 'instagram_usernames',
                ) );
            }

            // Validate each username.
            $instagram_api = new BWG_IGF_Instagram_API();
            $validation_errors = array();

            foreach ( $usernames_array as $username ) {
                // Basic format validation first.
                if ( ! preg_match( '/^[a-zA-Z0-9_.]+$/', $username ) ) {
                    $validation_errors[] = sprintf(
                        /* translators: %s: Instagram username */
                        __( '"%s" is not a valid Instagram username format.', 'bwg-instagram-feed' ),
                        esc_html( $username )
                    );
                    continue;
                }

                // Validate against Instagram.
                $result = $instagram_api->validate_username( $username );

                if ( ! $result['valid'] ) {
                    if ( ! $result['exists'] ) {
                        $validation_errors[] = sprintf(
                            /* translators: %s: Instagram username */
                            __( 'Instagram user "@%s" was not found. Please check the spelling and try again.', 'bwg-instagram-feed' ),
                            esc_html( $username )
                        );
                    } elseif ( $result['is_private'] ) {
                        $validation_errors[] = sprintf(
                            /* translators: %s: Instagram username */
                            __( 'The Instagram account "@%s" is private. Only public accounts can be displayed.', 'bwg-instagram-feed' ),
                            esc_html( $username )
                        );
                    } else {
                        $validation_errors[] = sprintf(
                            /* translators: 1: Instagram username, 2: error message */
                            __( 'Error validating "@%1$s": %2$s', 'bwg-instagram-feed' ),
                            esc_html( $username ),
                            esc_html( $result['error'] )
                        );
                    }
                }
            }

            // If there are validation errors, return them.
            if ( ! empty( $validation_errors ) ) {
                wp_send_json_error( array(
                    'message' => implode( ' ', $validation_errors ),
                    'field' => 'instagram_usernames',
                    'errors' => $validation_errors,
                ) );
            }
        }

        // Get layout settings.
        $layout_type = isset( $_POST['layout_type'] ) ? sanitize_text_field( wp_unslash( $_POST['layout_type'] ) ) : 'grid';
        $columns = isset( $_POST['columns'] ) ? absint( $_POST['columns'] ) : 3;
        // Enforce column count range: minimum 1, maximum 6.
        $columns = max( 1, min( 6, $columns ) );
        $gap = isset( $_POST['gap'] ) ? absint( $_POST['gap'] ) : 10;

        // Get slider-specific settings.
        $slides_to_show = isset( $_POST['slides_to_show'] ) ? absint( $_POST['slides_to_show'] ) : 3;
        $slides_to_scroll = isset( $_POST['slides_to_scroll'] ) ? absint( $_POST['slides_to_scroll'] ) : 1;
        $autoplay = isset( $_POST['autoplay'] ) ? 1 : 0;
        $autoplay_speed = isset( $_POST['autoplay_speed'] ) ? absint( $_POST['autoplay_speed'] ) : 3000;
        $show_arrows = isset( $_POST['show_arrows'] ) ? 1 : 0;
        $show_dots = isset( $_POST['show_dots'] ) ? 1 : 0;
        $infinite_loop = isset( $_POST['infinite_loop'] ) ? 1 : 0;

        // Get display settings.
        $post_count = isset( $_POST['post_count'] ) ? absint( $_POST['post_count'] ) : 9;
        // Enforce post count range: minimum 1, maximum 50.
        $post_count = max( 1, min( 50, $post_count ) );
        $show_likes = isset( $_POST['show_likes'] ) ? 1 : 0;
        $show_comments = isset( $_POST['show_comments'] ) ? 1 : 0;
        $show_caption = isset( $_POST['show_caption'] ) ? 1 : 0;
        $show_follow_button = isset( $_POST['show_follow_button'] ) ? 1 : 0;

        // Get styling settings.
        $background_color = isset( $_POST['background_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['background_color'] ) ) : '';
        $border_radius = isset( $_POST['border_radius'] ) ? absint( $_POST['border_radius'] ) : 0;
        $hover_effect = isset( $_POST['hover_effect'] ) ? sanitize_text_field( wp_unslash( $_POST['hover_effect'] ) ) : 'none';
        $custom_css = isset( $_POST['custom_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ) ) : '';

        // Get popup settings.
        $popup_enabled = isset( $_POST['popup_enabled'] ) ? 1 : 0;

        // Get advanced settings.
        $ordering = isset( $_POST['ordering'] ) ? sanitize_text_field( wp_unslash( $_POST['ordering'] ) ) : 'newest';
        $cache_duration = isset( $_POST['cache_duration'] ) ? absint( $_POST['cache_duration'] ) : 3600;

        // Build JSON settings objects.
        $layout_settings = wp_json_encode( array(
            'columns'          => $columns,
            'gap'              => $gap,
            'slides_to_show'   => $slides_to_show,
            'slides_to_scroll' => $slides_to_scroll,
            'autoplay'         => (bool) $autoplay,
            'autoplay_speed'   => $autoplay_speed,
            'show_arrows'      => (bool) $show_arrows,
            'show_dots'        => (bool) $show_dots,
            'infinite_loop'    => (bool) $infinite_loop,
        ) );

        $display_settings = wp_json_encode( array(
            'show_likes'         => $show_likes,
            'show_comments'      => $show_comments,
            'show_caption'       => $show_caption,
            'show_follow_button' => $show_follow_button,
        ) );

        $styling_settings = wp_json_encode( array(
            'background_color' => $background_color,
            'border_radius'    => $border_radius,
            'hover_effect'     => $hover_effect,
            'custom_css'       => $custom_css,
        ) );

        $popup_settings = wp_json_encode( array(
            'enabled' => $popup_enabled,
        ) );

        // Generate slug.
        $slug = $this->generate_slug( $name, $feed_id );

        // Prepare data array.
        $data = array(
            'name'                => $name,
            'slug'                => $slug,
            'feed_type'           => $feed_type,
            'instagram_usernames' => $instagram_usernames,
            'layout_type'         => $layout_type,
            'layout_settings'     => $layout_settings,
            'display_settings'    => $display_settings,
            'styling_settings'    => $styling_settings,
            'popup_settings'      => $popup_settings,
            'post_count'          => $post_count,
            'ordering'            => $ordering,
            'cache_duration'      => $cache_duration,
            'status'              => 'active',
        );

        $format = array(
            '%s', // name.
            '%s', // slug.
            '%s', // feed_type.
            '%s', // instagram_usernames.
            '%s', // layout_type.
            '%s', // layout_settings.
            '%s', // display_settings.
            '%s', // styling_settings.
            '%s', // popup_settings.
            '%d', // post_count.
            '%s', // ordering.
            '%d', // cache_duration.
            '%s', // status.
        );

        if ( $feed_id > 0 ) {
            // Update existing feed.
            $data['updated_at'] = current_time( 'mysql' );
            $format[] = '%s'; // updated_at.

            $result = $wpdb->update(
                $wpdb->prefix . 'bwg_igf_feeds',
                $data,
                array( 'id' => $feed_id ),
                $format,
                array( '%d' )
            );

            if ( false === $result ) {
                wp_send_json_error( array( 'message' => __( 'Failed to update feed.', 'bwg-instagram-feed' ) ) );
            }

            wp_send_json_success( array(
                'message' => __( 'Feed updated successfully!', 'bwg-instagram-feed' ),
                'feed_id' => $feed_id,
            ) );
        } else {
            // Create new feed.
            $data['created_at'] = current_time( 'mysql' );
            $data['updated_at'] = current_time( 'mysql' );
            $format[] = '%s'; // created_at.
            $format[] = '%s'; // updated_at.

            $result = $wpdb->insert(
                $wpdb->prefix . 'bwg_igf_feeds',
                $data,
                $format
            );

            if ( false === $result ) {
                wp_send_json_error( array( 'message' => __( 'Failed to create feed.', 'bwg-instagram-feed' ) ) );
            }

            $new_feed_id = $wpdb->insert_id;

            wp_send_json_success( array(
                'message'   => __( 'Feed created successfully!', 'bwg-instagram-feed' ),
                'feed_id'   => $new_feed_id,
                'shortcode' => '[bwg_igf id="' . $new_feed_id . '"]',
                'redirect'  => admin_url( 'admin.php?page=bwg-igf-feeds' ),
            ) );
        }
    }

    /**
     * Delete feed.
     */
    public function delete_feed() {
        $this->verify_request();

        global $wpdb;

        $feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

        if ( ! $feed_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'bwg-instagram-feed' ) ) );
        }

        // Check if feed exists first.
        $feed_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
                $feed_id
            )
        );

        if ( ! $feed_exists ) {
            wp_send_json_error( array( 'message' => __( 'Feed not found.', 'bwg-instagram-feed' ) ) );
        }

        // Delete feed.
        $result = $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_feeds',
            array( 'id' => $feed_id ),
            array( '%d' )
        );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete feed.', 'bwg-instagram-feed' ) ) );
        }

        // Delete associated cache.
        $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_cache',
            array( 'feed_id' => $feed_id ),
            array( '%d' )
        );

        wp_send_json_success( array( 'message' => __( 'Feed deleted successfully!', 'bwg-instagram-feed' ) ) );
    }

    /**
     * Duplicate feed.
     */
    public function duplicate_feed() {
        $this->verify_request();

        global $wpdb;

        $feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

        if ( ! $feed_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'bwg-instagram-feed' ) ) );
        }

        // Get existing feed.
        $feed = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
                $feed_id
            ),
            ARRAY_A
        );

        if ( ! $feed ) {
            wp_send_json_error( array( 'message' => __( 'Feed not found.', 'bwg-instagram-feed' ) ) );
        }

        // Remove id and modify name.
        unset( $feed['id'] );
        $feed['name'] = $feed['name'] . ' (Copy)';
        $feed['slug'] = $this->generate_slug( $feed['name'] );
        $feed['created_at'] = current_time( 'mysql' );
        $feed['updated_at'] = current_time( 'mysql' );

        // Insert duplicate.
        $result = $wpdb->insert(
            $wpdb->prefix . 'bwg_igf_feeds',
            $feed
        );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to duplicate feed.', 'bwg-instagram-feed' ) ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Feed duplicated successfully!', 'bwg-instagram-feed' ),
            'redirect' => admin_url( 'admin.php?page=bwg-igf-feeds' ),
        ) );
    }

    /**
     * Validate Instagram username.
     */
    public function validate_username() {
        $this->verify_request();

        $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';

        if ( empty( $username ) ) {
            wp_send_json_error( array( 'message' => __( 'Username is required.', 'bwg-instagram-feed' ) ) );
        }

        // Basic username format validation.
        if ( ! preg_match( '/^[a-zA-Z0-9_.]+$/', $username ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid username format. Instagram usernames can only contain letters, numbers, periods, and underscores.', 'bwg-instagram-feed' ),
                'error_type' => 'invalid_format',
            ) );
        }

        // Actually validate the username exists on Instagram.
        $instagram_api = new BWG_IGF_Instagram_API();
        $result = $instagram_api->validate_username( $username );

        if ( ! $result['valid'] ) {
            $error_message = $result['error'];
            $error_type = 'validation_failed';

            // Provide clear, helpful error messages based on the error type.
            if ( ! $result['exists'] ) {
                $error_message = sprintf(
                    /* translators: %s: Instagram username */
                    __( 'Instagram user "@%s" was not found. Please check the spelling and try again.', 'bwg-instagram-feed' ),
                    esc_html( $username )
                );
                $error_type = 'user_not_found';
            } elseif ( $result['is_private'] ) {
                $error_message = sprintf(
                    /* translators: %s: Instagram username */
                    __( 'The Instagram account "@%s" is private. Only public accounts can be displayed in feeds.', 'bwg-instagram-feed' ),
                    esc_html( $username )
                );
                $error_type = 'private_account';
            }

            wp_send_json_error( array(
                'message' => $error_message,
                'error_type' => $error_type,
            ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: Instagram username */
                __( 'Instagram user "@%s" is valid and public.', 'bwg-instagram-feed' ),
                esc_html( $username )
            ),
        ) );
    }

    /**
     * Refresh feed cache.
     */
    public function refresh_cache() {
        $this->verify_request();

        global $wpdb;

        $feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

        if ( ! $feed_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'bwg-instagram-feed' ) ) );
        }

        // Get the full feed data.
        $feed = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
                $feed_id
            )
        );

        if ( ! $feed ) {
            wp_send_json_error( array( 'message' => __( 'Feed not found.', 'bwg-instagram-feed' ) ) );
        }

        // Delete existing cache for this feed.
        $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_cache',
            array( 'feed_id' => $feed_id ),
            array( '%d' )
        );

        // Fetch fresh data from Instagram
        $posts = $this->fetch_instagram_data( $feed );

        if ( is_wp_error( $posts ) ) {
            // Update feed with error status
            $wpdb->update(
                $wpdb->prefix . 'bwg_igf_feeds',
                array(
                    'status'        => 'error',
                    'error_message' => $posts->get_error_message(),
                ),
                array( 'id' => $feed_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            wp_send_json_error( array(
                'message' => sprintf(
                    __( 'Failed to fetch Instagram data: %s', 'bwg-instagram-feed' ),
                    $posts->get_error_message()
                ),
            ) );
        }

        // Store in cache
        $cache_duration = absint( $feed->cache_duration ) ?: 3600;
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + $cache_duration );

        $wpdb->insert(
            $wpdb->prefix . 'bwg_igf_cache',
            array(
                'feed_id'    => $feed_id,
                'cache_key'  => 'feed_' . $feed_id,
                'cache_data' => wp_json_encode( $posts ),
                'created_at' => current_time( 'mysql' ),
                'expires_at' => $expires_at,
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        // Clear any error status
        $wpdb->update(
            $wpdb->prefix . 'bwg_igf_feeds',
            array(
                'status'        => 'active',
                'error_message' => null,
            ),
            array( 'id' => $feed_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        // Return the current timestamp for display
        $current_time = current_time( 'mysql' );
        $formatted_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $current_time ) );

        wp_send_json_success( array(
            'message'    => sprintf(
                __( 'Cache refreshed successfully! Fetched %d posts.', 'bwg-instagram-feed' ),
                count( $posts )
            ),
            'timestamp'  => $formatted_time,
            'post_count' => count( $posts ),
        ) );
    }

    /**
     * Fetch Instagram data for a feed.
     *
     * @param object $feed Feed database row.
     * @return array|WP_Error Array of posts or WP_Error.
     */
    private function fetch_instagram_data( $feed ) {
        $instagram_api = new BWG_IGF_Instagram_API();
        $post_count = absint( $feed->post_count ) ?: 9;

        if ( 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) ) {
            // Fetch using connected account with automatic token refresh.
            // maybe_refresh_token checks expiration and refreshes if within 7 days.
            $access_token = $instagram_api->maybe_refresh_token( $feed->connected_account_id );

            if ( is_wp_error( $access_token ) ) {
                return $access_token;
            }

            return $instagram_api->fetch_connected_posts( $access_token, $post_count );
        } else {
            // Fetch from public profile(s)
            $usernames = $feed->instagram_usernames;

            if ( empty( $usernames ) ) {
                return new WP_Error( 'no_username', __( 'No Instagram username configured.', 'bwg-instagram-feed' ) );
            }

            // Parse usernames (could be JSON array or comma-separated)
            $parsed_usernames = json_decode( $usernames, true );
            if ( ! is_array( $parsed_usernames ) ) {
                $parsed_usernames = array_map( 'trim', explode( ',', $usernames ) );
            }
            $parsed_usernames = array_filter( $parsed_usernames );

            if ( count( $parsed_usernames ) === 1 ) {
                return $instagram_api->fetch_public_posts( $parsed_usernames[0], $post_count );
            } else {
                return $instagram_api->fetch_combined_posts( $parsed_usernames, $post_count );
            }
        }
    }

    /**
     * Clear all cache.
     */
    public function clear_all_cache() {
        $this->verify_request();

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- TRUNCATE is intentional for cache clearing
        $wpdb->query(
            $wpdb->prepare(
                'TRUNCATE TABLE %i',
                $wpdb->prefix . 'bwg_igf_cache'
            )
        );

        wp_send_json_success( array( 'message' => __( 'All cache cleared successfully!', 'bwg-instagram-feed' ) ) );
    }

    /**
     * Connect Instagram account.
     *
     * In a real implementation, this would be called after the OAuth callback
     * with the access token from Instagram. For testing, we simulate this process.
     */
    public function connect_account() {
        $this->verify_request();

        global $wpdb;

        // Get the access token from the request.
        // In production, this comes from Instagram OAuth callback.
        $access_token = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';
        $instagram_user_id = isset( $_POST['instagram_user_id'] ) ? absint( $_POST['instagram_user_id'] ) : 0;
        $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
        $account_type = isset( $_POST['account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['account_type'] ) ) : 'basic';

        // Validate required fields.
        if ( empty( $access_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Access token is required.', 'bwg-instagram-feed' ) ) );
        }

        if ( empty( $instagram_user_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Instagram user ID is required.', 'bwg-instagram-feed' ) ) );
        }

        if ( empty( $username ) ) {
            wp_send_json_error( array( 'message' => __( 'Username is required.', 'bwg-instagram-feed' ) ) );
        }

        // Check if encryption is available.
        if ( ! BWG_IGF_Encryption::is_encryption_available() ) {
            // Log warning but continue with fallback.
            error_log( 'BWG Instagram Feed: OpenSSL encryption not available. Using fallback encoding.' );
        }

        // Encrypt the access token before storing.
        $encrypted_token = BWG_IGF_Encryption::encrypt( $access_token );

        if ( false === $encrypted_token ) {
            wp_send_json_error( array( 'message' => __( 'Failed to encrypt access token.', 'bwg-instagram-feed' ) ) );
        }

        // Calculate expiration (Instagram Basic Display API tokens expire in 60 days).
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 60 * DAY_IN_SECONDS ) );

        // Check if this Instagram account is already connected.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}bwg_igf_accounts WHERE instagram_user_id = %d",
                $instagram_user_id
            )
        );

        if ( $existing ) {
            // Update existing account.
            $result = $wpdb->update(
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

            if ( false === $result ) {
                wp_send_json_error( array( 'message' => __( 'Failed to update account.', 'bwg-instagram-feed' ) ) );
            }

            wp_send_json_success( array(
                'message'    => __( 'Instagram account reconnected successfully!', 'bwg-instagram-feed' ),
                'account_id' => $existing->id,
            ) );
        } else {
            // Insert new account.
            $result = $wpdb->insert(
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

            if ( false === $result ) {
                wp_send_json_error( array( 'message' => __( 'Failed to connect account.', 'bwg-instagram-feed' ) ) );
            }

            $new_account_id = $wpdb->insert_id;

            wp_send_json_success( array(
                'message'    => __( 'Instagram account connected successfully!', 'bwg-instagram-feed' ),
                'account_id' => $new_account_id,
            ) );
        }
    }

    /**
     * Disconnect Instagram account.
     */
    public function disconnect_account() {
        $this->verify_request();

        global $wpdb;

        $account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;

        if ( ! $account_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid account ID.', 'bwg-instagram-feed' ) ) );
        }

        // Delete the account.
        $result = $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_accounts',
            array( 'id' => $account_id ),
            array( '%d' )
        );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to disconnect account.', 'bwg-instagram-feed' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Account disconnected successfully!', 'bwg-instagram-feed' ) ) );
    }

    /**
     * Verify token encryption status for an account.
     *
     * This is used by administrators and for testing to verify that
     * OAuth tokens are properly encrypted in the database.
     */
    public function verify_token_encryption() {
        $this->verify_request();

        global $wpdb;

        $account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;

        if ( ! $account_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid account ID.', 'bwg-instagram-feed' ) ) );
        }

        // Get the account.
        $account = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, username, access_token FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
                $account_id
            )
        );

        if ( ! $account ) {
            wp_send_json_error( array( 'message' => __( 'Account not found.', 'bwg-instagram-feed' ) ) );
        }

        // Verify the token encryption status.
        $verification = BWG_IGF_Encryption::verify_encrypted( $account->access_token );

        // Determine the raw token format for display (show first/last few chars only).
        $token_preview = '';
        if ( strlen( $account->access_token ) > 20 ) {
            $token_preview = substr( $account->access_token, 0, 15 ) . '...' . substr( $account->access_token, -5 );
        } else {
            $token_preview = $account->access_token;
        }

        wp_send_json_success( array(
            'account_id'        => $account->id,
            'username'          => $account->username,
            'token_preview'     => $token_preview,
            'is_encrypted'      => $verification['is_encrypted'],
            'encryption_method' => $verification['encryption_method'],
            'is_plaintext'      => $verification['is_plaintext'],
        ) );
    }
}

// Initialize.
new BWG_IGF_Admin_Ajax();
