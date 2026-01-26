<?php
/**
 * Frontend Feed Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load the Image Proxy class if not already loaded (for generating CORS-safe image URLs).
if ( ! class_exists( 'BWG_IGF_Image_Proxy' ) ) {
    require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-image-proxy.php';
}

// Get feed by ID or slug
$feed_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : 0;
$feed_slug = ! empty( $atts['feed'] ) ? sanitize_title( $atts['feed'] ) : '';

global $wpdb;

if ( $feed_id > 0 ) {
    // Include both 'active' and 'error' status feeds - error feeds may have rate limit warnings
    // but can still display cached content
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d AND status IN ('active', 'error')",
        $feed_id
    ) );
} elseif ( ! empty( $feed_slug ) ) {
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s AND status IN ('active', 'error')",
        $feed_slug
    ) );
} else {
    $feed = null;
}

if ( ! $feed ) {
    echo '<div class="bwg-igf-error">' . esc_html__( 'Feed not found or inactive.', 'bwg-instagram-feed' ) . '</div>';
    return;
}

// Parse settings
$layout_settings = json_decode( $feed->layout_settings, true ) ?: array();
$display_settings = json_decode( $feed->display_settings, true ) ?: array();
$styling_settings = json_decode( $feed->styling_settings, true ) ?: array();
$popup_settings = json_decode( $feed->popup_settings, true ) ?: array();

// Defaults
$columns = isset( $layout_settings['columns'] ) ? absint( $layout_settings['columns'] ) : 3;
$gap = isset( $layout_settings['gap'] ) ? absint( $layout_settings['gap'] ) : 10;
$hover_effect = isset( $styling_settings['hover_effect'] ) ? $styling_settings['hover_effect'] : 'none';
$border_radius = isset( $styling_settings['border_radius'] ) ? absint( $styling_settings['border_radius'] ) : 0;
$background_color = isset( $styling_settings['background_color'] ) ? $styling_settings['background_color'] : '';
$popup_enabled = isset( $popup_settings['enabled'] ) ? $popup_settings['enabled'] : true;
$custom_css = isset( $styling_settings['custom_css'] ) ? $styling_settings['custom_css'] : '';

// Feed Size settings (Feature #165)
$feed_width = isset( $styling_settings['feed_width'] ) ? $styling_settings['feed_width'] : '100%';
$feed_max_width = isset( $styling_settings['feed_max_width'] ) ? $styling_settings['feed_max_width'] : '';
$feed_padding = isset( $styling_settings['feed_padding'] ) ? absint( $styling_settings['feed_padding'] ) : 0;
$image_height_mode = isset( $styling_settings['image_height_mode'] ) ? $styling_settings['image_height_mode'] : 'square';
$image_fixed_height = isset( $styling_settings['image_fixed_height'] ) ? absint( $styling_settings['image_fixed_height'] ) : 200;

// Slider defaults
$slides_to_show = isset( $layout_settings['slides_to_show'] ) ? absint( $layout_settings['slides_to_show'] ) : 3;
$autoplay = ! empty( $layout_settings['autoplay'] );
$autoplay_speed = isset( $layout_settings['autoplay_speed'] ) ? absint( $layout_settings['autoplay_speed'] ) : 3000;
$infinite = isset( $layout_settings['infinite'] ) ? (bool) $layout_settings['infinite'] : true;
$show_arrows = isset( $layout_settings['show_arrows'] ) ? (bool) $layout_settings['show_arrows'] : true;
$show_dots = isset( $layout_settings['show_dots'] ) ? (bool) $layout_settings['show_dots'] : true;

// Check if we have cached posts
global $wpdb;

// Feature #23: Track cache status for stale data indicator
$cache_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT cache_data, created_at, expires_at FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
    $feed->id
) );

$cache_data = $cache_row ? $cache_row->cache_data : null;
$cache_created_at = $cache_row ? $cache_row->created_at : null;
$cache_expires_at = $cache_row ? $cache_row->expires_at : null;
$is_using_stale_cache = false;

// Determine if we have cached posts
$has_cache = ! empty( $cache_data );
$posts = array();
$no_cache_message = '';
$rate_limit_warning = '';

if ( $has_cache ) {
    $posts = json_decode( $cache_data, true ) ?: array();
}

// Also check for expired cache (useful for showing stale data during rate limiting)
$expired_cache_data = null;
$expired_cache_created_at = null;
if ( ! $has_cache ) {
    // Get any existing cache, even if expired
    $expired_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT cache_data, created_at FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d ORDER BY created_at DESC LIMIT 1",
        $feed->id
    ) );
    $expired_cache_data = $expired_row ? $expired_row->cache_data : null;
    $expired_cache_created_at = $expired_row ? $expired_row->created_at : null;
}

// Determine if we need async loading (no cache but have usernames or connected account)
// This shows a loading state while AJAX fetches the data
$has_source = ! empty( $feed->instagram_usernames ) || ( 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) );
$needs_async_load = empty( $posts ) && $has_source;

// Feature #34: Check if connected account exists and is active for connected feeds.
// If the account is disconnected/expired, show a helpful error instead of loading state.
$connected_account_error = false;
$connected_account_info = null;
if ( 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) ) {
    $connected_account_info = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, username, status, expires_at FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
        $feed->connected_account_id
    ) );

    if ( ! $connected_account_info ) {
        // Account record doesn't exist (deleted)
        $connected_account_error = 'deleted';
        $no_cache_message = __( 'The Instagram account connected to this feed has been removed.', 'bwg-instagram-feed' );
        $needs_async_load = false;
    } elseif ( 'active' !== $connected_account_info->status ) {
        // Account exists but is inactive/disconnected
        $connected_account_error = 'inactive';
        $no_cache_message = sprintf(
            /* translators: %s: Instagram username */
            __( 'The Instagram account @%s is no longer connected.', 'bwg-instagram-feed' ),
            esc_html( $connected_account_info->username )
        );
        $needs_async_load = false;
    } elseif ( ! empty( $connected_account_info->expires_at ) && strtotime( $connected_account_info->expires_at ) < time() ) {
        // Token has expired
        $connected_account_error = 'expired';
        $no_cache_message = sprintf(
            /* translators: %s: Instagram username */
            __( 'The access token for @%s has expired.', 'bwg-instagram-feed' ),
            esc_html( $connected_account_info->username )
        );
        $needs_async_load = false;
    }
}

