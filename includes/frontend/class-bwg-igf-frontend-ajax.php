<?php
/**
 * Frontend AJAX Handler
 *
 * Handles public-facing AJAX requests for loading feed data.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend AJAX Handler Class
 */
class BWG_IGF_Frontend_Ajax {

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
        // Load feed data (public action - works for logged-in and non-logged-in users).
        add_action( 'wp_ajax_bwg_igf_load_feed', array( $this, 'load_feed' ) );
        add_action( 'wp_ajax_nopriv_bwg_igf_load_feed', array( $this, 'load_feed' ) );
    }

    /**
     * Load feed data via AJAX.
     *
     * This is called when a feed doesn't have cached data and needs to
     * fetch fresh data from Instagram asynchronously.
     */
    public function load_feed() {
        // Verify nonce for security (using the frontend nonce).
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bwg_igf_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'bwg-instagram-feed' ) ) );
        }

        global $wpdb;

        $feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

        if ( ! $feed_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'bwg-instagram-feed' ) ) );
        }

        // Get the feed data.
        $feed = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d AND status = 'active'",
                $feed_id
            )
        );

        if ( ! $feed ) {
            wp_send_json_error( array( 'message' => __( 'Feed not found or inactive.', 'bwg-instagram-feed' ) ) );
        }

        // Check if we have cached data first.
        $cache_data = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cache_data FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
                $feed_id
            )
        );

        if ( $cache_data ) {
            // Return cached data.
            $posts = json_decode( $cache_data, true ) ?: array();
            $this->send_feed_response( $feed, $posts );
            return;
        }

        // No cache - fetch from Instagram.
        $posts = $this->fetch_instagram_data( $feed );

        if ( is_wp_error( $posts ) ) {
            $error_code = $posts->get_error_code();
            $error_message = $posts->get_error_message();

            // Feature #17: User-friendly rate limit error messages
            if ( 'rate_limited' === $error_code || 'backoff_active' === $error_code ) {
                wp_send_json_error( array(
                    'message'       => __( 'Instagram is temporarily limiting requests. Please wait a few minutes and try again later. We apologize for the inconvenience.', 'bwg-instagram-feed' ),
                    'error_code'    => $error_code,
                    'is_rate_limit' => true,
                ) );
            }

            wp_send_json_error( array(
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __( 'Could not fetch Instagram posts: %s', 'bwg-instagram-feed' ),
                    $error_message
                ),
                'error_code' => $error_code,
            ) );
        }

        // Cache the fetched posts.
        if ( ! empty( $posts ) ) {
            $cache_duration = absint( $feed->cache_duration ) ?: 3600;
            $expires_at = gmdate( 'Y-m-d H:i:s', time() + $cache_duration );
            $cache_key = 'feed_' . $feed_id . '_' . md5( wp_json_encode( $posts ) );

            // Delete old cache entries for this feed.
            $wpdb->delete(
                $wpdb->prefix . 'bwg_igf_cache',
                array( 'feed_id' => $feed_id ),
                array( '%d' )
            );

            // Insert new cache entry.
            $wpdb->insert(
                $wpdb->prefix . 'bwg_igf_cache',
                array(
                    'feed_id'    => $feed_id,
                    'cache_key'  => $cache_key,
                    'cache_data' => wp_json_encode( $posts ),
                    'created_at' => current_time( 'mysql' ),
                    'expires_at' => $expires_at,
                ),
                array( '%d', '%s', '%s', '%s', '%s' )
            );
        }

        $this->send_feed_response( $feed, $posts );
    }

    /**
     * Send the feed response with posts data.
     *
     * @param object $feed Feed database row.
     * @param array  $posts Array of post data.
     */
    private function send_feed_response( $feed, $posts ) {
        // Parse settings.
        $display_settings = json_decode( $feed->display_settings, true ) ?: array();
        $layout_settings = json_decode( $feed->layout_settings, true ) ?: array();
        $styling_settings = json_decode( $feed->styling_settings, true ) ?: array();

        // Apply ordering.
        $ordering = isset( $feed->ordering ) ? $feed->ordering : 'newest';
        $posts = $this->apply_ordering( $posts, $ordering );

        // Get usernames for follow button.
        $usernames = json_decode( $feed->instagram_usernames, true );
        if ( ! is_array( $usernames ) ) {
            $usernames = array_map( 'trim', explode( ',', $feed->instagram_usernames ) );
        }
        $first_username = ! empty( $usernames ) ? $usernames[0] : '';

        wp_send_json_success( array(
            'posts'            => $posts,
            'post_count'       => count( $posts ),
            'display_settings' => $display_settings,
            'layout_settings'  => $layout_settings,
            'styling_settings' => $styling_settings,
            'layout_type'      => $feed->layout_type,
            'first_username'   => $first_username,
        ) );
    }

    /**
     * Fetch Instagram data for a feed.
     *
     * @param object $feed Feed database row.
     * @return array|WP_Error Array of posts or WP_Error.
     */
    private function fetch_instagram_data( $feed ) {
        // Load the Instagram API class if not already loaded.
        if ( ! class_exists( 'BWG_IGF_Instagram_API' ) ) {
            require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-api.php';
        }

        $instagram_api = new BWG_IGF_Instagram_API();
        $post_count = absint( $feed->post_count ) ?: 9;

        if ( 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) ) {
            // Feature #24: Check for cache warming data first.
            // When an account is connected, posts are pre-fetched and stored in a transient.
            // This allows feeds using that account to display immediately without additional API calls.
            $warmed_cache = get_transient( 'bwg_igf_account_cache_' . $feed->connected_account_id );
            if ( ! empty( $warmed_cache ) && ! empty( $warmed_cache['posts'] ) ) {
                // Use warmed cache data (limit to post_count).
                $posts = array_slice( $warmed_cache['posts'], 0, $post_count );
                // Note: Don't delete the transient - it can be used by other feeds using this account.
                return $posts;
            }

            // No warmed cache - fetch using connected account with automatic token refresh.
            // Load encryption class if needed.
            if ( ! class_exists( 'BWG_IGF_Encryption' ) ) {
                require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-encryption.php';
            }

            // maybe_refresh_token checks expiration and refreshes if within 7 days.
            $access_token = $instagram_api->maybe_refresh_token( $feed->connected_account_id );

            if ( is_wp_error( $access_token ) ) {
                return $access_token;
            }

            return $instagram_api->fetch_connected_posts( $access_token, $post_count );
        } else {
            // Fetch from public profile(s).
            $usernames = $feed->instagram_usernames;

            if ( empty( $usernames ) ) {
                return new WP_Error( 'no_username', __( 'No Instagram username configured.', 'bwg-instagram-feed' ) );
            }

            // Parse usernames (could be JSON array or comma-separated).
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
     * Apply ordering to posts array.
     *
     * @param array  $posts    Posts array.
     * @param string $ordering Ordering type.
     * @return array Ordered posts.
     */
    private function apply_ordering( $posts, $ordering ) {
        if ( empty( $posts ) ) {
            return $posts;
        }

        switch ( $ordering ) {
            case 'newest':
                usort( $posts, function( $a, $b ) {
                    $time_a = isset( $a['timestamp'] ) ? $a['timestamp'] : 0;
                    $time_b = isset( $b['timestamp'] ) ? $b['timestamp'] : 0;
                    return $time_b - $time_a;
                });
                break;

            case 'oldest':
                usort( $posts, function( $a, $b ) {
                    $time_a = isset( $a['timestamp'] ) ? $a['timestamp'] : 0;
                    $time_b = isset( $b['timestamp'] ) ? $b['timestamp'] : 0;
                    return $time_a - $time_b;
                });
                break;

            case 'random':
                shuffle( $posts );
                break;

            case 'most_liked':
                usort( $posts, function( $a, $b ) {
                    $likes_a = isset( $a['likes'] ) ? intval( $a['likes'] ) : 0;
                    $likes_b = isset( $b['likes'] ) ? intval( $b['likes'] ) : 0;
                    return $likes_b - $likes_a;
                });
                break;

            case 'most_commented':
                usort( $posts, function( $a, $b ) {
                    $comments_a = isset( $a['comments'] ) ? intval( $a['comments'] ) : 0;
                    $comments_b = isset( $b['comments'] ) ? intval( $b['comments'] ) : 0;
                    return $comments_b - $comments_a;
                });
                break;
        }

        return $posts;
    }
}

// Initialize.
new BWG_IGF_Frontend_Ajax();
