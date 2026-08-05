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
     * Seconds of headroom to leave before a signed media URL expires.
     *
     * Cache entries are never allowed to outlive the images they reference by
     * more than this margin, so the background refresh fires while the URLs are
     * still fetchable rather than after they have already gone dead.
     *
     * @var int
     */
    const SIGNATURE_EXPIRY_BUFFER = 600;

    /**
     * Minimum cache duration in seconds.
     *
     * Floor applied after capping to signature expiry, so a batch of
     * already-expiring URLs cannot drive the cache duration to zero and cause a
     * refetch on every request.
     *
     * @var int
     */
    const MIN_CACHE_DURATION = 300;

    /**
     * Maximum age in seconds that expired cache may still be served.
     *
     * The frontend deliberately serves stale cache so visitors always see
     * content, but without an upper bound a stalled background refresh means
     * dead content is served forever. Beyond this age the feed re-fetches
     * instead.
     *
     * @var int
     */
    const MAX_STALE_CACHE_AGE = 86400;

    /**
     * Fetch posts for a feed and cache them.
     *
     * Returns valid cached posts when available; otherwise fetches fresh data
     * and caches it only when real posts were returned. Empty results are never
     * cached so a later request can retry the fetch.
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
                return $posts;
            }
        }

        // No valid cache - fetch fresh data.
        $posts = array();

        // For public feeds, fetch data from Instagram.
        if ( 'public' === $feed->feed_type && ! empty( $feed->instagram_usernames ) ) {
            $posts = self::fetch_public_feed( $feed );
        }

        // Only cache real posts. Empty results (fetch failed) are never cached so
        // the next request can retry instead of serving an empty feed from cache.
        if ( ! empty( $posts ) ) {
            self::store_cache( $feed->id, $posts, $feed->cache_duration );
        }

        return $posts;
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
     * Fetches public Instagram data from Instagram's web profile endpoint.
     * If the request fails, is blocked, or cannot be parsed, this returns an
     * empty array so the feed fails honestly rather than showing fabricated posts.
     *
     * @param string $username Instagram username.
     * @return array Array of post data (empty on failure).
     */
    private static function fetch_user_posts( $username ) {
        // Try to fetch from Instagram's public profile.
        $profile_url = sprintf( 'https://www.instagram.com/%s/', sanitize_text_field( $username ) );
        $user_agent  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        // Initialize variables.
        $body        = '';
        $status_code = 0;

        // Use native cURL for better compatibility with Instagram's bot detection.
        // WordPress wp_remote_get uses HTTP/1.0 which Instagram blocks.
        if ( function_exists( 'curl_init' ) ) {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $profile_url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
            curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ) );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

            $body        = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $curl_error  = curl_error( $ch );
            curl_close( $ch );

            if ( false === $body || ! empty( $curl_error ) ) {
                $error_msg = ! empty( $curl_error ) ? $curl_error : 'Request failed';
                error_log( 'BWG IGF: Failed to fetch Instagram profile for ' . $username . ': ' . $error_msg );
                return array();
            }
        } else {
            // Fallback to wp_remote_get if cURL is not available.
            $response = wp_remote_get( $profile_url, array(
                'timeout'    => 15,
                'user-agent' => $user_agent,
                'headers'    => array(
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ),
            ) );

            if ( is_wp_error( $response ) ) {
                error_log( 'BWG IGF: Failed to fetch Instagram profile for ' . $username . ': ' . $response->get_error_message() );
                return array();
            }

            $body        = wp_remote_retrieve_body( $response );
            $status_code = wp_remote_retrieve_response_code( $response );
        }

        if ( 200 !== $status_code ) {
            error_log( 'BWG IGF: Instagram returned status ' . $status_code . ' for ' . $username );
            return array();
        }

        // Try to extract post data from the HTML.
        $posts = self::parse_instagram_html( $body, $username );

        if ( empty( $posts ) ) {
            // Parsing failed - fail honestly with no posts rather than fabricating data.
            error_log( 'BWG IGF: Unable to parse Instagram posts for ' . $username );
            return array();
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
     * Get the earliest signature expiry across a set of posts.
     *
     * Instagram media URLs are individually signed and expire at slightly
     * different times. The cache is only as good as its shortest-lived image,
     * so the earliest expiry is the one that matters.
     *
     * @param array $posts Array of post data.
     * @return int|false Earliest Unix expiry timestamp, or false if none found.
     */
    public static function get_posts_signature_expiry( $posts ) {
        if ( empty( $posts ) || ! is_array( $posts ) ) {
            return false;
        }

        if ( ! class_exists( 'BWG_IGF_Image_Proxy' ) ) {
            return false;
        }

        $earliest = false;

        foreach ( $posts as $post ) {
            if ( ! is_array( $post ) ) {
                continue;
            }

            foreach ( array( 'thumbnail', 'full_image', 'video_url' ) as $field ) {
                if ( empty( $post[ $field ] ) ) {
                    continue;
                }

                $expiry = BWG_IGF_Image_Proxy::get_url_expiry( $post[ $field ] );

                if ( false === $expiry ) {
                    continue;
                }

                if ( false === $earliest || $expiry < $earliest ) {
                    $earliest = $expiry;
                }
            }
        }

        return $earliest;
    }

    /**
     * Cap a cache duration so the entry expires before its images do.
     *
     * Posts whose URLs carry no parseable expiry keep the requested duration.
     *
     * @param int   $duration Requested cache duration in seconds.
     * @param array $posts    Array of post data.
     * @return int The effective duration in seconds.
     */
    public static function cap_duration_to_signature_expiry( $duration, $posts ) {
        $duration = intval( $duration );
        $earliest = self::get_posts_signature_expiry( $posts );

        if ( false === $earliest ) {
            return $duration;
        }

        $safe_duration = $earliest - self::SIGNATURE_EXPIRY_BUFFER - time();

        if ( $safe_duration >= $duration ) {
            return $duration;
        }

        return max( self::MIN_CACHE_DURATION, $safe_duration );
    }

    /**
     * Check whether a cached post set has dead image URLs.
     *
     * @param array $posts Array of post data.
     * @return bool True if the signed image URLs have already expired.
     */
    public static function posts_have_expired_images( $posts ) {
        $earliest = self::get_posts_signature_expiry( $posts );

        if ( false === $earliest ) {
            return false;
        }

        return $earliest <= time();
    }

    /**
     * Expire cache rows whose image URLs have already gone dead.
     *
     * Called when the image proxy sees a 403 from the CDN. Rather than deleting
     * the rows - which would leave the frontend with nothing to show if a
     * refetch fails - their expiry is backdated so the next render treats them
     * as stale and refreshes.
     *
     * @return int Number of cache rows invalidated.
     */
    public static function invalidate_expired_signature_caches() {
        global $wpdb;

        // Row count tracks the number of feeds, so scanning all of them is cheap.
        // Expiry is compared in PHP rather than SQL to match how the rest of this
        // class reads expires_at, avoiding a timezone mismatch with the stored value.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache maintenance.
        $rows = $wpdb->get_results(
            "SELECT id, feed_id, cache_data, expires_at FROM {$wpdb->prefix}bwg_igf_cache"
        );

        if ( empty( $rows ) ) {
            return 0;
        }

        $invalidated = 0;
        $backdated   = gmdate( 'Y-m-d H:i:s', time() - 1 );

        foreach ( $rows as $row ) {
            // Already stale - nothing to invalidate, and re-logging would be noise.
            if ( strtotime( $row->expires_at ) < time() ) {
                continue;
            }

            $posts = json_decode( $row->cache_data, true );

            if ( empty( $posts ) || ! is_array( $posts ) ) {
                continue;
            }

            if ( ! self::posts_have_expired_images( $posts ) ) {
                continue;
            }

            $wpdb->update(
                $wpdb->prefix . 'bwg_igf_cache',
                array( 'expires_at' => $backdated ),
                array( 'id' => $row->id ),
                array( '%s' ),
                array( '%d' )
            );

            $invalidated++;

            if ( class_exists( 'BWG_IGF_Logger' ) ) {
                BWG_IGF_Logger::warning(
                    'Feed cache invalidated: its Instagram image URLs had expired.',
                    array( 'feed_id' => $row->feed_id )
                );
            }
        }

        return $invalidated;
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

        // Never cache past the point where the signed image URLs stop working.
        // This also overrides the rate-limit extension above: holding a feed for
        // 24h is pointless if its images die in 2h.
        $effective_duration = self::cap_duration_to_signature_expiry( $effective_duration, $posts );

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
     * Get ANY cached posts for a feed, regardless of expiration.
     *
     * This method is used by the frontend to always serve cached content when available,
     * even if the cache has expired. Freshness is handled by background cron jobs,
     * not frontend requests. This ensures visitors always see content instead of errors.
     *
     * @param int $feed_id Feed ID.
     * @return array{posts: array|null, is_expired: bool, created_at: string|null, images_expired: bool, is_unusable: bool} Cache state.
     */
    public static function get_cached_posts_any( $feed_id ) {
        global $wpdb;

        // Get the most recent cache entry regardless of expiration.
        $cache_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT cache_data, created_at, expires_at FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d ORDER BY created_at DESC LIMIT 1",
            $feed_id
        ) );

        if ( ! $cache_row || empty( $cache_row->cache_data ) ) {
            return array(
                'posts'          => null,
                'is_expired'     => false,
                'created_at'     => null,
                'images_expired' => false,
                'is_unusable'    => false,
            );
        }

        $posts = json_decode( $cache_row->cache_data, true );

        // Check if the cache is expired.
        $is_expired = strtotime( $cache_row->expires_at ) < time();

        // Serving stale content is fine; serving content whose images have gone
        // dead is not - the visitor sees a grid of placeholders. Flag that case
        // so callers can refetch instead.
        $images_expired = self::posts_have_expired_images( $posts );

        // Backstop for feeds whose URLs carry no parseable expiry: refuse to
        // serve indefinitely if the background refresh has clearly stalled.
        // Measured from expires_at, which is stored in UTC like time(), rather
        // than created_at, which is written in site-local time.
        $expires_ts  = strtotime( $cache_row->expires_at );
        $too_old     = ( $expires_ts && ( time() - $expires_ts ) > self::MAX_STALE_CACHE_AGE );
        $is_unusable = $images_expired || ( $is_expired && $too_old );

        return array(
            'posts'          => $posts,
            'is_expired'     => $is_expired,
            'created_at'     => $cache_row->created_at,
            'images_expired' => $images_expired,
            'is_unusable'    => $is_unusable,
        );
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