// Feature #24: For connected feeds, check for cache warming data before showing loading state.
// When an account is connected, posts are pre-fetched and stored in a transient.
if ( $needs_async_load && 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) ) {
    $warmed_cache = get_transient( 'bwg_igf_account_cache_' . $feed->connected_account_id );
    if ( ! empty( $warmed_cache ) && ! empty( $warmed_cache['posts'] ) ) {
        // Use warmed cache data.
        $post_count = absint( $feed->post_count ) ?: 9;
        $posts = array_slice( $warmed_cache['posts'], 0, $post_count );
        $needs_async_load = false;

        // Store the warmed cache data in the feed cache for future use.
        if ( ! empty( $posts ) ) {
            $cache_duration = absint( $feed->cache_duration ) ?: 3600;
            $expires_at = gmdate( 'Y-m-d H:i:s', time() + $cache_duration );
            $cache_key = 'feed_' . $feed->id . '_warmed_' . md5( wp_json_encode( $posts ) );

            // Delete old cache entries for this feed.
            $wpdb->delete(
                $wpdb->prefix . 'bwg_igf_cache',
                array( 'feed_id' => $feed->id ),
                array( '%d' )
            );

            // Insert the warmed cache as feed cache.
            $wpdb->insert(
                $wpdb->prefix . 'bwg_igf_cache',
                array(
                    'feed_id'    => $feed->id,
                    'cache_key'  => $cache_key,
                    'cache_data' => wp_json_encode( $posts ),
                    'created_at' => current_time( 'mysql' ),
                    'expires_at' => $expires_at,
                ),
                array( '%d', '%s', '%s', '%s', '%s' )
            );
        }
    }
}

