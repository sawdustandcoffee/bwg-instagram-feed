<?php
/**
 * Setup Feature #140 test: Feed loads in acceptable time
 * Creates a feed with 9 posts and populates cache
 */

require_once('/var/www/html/wp-load.php');

global $wpdb;

$table_feeds = $wpdb->prefix . 'bwg_igf_feeds';
$table_cache = $wpdb->prefix . 'bwg_igf_cache';

// First, check if our test feed already exists
$existing_feed = $wpdb->get_var("SELECT id FROM {$table_feeds} WHERE name = 'Feature 140 Performance Test'");

if ($existing_feed) {
    echo "Test feed already exists (ID: {$existing_feed}), skipping creation...\n";
} else {
    // Check if feed ID 4 exists
    $feed_4 = $wpdb->get_var("SELECT id FROM {$table_feeds} WHERE id = 4");
    if (!$feed_4) {
        echo "Feed ID 4 not found, will create new feed...\n";
    }
}

// Create the feed with 9 posts configured
$feed_data = array(
    'name'                => 'Feature 140 Performance Test',
    'slug'                => 'feature-140-performance',
    'feed_type'           => 'public',
    'instagram_usernames' => json_encode(array('testuser')),
    'connected_account_id' => null,
    'layout_type'         => 'grid',
    'layout_settings'     => json_encode(array(
        'columns' => 3,
        'gap' => 10,
        'rows' => 3
    )),
    'display_settings'    => json_encode(array(
        'show_likes'          => true,
        'show_comments'       => true,
        'show_caption'        => true,
        'show_follow_button'  => true,
        'follow_button_text'  => 'Follow on Instagram'
    )),
    'styling_settings'    => json_encode(array(
        'hover_effect'     => 'overlay',
        'border_radius'    => 8,
        'background_color' => ''
    )),
    'popup_settings'      => json_encode(array(
        'enabled' => true
    )),
    'filter_settings'     => json_encode(array()),
    'post_count'          => 9,
    'ordering'            => 'newest',
    'cache_duration'      => 3600,
    'status'              => 'active',
    'error_message'       => null,
    'created_at'          => current_time('mysql'),
    'updated_at'          => current_time('mysql')
);

$result = $wpdb->insert($table_feeds, $feed_data);

if (!$result) {
    echo "Error creating feed: " . $wpdb->last_error . "\n";
    exit(1);
}

$feed_id = $wpdb->insert_id;
echo "Created feed ID: {$feed_id}\n";

// Generate 9 test posts with realistic data
$posts = array();
$base_timestamp = time();

for ($i = 1; $i <= 9; $i++) {
    $posts[] = array(
        'id'         => 'perf_test_' . $i,
        'thumbnail'  => 'https://picsum.photos/300/300?random=' . $i,
        'full_image' => 'https://picsum.photos/640/640?random=' . $i,
        'caption'    => "Performance test post {$i} from @testuser - This is a sample caption for testing the feed loading time. #performance #test",
        'likes'      => rand(100, 5000),
        'comments'   => rand(10, 200),
        'link'       => "https://instagram.com/p/perftest{$i}/",
        'timestamp'  => $base_timestamp - ($i * 3600), // Each post 1 hour older
    );
}

// Insert cache entry with the 9 posts
$cache_data = array(
    'feed_id'    => $feed_id,
    'cache_key'  => 'feed_' . $feed_id,
    'cache_data' => json_encode($posts),
    'created_at' => current_time('mysql'),
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
);

$result = $wpdb->insert($table_cache, $cache_data);

if (!$result) {
    echo "Error creating cache: " . $wpdb->last_error . "\n";
    exit(1);
}

echo "Cache populated with 9 posts\n";

// Create a test page with the shortcode
$page_id = wp_insert_post(array(
    'post_title'   => 'Feature 140 Performance Test',
    'post_content' => '[bwg_igf id="' . $feed_id . '"]',
    'post_status'  => 'publish',
    'post_type'    => 'page'
));

if (is_wp_error($page_id)) {
    echo "Error creating page: " . $page_id->get_error_message() . "\n";
    exit(1);
}

echo "Created test page ID: {$page_id}\n";
echo "Test URL: http://localhost:8088/?page_id={$page_id}\n";

// Verify the setup
$verify_feed = $wpdb->get_row("SELECT * FROM {$table_feeds} WHERE id = {$feed_id}");
$verify_cache = $wpdb->get_row("SELECT * FROM {$table_cache} WHERE feed_id = {$feed_id}");

echo "\nVerification:\n";
echo "  Feed exists: " . ($verify_feed ? "YES" : "NO") . "\n";
echo "  Cache exists: " . ($verify_cache ? "YES" : "NO") . "\n";
echo "  Post count: " . $verify_feed->post_count . "\n";

echo "\nSetup complete! Ready to test load time.\n";
