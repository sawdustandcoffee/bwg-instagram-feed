<?php
/**
 * Set up a test feed with long captions for testing text truncation
 */

// Load WordPress.
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Check if a long caption feed already exists
$existing_feed = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE instagram_usernames LIKE %s",
        '%longcaptiontest%'
    )
);

if ($existing_feed) {
    echo "Long caption test feed already exists (ID: {$existing_feed->id})\n";
    $feed_id = $existing_feed->id;
} else {
    // Create a new feed for long caption testing
    $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_feeds',
        [
            'name' => 'Long Caption Test Feed',
            'slug' => 'long-caption-test',
            'feed_type' => 'public',
            'instagram_usernames' => json_encode(['longcaptiontest']),
            'layout_type' => 'grid',
            'layout_settings' => json_encode([
                'columns' => 3,
                'gap' => 10,
            ]),
            'display_settings' => json_encode([
                'show_likes' => true,
                'show_comments' => true,
                'show_follow_button' => true,
            ]),
            'styling_settings' => json_encode([
                'hover_effect' => 'overlay',
                'border_radius' => 8,
            ]),
            'popup_settings' => json_encode([
                'enabled' => true,
            ]),
            'post_count' => 12,
            'ordering' => 'newest',
            'cache_duration' => 3600,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
    );

    $feed_id = $wpdb->insert_id;
    echo "Created new feed with ID: {$feed_id}\n";
}

// Check if a test page already exists
$existing_page = $wpdb->get_var(
    "SELECT ID FROM {$wpdb->posts} WHERE post_title = 'Long Caption Test' AND post_status = 'publish'"
);

if ($existing_page) {
    echo "Long caption test page already exists (ID: {$existing_page})\n";
    $page_id = $existing_page;
} else {
    // Create a test page with the feed shortcode
    $page_content = "<!-- wp:paragraph -->\n<p>This page tests how the feed handles long captions and text content.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[bwg_igf id=\"{$feed_id}\"]\n<!-- /wp:shortcode -->";

    $page_id = wp_insert_post([
        'post_title' => 'Long Caption Test',
        'post_content' => $page_content,
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);

    echo "Created new test page with ID: {$page_id}\n";
}

// Clear any existing cache for this feed to force re-fetch
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_cache',
    ['feed_id' => $feed_id],
    ['%d']
);

echo "\nSetup complete!\n";
echo "View the long caption test at: http://localhost:8088/?page_id={$page_id}\n";
echo "Feed ID: {$feed_id}\n";
