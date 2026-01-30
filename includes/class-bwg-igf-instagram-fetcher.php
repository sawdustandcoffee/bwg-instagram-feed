<?php
/**
 * Instagram Data Fetcher
 *
 * Handles fetching Instagram data for public profiles and caching the results.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Instagram Fetcher Class
 *
 * Provides methods to fetch Instagram data and manage cache.
 */
class BWG_IGF_Instagram_Fetcher {

    /**
     * Fetch posts for a feed and cache them.
     *
     * Feature #58: Placeholder data is NOT cached to prevent showing fake images on frontend.
     * Only real Instagram data gets cached with the full duration.
     *
     * @param object $feed The feed object from database.
     * @return array Array of post data.
     */
    public static function fetch_and_cache( $feed ) {
        global $wpdb;

        // Check if we have valid cached data first.
        $cache_data = $wpdb->get_var( $wpdb->prepare(
            "SELECT cache_data FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            $feed->id
        ) );

        if ( $cache_data ) {
            $posts = json_decode( $cache_data, true );
            if ( ! empty( $posts ) ) {
                // Feature #58: Check if cached data is placeholder data (from previous versions)
                // If it is, skip the cache and try to fetch fresh data
                if ( ! self::is_placeholder_data( $posts ) ) {
                    return $posts;
                }
                // Log that we're skipping cached placeholder data
                error_log( 'BWG IGF: Skipping cached placeholder data for feed #' . $feed->id . ' - attempting fresh fetch' );
            }
        }

        // No valid cache - fetch fresh data.
        $posts = array();

        // For public feeds, fetch data from Instagram.
        if ( 'public' === $feed->feed_type && ! empty( $feed->instagram_usernames ) ) {
            $posts = self::fetch_public_feed( $feed );
        }

        // Feature #58: Only cache if we got REAL posts (not placeholder data).
        // Placeholder data should NEVER be cached to avoid showing fake images on frontend.
        if ( ! empty( $posts ) && ! self::is_placeholder_data( $posts ) ) {
            self::store_cache( $feed->id, $posts, $feed->cache_duration );
        } elseif ( ! empty( $posts ) && self::is_placeholder_data( $posts ) ) {
            error_log( 'BWG IGF: NOT caching placeholder data for feed #' . $feed->id . ' - only real Instagram data is cached' );
        }

        return $posts;
    }

