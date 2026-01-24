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

        // Get layout settings.
        $layout_type = isset( $_POST['layout_type'] ) ? sanitize_text_field( wp_unslash( $_POST['layout_type'] ) ) : 'grid';
        $columns = isset( $_POST['columns'] ) ? absint( $_POST['columns'] ) : 3;
        $gap = isset( $_POST['gap'] ) ? absint( $_POST['gap'] ) : 10;

        // Get display settings.
        $post_count = isset( $_POST['post_count'] ) ? absint( $_POST['post_count'] ) : 9;
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
            'columns' => $columns,
            'gap'     => $gap,
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
            wp_send_json_error( array( 'message' => __( 'Invalid username format.', 'bwg-instagram-feed' ) ) );
        }

        // For now, just return success since we can't verify Instagram accounts without their API.
        // In production, this would make an API call to verify the account exists and is public.
        wp_send_json_success( array( 'message' => __( 'Username format is valid.', 'bwg-instagram-feed' ) ) );
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

        // Delete existing cache for this feed.
        $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_cache',
            array( 'feed_id' => $feed_id ),
            array( '%d' )
        );

        // Return the current timestamp for display
        $current_time = current_time( 'mysql' );
        $formatted_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $current_time ) );

        wp_send_json_success( array(
            'message'   => __( 'Cache refreshed successfully!', 'bwg-instagram-feed' ),
            'timestamp' => $formatted_time,
        ) );
    }

    /**
     * Clear all cache.
     */
    public function clear_all_cache() {
        $this->verify_request();

        global $wpdb;

        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bwg_igf_cache" );

        wp_send_json_success( array( 'message' => __( 'All cache cleared successfully!', 'bwg-instagram-feed' ) ) );
    }
}

// Initialize.
new BWG_IGF_Admin_Ajax();