// For private accounts, we need to try fetching synchronously to show the error
// Otherwise, the async JS will just show loading indefinitely
if ( $needs_async_load && ! empty( $feed->instagram_usernames ) ) {
    // Load the Instagram API class if not already loaded.
    if ( ! class_exists( 'BWG_IGF_Instagram_API' ) ) {
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-api.php';
    }

    $instagram_api = new BWG_IGF_Instagram_API();
    $usernames = json_decode( $feed->instagram_usernames, true );
    if ( ! is_array( $usernames ) ) {
        $usernames = array( $feed->instagram_usernames );
    }

    // Try to fetch posts for the first username to check for errors
    if ( ! empty( $usernames[0] ) ) {
        $fetched_posts = $instagram_api->fetch_public_posts( $usernames[0], 12 );

        if ( is_wp_error( $fetched_posts ) ) {
            // Store specific error message based on error type
            $error_code = $fetched_posts->get_error_code();
            $error_message = $fetched_posts->get_error_message();

            // Check for private account error specifically
            if ( 'private_account' === $error_code ) {
                $no_cache_message = sprintf(
                    /* translators: %s: Instagram username */
                    __( 'This Instagram account (@%s) is private. Private accounts cannot be displayed without authentication.', 'bwg-instagram-feed' ),
                    esc_html( $usernames[0] )
                );
                $needs_async_load = false; // Don't show loading, show error
            } elseif ( 'user_not_found' === $error_code ) {
                $no_cache_message = sprintf(
                    /* translators: %s: Instagram username */
                    __( 'Instagram user "@%s" was not found. Please check the username and try again.', 'bwg-instagram-feed' ),
                    esc_html( $usernames[0] )
                );
                $needs_async_load = false;
            } else {
                $no_cache_message = sprintf(
                    /* translators: %s: Error message */
                    __( 'Could not fetch Instagram posts: %s', 'bwg-instagram-feed' ),
                    $error_message
                );
                // Keep async load for other errors (might be temporary)
            }
        } elseif ( is_array( $fetched_posts ) && ! empty( $fetched_posts ) ) {
            // Successfully fetched posts, use them
            $posts = $fetched_posts;
            $needs_async_load = false;

            // Cache the posts for future use
            $wpdb->insert(
                $wpdb->prefix . 'bwg_igf_cache',
                array(
                    'feed_id'    => $feed->id,
                    'cache_data' => wp_json_encode( $posts ),
                    'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }
    }
}

// Check if there's a rate limit error stored for this feed
$feed_status = $feed->status ?? 'active';
$feed_error = $feed->error_message ?? '';
$is_rate_limited = ( 'error' === $feed_status &&
    ( stripos( $feed_error, 'rate' ) !== false ||
      stripos( $feed_error, 'limit' ) !== false ||
      stripos( $feed_error, 'temporarily' ) !== false ) );

// If rate limited but we have cached/expired data, show the data with a warning
if ( $is_rate_limited ) {
    $rate_limit_warning = __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing. Showing cached posts below.', 'bwg-instagram-feed' );

    // If no current cache but have expired cache, use expired cache
    if ( empty( $posts ) && ! empty( $expired_cache_data ) ) {
        $posts = json_decode( $expired_cache_data, true ) ?: array();
        $is_using_stale_cache = true;
        $cache_created_at = $expired_cache_created_at; // Use the expired cache timestamp
        $rate_limit_warning = __( 'Instagram is temporarily limiting requests. Showing previously cached posts. Please wait a few minutes and try again.', 'bwg-instagram-feed' );
    }

    // Feature #17: If rate limited with NO cache at all, show a user-friendly error message
    if ( empty( $posts ) && empty( $expired_cache_data ) ) {
        $no_cache_message = __( 'Instagram is temporarily limiting requests. Please wait a few minutes and try again later. We apologize for the inconvenience.', 'bwg-instagram-feed' );
        $rate_limit_warning = ''; // Don't show warning banner if we have no posts to show
    }

    // Don't show loading state if rate limited
    $needs_async_load = false;
}

// Apply ordering based on feed settings
$ordering = isset( $feed->ordering ) ? $feed->ordering : 'newest';

// Apply hashtag filters (Feature #131, #132) - only for connected feeds
$filter_settings = ! empty( $feed->filter_settings ) ? json_decode( $feed->filter_settings, true ) : array();
$filter_settings = is_array( $filter_settings ) ? $filter_settings : array();
$hashtag_include = isset( $filter_settings['hashtag_include'] ) ? trim( $filter_settings['hashtag_include'] ) : '';
$hashtag_exclude = isset( $filter_settings['hashtag_exclude'] ) ? trim( $filter_settings['hashtag_exclude'] ) : '';

if ( ! empty( $posts ) && 'connected' === $feed->feed_type ) {
    // Parse included hashtags (comma-separated, without #)
    $included_hashtags = array();
    if ( ! empty( $hashtag_include ) ) {
        $included_hashtags = array_map( 'trim', explode( ',', $hashtag_include ) );
        $included_hashtags = array_filter( $included_hashtags ); // Remove empty values
        $included_hashtags = array_map( 'strtolower', $included_hashtags ); // Lowercase for case-insensitive matching
        // Remove # prefix if present
        $included_hashtags = array_map( function( $tag ) {
            return ltrim( $tag, '#' );
        }, $included_hashtags );
    }

    // Parse excluded hashtags (comma-separated, without #)
    $excluded_hashtags = array();
    if ( ! empty( $hashtag_exclude ) ) {
        $excluded_hashtags = array_map( 'trim', explode( ',', $hashtag_exclude ) );
        $excluded_hashtags = array_filter( $excluded_hashtags ); // Remove empty values
        $excluded_hashtags = array_map( 'strtolower', $excluded_hashtags ); // Lowercase for case-insensitive matching
        // Remove # prefix if present
        $excluded_hashtags = array_map( function( $tag ) {
            return ltrim( $tag, '#' );
        }, $excluded_hashtags );
    }

    // Apply filtering if there are any filter settings
    if ( ! empty( $included_hashtags ) || ! empty( $excluded_hashtags ) ) {
        $posts = array_filter( $posts, function( $post ) use ( $included_hashtags, $excluded_hashtags ) {
            $caption = isset( $post['caption'] ) ? strtolower( $post['caption'] ) : '';

            // Extract hashtags from caption
            preg_match_all( '/#([a-zA-Z0-9_]+)/', $caption, $matches );
            $post_hashtags = ! empty( $matches[1] ) ? array_map( 'strtolower', $matches[1] ) : array();

            // Check exclude filter first - if any excluded hashtag is present, skip this post
            if ( ! empty( $excluded_hashtags ) ) {
                foreach ( $excluded_hashtags as $hashtag ) {
                    if ( in_array( $hashtag, $post_hashtags, true ) ) {
                        return false; // Exclude this post
                    }
                }
            }

            // Check include filter - if include tags specified, at least one must be present
            if ( ! empty( $included_hashtags ) ) {
                $has_included = false;
                foreach ( $included_hashtags as $hashtag ) {
                    if ( in_array( $hashtag, $post_hashtags, true ) ) {
                        $has_included = true;
                        break;
                    }
                }
                if ( ! $has_included ) {
                    return false; // Skip this post
                }
            }

            return true; // Keep this post
        } );
        // Re-index array after filtering
        $posts = array_values( $posts );
    }
}

if ( ! empty( $posts ) ) {
    switch ( $ordering ) {
        case 'newest':
            // Sort by timestamp descending (newest first)
            usort( $posts, function( $a, $b ) {
                $time_a = isset( $a['timestamp'] ) ? $a['timestamp'] : 0;
                $time_b = isset( $b['timestamp'] ) ? $b['timestamp'] : 0;
                return $time_b - $time_a;
            });
            break;

        case 'oldest':
            // Sort by timestamp ascending (oldest first)
            usort( $posts, function( $a, $b ) {
                $time_a = isset( $a['timestamp'] ) ? $a['timestamp'] : 0;
                $time_b = isset( $b['timestamp'] ) ? $b['timestamp'] : 0;
                return $time_a - $time_b;
            });
            break;

        case 'random':
            // Shuffle the posts
            shuffle( $posts );
            break;

        case 'most_liked':
            // Sort by likes descending
            usort( $posts, function( $a, $b ) {
                $likes_a = isset( $a['likes'] ) ? intval( $a['likes'] ) : 0;
                $likes_b = isset( $b['likes'] ) ? intval( $b['likes'] ) : 0;
                return $likes_b - $likes_a;
            });
            break;

        case 'most_commented':
            // Sort by comments descending
            usort( $posts, function( $a, $b ) {
                $comments_a = isset( $a['comments'] ) ? intval( $a['comments'] ) : 0;
                $comments_b = isset( $b['comments'] ) ? intval( $b['comments'] ) : 0;
                return $comments_b - $comments_a;
            });
            break;
    }
}

// Custom styles
$custom_styles = array(
    '--bwg-igf-gap: ' . $gap . 'px',
    '--bwg-igf-border-radius: ' . $border_radius . 'px',
);

// Feed Size settings (Feature #165)
if ( ! empty( $feed_width ) ) {
    $custom_styles[] = 'width: ' . esc_attr( $feed_width );
}

if ( ! empty( $feed_max_width ) ) {
    $custom_styles[] = 'max-width: ' . esc_attr( $feed_max_width );
    $custom_styles[] = 'margin-left: auto';
    $custom_styles[] = 'margin-right: auto';
}

if ( $feed_padding > 0 ) {
    $custom_styles[] = 'padding: ' . absint( $feed_padding ) . 'px';
}

// Image height mode CSS variable
if ( 'fixed' === $image_height_mode && $image_fixed_height > 0 ) {
    $custom_styles[] = '--bwg-igf-image-height: ' . absint( $image_fixed_height ) . 'px';
} elseif ( 'original' === $image_height_mode ) {
    $custom_styles[] = '--bwg-igf-image-height: auto';
}

if ( ! empty( $background_color ) ) {
    $custom_styles[] = 'background-color: ' . esc_attr( $background_color );
    // Only add padding if not already set by feed_padding
    if ( $feed_padding <= 0 ) {
        $custom_styles[] = 'padding: 15px';
    }
    $custom_styles[] = 'border-radius: 8px';
}

if ( isset( $styling_settings['overlay_color'] ) ) {
    $custom_styles[] = '--bwg-igf-overlay-color: ' . $styling_settings['overlay_color'];
}

$feed_classes = array(
    'bwg-igf-feed',
    'bwg-igf-' . $feed->layout_type,
);

if ( 'grid' === $feed->layout_type ) {
    $feed_classes[] = 'bwg-igf-grid-' . $columns;
}

if ( 'none' !== $hover_effect ) {
    $feed_classes[] = 'bwg-igf-hover-' . $hover_effect;
}

// Add image height mode class (Feature #165)
if ( 'original' === $image_height_mode ) {
    $feed_classes[] = 'bwg-igf-image-original';
} elseif ( 'fixed' === $image_height_mode ) {
    $feed_classes[] = 'bwg-igf-image-fixed';
}

// Output custom CSS if provided
if ( ! empty( $custom_css ) ) :
?>
<style type="text/css" id="bwg-igf-custom-css-<?php echo esc_attr( $feed->id ); ?>">
<?php echo wp_strip_all_tags( $custom_css ); ?>
</style>
<?php endif; ?>
<div
    class="<?php echo esc_attr( implode( ' ', $feed_classes ) ); ?><?php echo $needs_async_load ? ' bwg-igf-loading-feed' : ''; ?>"
    data-feed-id="<?php echo esc_attr( $feed->id ); ?>"
    data-popup="<?php echo $popup_enabled ? 'true' : 'false'; ?>"
    data-needs-load="<?php echo $needs_async_load ? 'true' : 'false'; ?>"
    data-layout-type="<?php echo esc_attr( $feed->layout_type ); ?>"
    data-columns="<?php echo esc_attr( $columns ); ?>"
    data-hover-effect="<?php echo esc_attr( $hover_effect ); ?>"
    data-show-likes="<?php echo ! empty( $display_settings['show_likes'] ) ? 'true' : 'false'; ?>"
    data-show-comments="<?php echo ! empty( $display_settings['show_comments'] ) ? 'true' : 'false'; ?>"
    data-show-follow="<?php echo ! empty( $display_settings['show_follow_button'] ) ? 'true' : 'false'; ?>"
    <?php if ( 'slider' === $feed->layout_type ) : ?>
    data-slides-to-show="<?php echo esc_attr( $slides_to_show ); ?>"
    data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>"
    data-autoplay-speed="<?php echo esc_attr( $autoplay_speed ); ?>"
    data-infinite="<?php echo $infinite ? 'true' : 'false'; ?>"
    <?php endif; ?>
    style="<?php echo esc_attr( implode( '; ', $custom_styles ) ); ?>"
>
    <?php if ( ! empty( $rate_limit_warning ) ) : ?>
        <!-- Rate Limit Warning Banner -->
        <div class="bwg-igf-rate-limit-warning" role="alert">
            <div class="bwg-igf-rate-limit-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="bwg-igf-rate-limit-text">
                <strong><?php esc_html_e( 'Rate Limit Reached', 'bwg-instagram-feed' ); ?></strong>
                <p><?php echo esc_html( $rate_limit_warning ); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // Feature #23: Show stale data indicator if enabled and we have cached data
    $show_stale_indicator = get_option( 'bwg_igf_show_stale_data_indicator', 0 );
    $should_show_stale_indicator = $show_stale_indicator && ! empty( $posts ) && ! empty( $cache_created_at );

    // Calculate how old the cache is for display
    $cache_age_text = '';
    if ( $should_show_stale_indicator && ! empty( $cache_created_at ) ) {
        $cache_time = strtotime( $cache_created_at );
        $time_diff = time() - $cache_time;

        if ( $time_diff < 60 ) {
            $cache_age_text = __( 'Just now', 'bwg-instagram-feed' );
        } elseif ( $time_diff < 3600 ) {
            $minutes = floor( $time_diff / 60 );
            /* translators: %d: number of minutes */
            $cache_age_text = sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'bwg-instagram-feed' ), $minutes );
        } elseif ( $time_diff < 86400 ) {
            $hours = floor( $time_diff / 3600 );
            /* translators: %d: number of hours */
            $cache_age_text = sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'bwg-instagram-feed' ), $hours );
        } else {
            $days = floor( $time_diff / 86400 );
            /* translators: %d: number of days */
            $cache_age_text = sprintf( _n( '%d day ago', '%d days ago', $days, 'bwg-instagram-feed' ), $days );
        }
    }
    ?>

    <?php if ( $should_show_stale_indicator ) : ?>
        <!-- Feature #23: Stale Data Indicator -->
        <div class="bwg-igf-stale-data-indicator" title="<?php esc_attr_e( 'Cached data - may not be the most current', 'bwg-instagram-feed' ); ?>">
            <span class="bwg-igf-stale-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </span>
            <span class="bwg-igf-stale-text">
                <?php
                if ( $is_using_stale_cache ) {
                    /* translators: %s: time ago (e.g., "2 hours ago") */
                    printf( esc_html__( 'Showing cached data from %s', 'bwg-instagram-feed' ), esc_html( $cache_age_text ) );
                } else {
                    /* translators: %s: time ago (e.g., "2 hours ago") */
                    printf( esc_html__( 'Last updated %s', 'bwg-instagram-feed' ), esc_html( $cache_age_text ) );
                }
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php
    // Display account name header if enabled (Feature #24)
    if ( ! empty( $display_settings['show_account_name'] ) && ! empty( $feed->instagram_usernames ) ) :
        $header_usernames = json_decode( $feed->instagram_usernames, true );
        if ( ! is_array( $header_usernames ) ) {
            $header_usernames = array( $feed->instagram_usernames );
        }
        // Filter out empty usernames
        $header_usernames = array_filter( $header_usernames );
        ?>
        <div class="bwg-igf-account-header">
            <div class="bwg-igf-account-info">
                <span class="bwg-igf-account-icon">
                    <!-- Instagram icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                        <defs>
                            <linearGradient id="instagram-gradient-<?php echo esc_attr( $feed->id ); ?>" x1="0%" y1="100%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#feda75"/>
                                <stop offset="25%" style="stop-color:#fa7e1e"/>
                                <stop offset="50%" style="stop-color:#d62976"/>
                                <stop offset="75%" style="stop-color:#962fbf"/>
                                <stop offset="100%" style="stop-color:#4f5bd5"/>
                            </linearGradient>
                        </defs>
                        <path fill="url(#instagram-gradient-<?php echo esc_attr( $feed->id ); ?>)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                </span>
                <span class="bwg-igf-account-name">
                    <?php
                    if ( count( $header_usernames ) === 1 ) {
                        echo '<a href="https://instagram.com/' . esc_attr( $header_usernames[0] ) . '" target="_blank" rel="noopener noreferrer">@' . esc_html( $header_usernames[0] ) . '</a>';
                    } else {
                        // Multiple usernames - display as comma-separated list
                        $username_links = array();
                        foreach ( $header_usernames as $username ) {
                            $username = trim( $username );
                            if ( ! empty( $username ) ) {
                                $username_links[] = '<a href="https://instagram.com/' . esc_attr( $username ) . '" target="_blank" rel="noopener noreferrer">@' . esc_html( $username ) . '</a>';
                            }
                        }
                        echo implode( ', ', $username_links );
                    }
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $needs_async_load ) : ?>
        <!-- Loading State - displayed while fetching Instagram data via AJAX -->
        <div class="bwg-igf-loading" role="status" aria-live="polite" aria-label="<?php esc_attr_e( 'Loading Instagram feed...', 'bwg-instagram-feed' ); ?>">
            <div class="bwg-igf-loading-content">
                <div class="bwg-igf-spinner"></div>
                <p class="bwg-igf-loading-text"><?php esc_html_e( 'Loading Instagram feed...', 'bwg-instagram-feed' ); ?></p>
            </div>
        </div>
    <?php elseif ( empty( $posts ) ) : ?>
        <?php
        // Determine the type of error for display purposes
        // Check for "is private" phrase to avoid false matches with usernames containing "private"
        $is_private_account = strpos( $no_cache_message, 'is private' ) !== false || strpos( $no_cache_message, 'Private accounts cannot' ) !== false;
        $is_user_not_found = strpos( $no_cache_message, 'not found' ) !== false || strpos( $no_cache_message, 'was not found' ) !== false;
        // Feature #17: Detect rate limit error when no cache is available
        $is_rate_limit_error = strpos( $no_cache_message, 'temporarily limiting' ) !== false || strpos( $no_cache_message, 'rate limit' ) !== false || $is_rate_limited;
        // Feature #34: Detect connected account error
        $is_connected_account_error = ! empty( $connected_account_error );
        $has_error = ! empty( $no_cache_message );

        // Determine the appropriate warning style
        $warning_class = '';
        if ( $is_private_account ) {
            $warning_class = 'bwg-igf-private-account-warning';
        } elseif ( $is_user_not_found ) {
            $warning_class = 'bwg-igf-user-not-found-warning';
        } elseif ( $is_rate_limit_error ) {
            $warning_class = 'bwg-igf-rate-limit-error';
        } elseif ( $is_connected_account_error ) {
            $warning_class = 'bwg-igf-account-disconnected-warning';
        }
        ?>
        <div class="bwg-igf-empty-state <?php echo esc_attr( $warning_class ); ?>">
            <div class="bwg-igf-empty-state-icon">
                <?php if ( $is_private_account ) : ?>
                    <!-- Lock icon for private accounts -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                <?php elseif ( $is_user_not_found ) : ?>
                    <!-- Question mark icon for user not found -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
                    </svg>
                <?php elseif ( $is_rate_limit_error ) : ?>
                    <!-- Clock/timer icon for rate limiting -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                    </svg>
                <?php elseif ( $is_connected_account_error ) : ?>
                    <!-- Unlink/broken chain icon for disconnected accounts -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                        <path d="M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.95 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16zM2 4.27l3.11 3.11C3.29 8.12 2 9.91 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.59 1.21-2.9 2.76-3.07L8.73 11H8v2h2.73L13 15.27V17h1.73l4.01 4L20 19.74 3.27 3 2 4.27z"/>
                        <path d="M0 0h24v24H0z" fill="none"/>
                    </svg>
                <?php else : ?>
                    <!-- Instagram icon for other errors -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                <?php endif; ?>
            </div>
            <h3>
                <?php
                if ( $is_private_account ) {
                    esc_html_e( 'Private Account', 'bwg-instagram-feed' );
                } elseif ( $is_user_not_found ) {
                    esc_html_e( 'User Not Found', 'bwg-instagram-feed' );
                } elseif ( $is_rate_limit_error ) {
                    esc_html_e( 'Temporarily Unavailable', 'bwg-instagram-feed' );
                } elseif ( $is_connected_account_error ) {
                    esc_html_e( 'Account Disconnected', 'bwg-instagram-feed' );
                } else {
                    esc_html_e( 'No Posts Found', 'bwg-instagram-feed' );
                }
                ?>
            </h3>
            <p>
                <?php
                if ( $is_connected_account_error ) {
                    // Feature #34: Show different messages for admin vs end users
                    if ( current_user_can( 'manage_options' ) ) {
                        // Admin sees detailed message with link to reconnect
                        echo esc_html( $no_cache_message );
                        echo ' ';
                        printf(
                            /* translators: %1$s: opening link tag, %2$s: closing link tag */
                            esc_html__( 'Please %1$sreconnect the account%2$s to restore this feed.', 'bwg-instagram-feed' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=bwg-instagram-feed-accounts' ) ) . '">',
                            '</a>'
                        );
                    } else {
                        // End users see a friendly fallback message
                        esc_html_e( 'This Instagram feed is temporarily unavailable. Please check back later.', 'bwg-instagram-feed' );
                    }
                } elseif ( ! empty( $no_cache_message ) ) {
                    echo esc_html( $no_cache_message );
                } elseif ( $is_rate_limit_error ) {
                    esc_html_e( 'Instagram is temporarily limiting requests. Please wait a few minutes and try again later. We apologize for the inconvenience.', 'bwg-instagram-feed' );
                } else {
                    esc_html_e( 'This feed doesn\'t have any posts to display yet. Please check the Instagram username or try again later.', 'bwg-instagram-feed' );
                }
                ?>
            </p>
            <?php
            // Feature #35: Suggest using public feed if connected account has issues.
            // Show this suggestion only to admins when there's a connected account error.
            if ( $is_connected_account_error && current_user_can( 'manage_options' ) ) :
                // Get the username from the connected account info if available.
                $suggested_username = ! empty( $connected_account_info->username ) ? $connected_account_info->username : '';
            ?>
                <div class="bwg-igf-suggestion-box">
                    <strong><?php esc_html_e( 'Alternative: Use Public Feed', 'bwg-instagram-feed' ); ?></strong>
                    <p>
                        <?php
                        if ( ! empty( $suggested_username ) ) {
                            printf(
                                /* translators: %s: Instagram username */
                                esc_html__( 'While the connected account is unavailable, you can try switching this feed to a public feed using @%s.', 'bwg-instagram-feed' ),
                                esc_html( $suggested_username )
                            );
                        } else {
                            esc_html_e( 'While the connected account is unavailable, you can try switching this feed to a public feed by entering an Instagram username.', 'bwg-instagram-feed' );
                        }
                        ?>
                    </p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=edit&feed_id=' . $feed->id ) ); ?>" class="bwg-igf-suggestion-link">
                        <?php esc_html_e( 'Edit Feed Settings', 'bwg-instagram-feed' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <?php if ( 'slider' === $feed->layout_type ) : ?>
            <div class="bwg-igf-slider-track">
        <?php endif; ?>

        <?php foreach ( $posts as $post ) :
            // Generate proxy URLs for images to prevent CORS blocking
            $thumbnail_url = ! empty( $post['thumbnail'] ) ? BWG_IGF_Image_Proxy::get_proxy_url( $post['thumbnail'] ) : '';
            $full_image_url = ! empty( $post['full_image'] ) ? BWG_IGF_Image_Proxy::get_proxy_url( $post['full_image'] ) : '';

            // Feature #47: Check media type for video posts
            $media_type = isset( $post['media_type'] ) ? strtoupper( $post['media_type'] ) : 'IMAGE';
            $is_video = ( 'VIDEO' === $media_type ) || ( isset( $post['is_video'] ) && $post['is_video'] );
            $video_url = isset( $post['video_url'] ) ? $post['video_url'] : '';

            // Add video class if this is a video post
            $item_classes = array( 'bwg-igf-item' );
            if ( 'slider' === $feed->layout_type ) {
                $item_classes[] = 'bwg-igf-slider-slide';
            }
            if ( $is_video ) {
                $item_classes[] = 'bwg-igf-video-item';
            }
        ?>
            <div
                class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>"
                data-full-image="<?php echo esc_url( $full_image_url ); ?>"
                data-caption="<?php echo esc_attr( $post['caption'] ?? '' ); ?>"
                data-likes="<?php echo esc_attr( $post['likes'] ?? 0 ); ?>"
                data-comments="<?php echo esc_attr( $post['comments'] ?? 0 ); ?>"
                data-link="<?php echo esc_url( $post['link'] ?? '' ); ?>"
                <?php if ( $is_video && ! empty( $video_url ) ) : ?>
                data-video-url="<?php echo esc_url( $video_url ); ?>"
                data-media-type="video"
                <?php endif; ?>
            >
                <img
                    src="<?php echo esc_url( $thumbnail_url ); ?>"
                    alt="<?php echo esc_attr( $post['caption'] ?? __( 'Instagram post', 'bwg-instagram-feed' ) ); ?>"
                    loading="lazy"
                >

                <?php // Feature #47: Add play icon overlay for video posts ?>
                <?php if ( $is_video ) : ?>
                    <div class="bwg-igf-video-play-icon" aria-label="<?php esc_attr_e( 'Video', 'bwg-instagram-feed' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <?php if ( 'overlay' === $hover_effect ) : ?>
                    <div class="bwg-igf-overlay">
                        <div class="bwg-igf-overlay-content">
                            <?php if ( ! empty( $post['caption'] ) ) : ?>
                                <div class="bwg-igf-overlay-caption">
                                    <?php echo esc_html( wp_trim_words( $post['caption'], 15, '...' ) ); ?>
                                </div>
                            <?php endif; ?>
                            <div class="bwg-igf-stats">
                                <?php if ( ! empty( $display_settings['show_likes'] ) ) : ?>
                                    <span class="bwg-igf-stat">❤️ <?php echo esc_html( $post['likes'] ?? 0 ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! empty( $display_settings['show_comments'] ) ) : ?>
                                    <span class="bwg-igf-stat">💬 <?php echo esc_html( $post['comments'] ?? 0 ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $display_settings['show_caption'] ) && ! empty( $post['caption'] ) ) : ?>
                    <div class="bwg-igf-caption">
                        <?php echo esc_html( wp_trim_words( $post['caption'], 20, '...' ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ( 'slider' === $feed->layout_type ) : ?>
            </div>

            <?php if ( ! empty( $layout_settings['show_arrows'] ) ) : ?>
                <button class="bwg-igf-slider-nav bwg-igf-slider-prev" aria-label="<?php esc_attr_e( 'Previous', 'bwg-instagram-feed' ); ?>">‹</button>
                <button class="bwg-igf-slider-nav bwg-igf-slider-next" aria-label="<?php esc_attr_e( 'Next', 'bwg-instagram-feed' ); ?>">›</button>
            <?php endif; ?>

            <?php if ( ! empty( $layout_settings['show_dots'] ) ) : ?>
                <div class="bwg-igf-slider-dots">
                    <?php for ( $i = 0; $i < count( $posts ); $i++ ) : ?>
                        <button class="bwg-igf-slider-dot<?php echo 0 === $i ? ' active' : ''; ?>"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( ! empty( $display_settings['show_follow_button'] ) && ! empty( $feed->instagram_usernames ) ) :
        $usernames = json_decode( $feed->instagram_usernames, true ) ?: array( $feed->instagram_usernames );
        $first_username = is_array( $usernames ) ? $usernames[0] : $usernames;
        $button_style = isset( $display_settings['follow_button_style'] ) ? $display_settings['follow_button_style'] : 'gradient';
        $button_text = isset( $display_settings['follow_button_text'] ) && ! empty( $display_settings['follow_button_text'] ) ? $display_settings['follow_button_text'] : __( 'Follow on Instagram', 'bwg-instagram-feed' );
    ?>
        <div class="bwg-igf-follow-wrapper">
            <a href="https://instagram.com/<?php echo esc_attr( $first_username ); ?>" class="bwg-igf-follow bwg-igf-follow-<?php echo esc_attr( $button_style ); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( $button_text ); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