    /**
     * Check if posts array contains placeholder data.
     *
     * Feature #58: Detects placeholder data to prevent caching and allow
     * frontend to show admin-only warnings.
     *
     * @param array $posts Array of post data.
     * @return bool True if any post is placeholder data.
     */
    public static function is_placeholder_data( $posts ) {
        if ( empty( $posts ) || ! is_array( $posts ) ) {
            return false;
        }

        // Check first post for is_placeholder flag
        if ( isset( $posts[0]['is_placeholder'] ) && true === $posts[0]['is_placeholder'] ) {
            return true;
        }

        // Also detect by checking for picsum.photos URLs (fallback for data without flag)
        if ( isset( $posts[0]['thumbnail'] ) ) {
            $thumbnail = $posts[0]['thumbnail'];
            if ( strpos( $thumbnail, 'picsum.photos' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch public Instagram feed data.
     *
     * Uses a web scraping approach since Instagram's public API requires authentication.
     * This fetches publicly available data from Instagram profiles.
     *
     * @param object $feed The feed object.
     * @return array Array of post data.
     */
    private static function fetch_public_feed( $feed ) {
        $usernames = json_decode( $feed->instagram_usernames, true );
        if ( ! is_array( $usernames ) ) {
            $usernames = array( $feed->instagram_usernames );
        }

        // Clean usernames (remove @ if present).
        $usernames = array_map( function( $username ) {
            return ltrim( trim( $username ), '@' );
        }, $usernames );

        $all_posts = array();

        foreach ( $usernames as $username ) {
            if ( empty( $username ) ) {
                continue;
            }

            $user_posts = self::fetch_user_posts( $username );
            if ( ! empty( $user_posts ) ) {
                $all_posts = array_merge( $all_posts, $user_posts );
            }
        }

        // Limit to the configured post count.
        $post_count = absint( $feed->post_count ) ?: 9;

        // Sort by timestamp (newest first) before limiting.
        usort( $all_posts, function( $a, $b ) {
            $time_a = isset( $a['timestamp'] ) ? $a['timestamp'] : 0;
            $time_b = isset( $b['timestamp'] ) ? $b['timestamp'] : 0;
            return $time_b - $time_a;
        });

        return array_slice( $all_posts, 0, $post_count );
    }

    /**
     * Fetch posts for a single Instagram user.
     *
     * Uses multiple methods to try to fetch public Instagram data:
     * 1. Instagram's web profile endpoint
     * 2. Fallback to simulated data if Instagram blocks the request
     *
     * @param string $username Instagram username.
     * @return array Array of post data.
     */
    private static function fetch_user_posts( $username ) {
        // Try to fetch from Instagram's public profile.
        $profile_url = sprintf( 'https://www.instagram.com/%s/', sanitize_text_field( $username ) );

        $response = wp_remote_get( $profile_url, array(
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers'    => array(
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'BWG IGF: Failed to fetch Instagram profile for ' . $username . ': ' . $response->get_error_message() );
            return self::generate_placeholder_data( $username );
        }

        $body = wp_remote_retrieve_body( $response );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status_code ) {
            error_log( 'BWG IGF: Instagram returned status ' . $status_code . ' for ' . $username );
            return self::generate_placeholder_data( $username );
        }

        // Try to extract post data from the HTML.
        $posts = self::parse_instagram_html( $body, $username );

        if ( empty( $posts ) ) {
            // If parsing failed, use placeholder data with the actual username.
            return self::generate_placeholder_data( $username );
        }

        return $posts;
    }

    /**
     * Parse Instagram HTML to extract post data.
     *
     * Instagram embeds JSON data in the page that contains post information.
     *
     * @param string $html The page HTML.
     * @param string $username The Instagram username.
     * @return array Array of post data.
     */
    private static function parse_instagram_html( $html, $username ) {
        $posts = array();

        // Try to find the shared data JSON in the page.
        // Instagram stores profile data in a script tag with type="application/ld+json"
        // or in window._sharedData / window.__additionalDataLoaded.

        // Method 1: Look for sharedData JSON.
        if ( preg_match( '/window\._sharedData\s*=\s*({.+?});<\/script>/s', $html, $matches ) ) {
            $json_data = json_decode( $matches[1], true );
            if ( $json_data && isset( $json_data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'] ) ) {
                $edges = $json_data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'];
                foreach ( $edges as $edge ) {
                    $node = $edge['node'];
                    $posts[] = self::format_post_data( $node, $username );
                }
            }
        }

        // Method 2: Look for additional data JSON.
        if ( empty( $posts ) && preg_match( '/window\.__additionalDataLoaded\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*({.+?})\s*\)\s*;/s', $html, $matches ) ) {
            $json_data = json_decode( $matches[1], true );
            if ( $json_data && isset( $json_data['graphql']['user']['edge_owner_to_timeline_media']['edges'] ) ) {
                $edges = $json_data['graphql']['user']['edge_owner_to_timeline_media']['edges'];
                foreach ( $edges as $edge ) {
                    $node = $edge['node'];
                    $posts[] = self::format_post_data( $node, $username );
                }
            }
        }

        // Method 3: Try to extract from article/ld+json structured data.
        if ( empty( $posts ) && preg_match_all( '/<script type="application\/ld\+json">(.+?)<\/script>/s', $html, $matches ) ) {
            foreach ( $matches[1] as $json_str ) {
                $ld_json = json_decode( $json_str, true );
                if ( $ld_json && isset( $ld_json['@type'] ) && 'ProfilePage' === $ld_json['@type'] ) {
                    // Extract what we can from structured data.
                    if ( isset( $ld_json['mainEntity']['interactionStatistic'] ) ) {
                        // Profile exists, but we may not have post data.
                        break;
                    }
                }
            }
        }

        return $posts;
    }

    /**
     * Format post data from Instagram's JSON structure.
     *
     * @param array  $node The node data from Instagram.
     * @param string $username The Instagram username.
     * @return array Formatted post data.
     */
    private static function format_post_data( $node, $username ) {
        $thumbnail = '';
        $full_image = '';

        // Get image URLs.
        if ( isset( $node['thumbnail_src'] ) ) {
            $thumbnail = $node['thumbnail_src'];
        } elseif ( isset( $node['display_url'] ) ) {
            $thumbnail = $node['display_url'];
        }

        if ( isset( $node['display_url'] ) ) {
            $full_image = $node['display_url'];
        } else {
            $full_image = $thumbnail;
        }

        // Get caption.
        $caption = '';
        if ( isset( $node['edge_media_to_caption']['edges'][0]['node']['text'] ) ) {
            $caption = $node['edge_media_to_caption']['edges'][0]['node']['text'];
        }

        // Get engagement stats.
        $likes = 0;
        $comments = 0;

        if ( isset( $node['edge_liked_by']['count'] ) ) {
            $likes = intval( $node['edge_liked_by']['count'] );
        } elseif ( isset( $node['edge_media_preview_like']['count'] ) ) {
            $likes = intval( $node['edge_media_preview_like']['count'] );
        }

        if ( isset( $node['edge_media_to_comment']['count'] ) ) {
            $comments = intval( $node['edge_media_to_comment']['count'] );
        }

        // Get timestamp.
        $timestamp = isset( $node['taken_at_timestamp'] ) ? intval( $node['taken_at_timestamp'] ) : time();

        // Get post link.
        $shortcode = isset( $node['shortcode'] ) ? $node['shortcode'] : '';
        $link = $shortcode ? 'https://www.instagram.com/p/' . $shortcode . '/' : 'https://www.instagram.com/' . $username . '/';

        return array(
            'thumbnail'  => $thumbnail,
            'full_image' => $full_image,
            'caption'    => $caption,
            'likes'      => $likes,
            'comments'   => $comments,
            'link'       => $link,
            'timestamp'  => $timestamp,
            'username'   => $username,
        );
    }

    /**
     * Generate placeholder data for an Instagram user.
     *
     * This is used when we cannot fetch real data from Instagram.
     * The placeholder data includes the actual username and realistic-looking content.
     *
     * Feature #58: Placeholder data is now marked with 'is_placeholder' flag
     * to prevent caching and allow frontend to show admin warnings.
     *
     * @param string $username The Instagram username.
     * @return array Array of placeholder post data.
     */
    private static function generate_placeholder_data( $username ) {
        $posts = array();

        // Log that we're generating placeholder data
        error_log( 'BWG IGF: Generating placeholder data for @' . $username . ' - real Instagram data unavailable' );

        // Generate 12 placeholder posts with varied, realistic content.
        $image_seeds = array(
            'nature', 'city', 'food', 'travel', 'fitness',
            'art', 'tech', 'music', 'fashion', 'pets',
            'beach', 'mountain',
        );

        $captions = array(
            "Beautiful day! \xF0\x9F\x8C\x9F #instagram #photooftheday",
            "Living my best life \xE2\x9C\xA8 #lifestyle #blessed",
            "Adventure awaits! \xF0\x9F\x8C\x8D #travel #explore",
            "Good vibes only \xE2\x98\x80\xEF\xB8\x8F #positivity #happiness",
            "Making memories \xF0\x9F\x93\xB8 #moments #life",
            "Stay inspired \xF0\x9F\x92\xAB #motivation #goals",
            "Simple pleasures \xF0\x9F\x8C\xB8 #simplicity #joy",
            "Finding beauty everywhere \xF0\x9F\x8C\xBA #beauty #nature",
            "New beginnings \xF0\x9F\x8C\xB1 #fresh #start",
            "Grateful for today \xF0\x9F\x99\x8F #gratitude #thankful",
            "Chase your dreams \xF0\x9F\x92\xAD #dreams #ambition",
            "Weekend mode on \xF0\x9F\x8E\x89 #weekend #fun",
        );

        // Base timestamp (posts from the last 2 weeks).
        $base_time = time() - ( 14 * DAY_IN_SECONDS );

        for ( $i = 0; $i < 12; $i++ ) {
            $seed = $image_seeds[ $i % count( $image_seeds ) ];
            $post_time = $base_time + ( $i * 86400 ) + rand( 0, 43200 ); // Random time within each day.

            $posts[] = array(
                'thumbnail'     => sprintf( 'https://picsum.photos/seed/%s_%s_%d/400/400', $username, $seed, $i ),
                'full_image'    => sprintf( 'https://picsum.photos/seed/%s_%s_%d/1080/1080', $username, $seed, $i ),
                'caption'       => sprintf( '@%s: %s', $username, $captions[ $i % count( $captions ) ] ),
                'likes'         => rand( 100, 5000 ),
                'comments'      => rand( 5, 200 ),
                'link'          => sprintf( 'https://www.instagram.com/%s/', $username ),
                'timestamp'     => $post_time,
                'username'      => $username,
                'is_placeholder' => true, // Feature #58: Mark as placeholder data
            );
        }

        return $posts;
    }

    /**
     * Store posts in the cache table.
     *
     * @param int   $feed_id Feed ID.
     * @param array $posts Array of post data.
     * @param int   $cache_duration Cache duration in seconds.
     * @param int   $account_id Optional. Account ID for rate limit check.
     * @return bool True on success, false on failure.
     */
    public static function store_cache( $feed_id, $posts, $cache_duration = 3600, $account_id = 0 ) {
        global $wpdb;

        // Delete any existing cache for this feed.
        $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_cache',
            array( 'feed_id' => $feed_id ),
            array( '%d' )
        );

        // Feature #21: Get effective cache duration (may be extended during rate limit).
        if ( class_exists( 'BWG_IGF_API_Tracker' ) ) {
            $effective_duration = BWG_IGF_API_Tracker::get_effective_cache_duration( $cache_duration, $account_id );
        } else {
            $effective_duration = $cache_duration;
        }

        // Calculate expiration time.
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + $effective_duration );

        // Generate cache key.
        $cache_key = 'feed_' . $feed_id . '_' . md5( serialize( $posts ) );

        // Insert new cache entry.
        $result = $wpdb->insert(
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

        return false !== $result;
    }

    /**
     * Get cached posts for a feed.
     *
     * @param int $feed_id Feed ID.
     * @return array|null Array of posts if cache exists, null otherwise.
     */
    public static function get_cached_posts( $feed_id ) {
        global $wpdb;

        $cache_data = $wpdb->get_var( $wpdb->prepare(
            "SELECT cache_data FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            $feed_id
        ) );

        if ( $cache_data ) {
            return json_decode( $cache_data, true );
        }

        return null;
    }

    /**
     * Clear cache for a specific feed.
     *
     * @param int $feed_id Feed ID.
     * @return bool True on success.
     */
    public static function clear_feed_cache( $feed_id ) {
        global $wpdb;

        return false !== $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_cache',
            array( 'feed_id' => $feed_id ),
            array( '%d' )
        );
    }

    /**
     * Clear all cache entries.
     *
     * @return bool True on success.
     */
    public static function clear_all_cache() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- TRUNCATE is intentional for cache clearing.
        return false !== $wpdb->query(
            $wpdb->prepare( 'TRUNCATE TABLE %i', $wpdb->prefix . 'bwg_igf_cache' )
        );
    }
}
