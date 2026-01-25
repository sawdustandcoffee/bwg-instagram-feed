<?php
/**
 * Setup test data for Feature #146: Pagination dots styled correctly
 * Creates a test page and populates cache for the slider feed
 */

define('WP_USE_THEMES', false);
require('/var/www/html/wp-load.php');

global $wpdb;

echo "Setting up Feature #146 test environment...\n\n";

// Use the existing slider feed (ID 9 - Feature 145 Slider Test)
$feed_id = 9;

// Check if feed exists
$feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
    $feed_id
));

if (!$feed) {
    echo "ERROR: Feed ID $feed_id not found!\n";
    exit(1);
}

echo "Found feed: {$feed->name}\n";

// Make sure it's configured as a slider with pagination dots
$layout_settings = json_decode($feed->layout_settings, true) ?: [];
$layout_settings['show_pagination'] = true;
$layout_settings['slides_to_show'] = 3;
$layout_settings['slides_to_scroll'] = 1;
$layout_settings['infinite'] = true;

$wpdb->update(
    $wpdb->prefix . 'bwg_igf_feeds',
    array(
        'layout_type' => 'slider',
        'layout_settings' => json_encode($layout_settings),
        'post_count' => 9
    ),
    array('id' => $feed_id)
);
echo "Feed settings updated for slider with pagination dots\n";

// Clear any existing cache for this feed
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_cache',
    array('feed_id' => $feed_id)
);
echo "Cleared existing cache\n";

// Create test posts data for the cache (9 posts for a slider with dots)
$test_posts = array();
for ($i = 1; $i <= 9; $i++) {
    $test_posts[] = array(
        'id' => 'test_post_146_' . $i,
        'thumbnail' => 'https://picsum.photos/400/400?random=' . $i,
        'full_image' => 'https://picsum.photos/800/800?random=' . $i,
        'caption' => 'Feature 146 Test Image ' . $i . ' - Pagination Dots Test',
        'likes' => rand(100, 999),
        'comments' => rand(10, 99),
        'link' => 'https://instagram.com/p/test' . $i,
        'timestamp' => time() - ($i * 3600)
    );
}

// Insert cache entry
$cache_key = 'feed_' . $feed_id . '_posts';
$cache_data = json_encode($test_posts);
$expires_at = date('Y-m-d H:i:s', time() + 3600);

$wpdb->insert(
    $wpdb->prefix . 'bwg_igf_cache',
    array(
        'feed_id' => $feed_id,
        'cache_key' => $cache_key,
        'cache_data' => $cache_data,
        'created_at' => current_time('mysql'),
        'expires_at' => $expires_at
    )
);
echo "Created cache with 9 test posts\n";

// Check if test page already exists for this feature
$test_page_title = 'Feature 146 Pagination Dots Test';
$existing_page = get_page_by_title($test_page_title);

if ($existing_page) {
    $page_id = $existing_page->ID;
    echo "Test page already exists (ID: $page_id)\n";
} else {
    // Create a test page with the slider shortcode
    $page_id = wp_insert_post(array(
        'post_title' => $test_page_title,
        'post_content' => "<!-- wp:shortcode -->\n[bwg_igf id=\"$feed_id\"]\n<!-- /wp:shortcode -->",
        'post_status' => 'publish',
        'post_type' => 'page'
    ));

    if (is_wp_error($page_id)) {
        echo "ERROR: Failed to create test page: " . $page_id->get_error_message() . "\n";
        exit(1);
    }
    echo "Created test page (ID: $page_id)\n";
}

// Get the page URL
$page_url = get_permalink($page_id);
echo "\n=== Setup Complete ===\n";
echo "Feed ID: $feed_id\n";
echo "Layout: Slider with pagination dots\n";
echo "Posts in cache: 9\n";
echo "Test page URL: $page_url\n";
echo "Page ID: $page_id\n";
