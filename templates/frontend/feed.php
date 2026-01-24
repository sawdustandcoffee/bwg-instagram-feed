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

// Get feed by ID or slug
$feed_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : 0;
$feed_slug = ! empty( $atts['feed'] ) ? sanitize_title( $atts['feed'] ) : '';

global $wpdb;

if ( $feed_id > 0 ) {
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d AND status = 'active'",
        $feed_id
    ) );
} elseif ( ! empty( $feed_slug ) ) {
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s AND status = 'active'",
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

// Get cached posts first
global $wpdb;
$cache_data = $wpdb->get_var( $wpdb->prepare(
    "SELECT cache_data FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
    $feed->id
) );

if ( $cache_data ) {
    $posts = json_decode( $cache_data, true ) ?: array();
} else {
    $posts = array();
}

// If no cached posts and feed has usernames, fetch from Instagram and cache
$no_cache_message = '';
if ( empty( $posts ) && ! empty( $feed->instagram_usernames ) ) {
    // Load the Instagram API class if not already loaded.
    if ( ! class_exists( 'BWG_IGF_Instagram_API' ) ) {
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-api.php';
    }

    $instagram_api = new BWG_IGF_Instagram_API();

    // Parse usernames (could be JSON array or single username).
    $usernames = json_decode( $feed->instagram_usernames, true );
    if ( ! is_array( $usernames ) ) {
        $usernames = array_map( 'trim', explode( ',', $feed->instagram_usernames ) );
    }

    // Clean usernames (remove @ if present).
    $usernames = array_filter( array_map( function( $u ) {
        return ltrim( trim( $u ), '@' );
    }, $usernames ) );

    $post_count = absint( $feed->post_count ) ?: 9;

    // Fetch posts from Instagram.
    if ( count( $usernames ) === 1 ) {
        $fetched_posts = $instagram_api->fetch_public_posts( $usernames[0], $post_count );
    } else {
        $fetched_posts = $instagram_api->fetch_combined_posts( $usernames, $post_count );
    }

    // Check if we got valid posts (not an error).
    if ( ! is_wp_error( $fetched_posts ) && ! empty( $fetched_posts ) ) {
        $posts = $fetched_posts;

        // Cache the fetched posts.
        $cache_duration = absint( $feed->cache_duration ) ?: 3600;
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + $cache_duration );
        $cache_key = 'feed_' . $feed->id . '_' . md5( wp_json_encode( $posts ) );

        // Delete old cache entries for this feed.
        $wpdb->delete(
            $wpdb->prefix . 'bwg_igf_cache',
            array( 'feed_id' => $feed->id ),
            array( '%d' )
        );

        // Insert new cache entry with real Instagram data.
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
    } else {
        // Real Instagram data could not be fetched - do NOT use placeholder/mock data.
        // Instead, display an informative error message to the user.
        $error_detail = is_wp_error( $fetched_posts ) ? $fetched_posts->get_error_message() : __( 'No posts found.', 'bwg-instagram-feed' );
        $no_cache_message = sprintf(
            /* translators: %s: error detail */
            __( 'Could not fetch Instagram posts: %s', 'bwg-instagram-feed' ),
            $error_detail
        );
    }
}

// Apply ordering based on feed settings
$ordering = isset( $feed->ordering ) ? $feed->ordering : 'newest';

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

if ( ! empty( $background_color ) ) {
    $custom_styles[] = 'background-color: ' . esc_attr( $background_color );
    $custom_styles[] = 'padding: 15px';
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

// Output custom CSS if provided
if ( ! empty( $custom_css ) ) :
?>
<style type="text/css" id="bwg-igf-custom-css-<?php echo esc_attr( $feed->id ); ?>">
<?php echo wp_strip_all_tags( $custom_css ); ?>
</style>
<?php endif; ?>
<div
    class="<?php echo esc_attr( implode( ' ', $feed_classes ) ); ?>"
    data-feed-id="<?php echo esc_attr( $feed->id ); ?>"
    data-popup="<?php echo $popup_enabled ? 'true' : 'false'; ?>"
    style="<?php echo esc_attr( implode( '; ', $custom_styles ) ); ?>"
>
    <?php if ( empty( $posts ) ) : ?>
        <div class="bwg-igf-empty-state">
            <p><?php echo esc_html( $no_cache_message ?: __( 'No posts to display.', 'bwg-instagram-feed' ) ); ?></p>
        </div>
    <?php else : ?>
        <?php if ( 'slider' === $feed->layout_type ) : ?>
            <div class="bwg-igf-slider-track">
        <?php endif; ?>

        <?php foreach ( $posts as $post ) : ?>
            <div
                class="bwg-igf-item<?php echo 'slider' === $feed->layout_type ? ' bwg-igf-slider-slide' : ''; ?>"
                data-full-image="<?php echo esc_url( $post['full_image'] ?? '' ); ?>"
                data-caption="<?php echo esc_attr( $post['caption'] ?? '' ); ?>"
                data-likes="<?php echo esc_attr( $post['likes'] ?? 0 ); ?>"
                data-comments="<?php echo esc_attr( $post['comments'] ?? 0 ); ?>"
                data-link="<?php echo esc_url( $post['link'] ?? '' ); ?>"
            >
                <img
                    src="<?php echo esc_url( $post['thumbnail'] ?? '' ); ?>"
                    alt="<?php echo esc_attr( $post['caption'] ?? __( 'Instagram post', 'bwg-instagram-feed' ) ); ?>"
                    loading="lazy"
                >

                <?php if ( 'overlay' === $hover_effect ) : ?>
                    <div class="bwg-igf-overlay">
                        <div class="bwg-igf-overlay-content">
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
    ?>
        <div class="bwg-igf-follow-wrapper">
            <a href="https://instagram.com/<?php echo esc_attr( $first_username ); ?>" class="bwg-igf-follow" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( $display_settings['follow_button_text'] ?? __( 'Follow on Instagram', 'bwg-instagram-feed' ) ); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
