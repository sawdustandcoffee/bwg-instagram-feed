<?php
/**
 * Test script to set up a feed with many posts for lazy loading testing
 * Run via: docker exec bwg-igf-wpcli php /var/www/html/wp-content/plugins/bwg-instagram-feed/setup_lazy_loading_test.php
 */

require_once '/var/www/html/wp-load.php';

global $wpdb;

// Create a test feed if doesn't exist
$existing_feed = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE name = 'Lazy Loading Test'");

if (!$existing_feed) {
    // Create new feed for lazy loading test
    $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_feeds',
        array(
            'name' => 'Lazy Loading Test',
            'slug' => 'lazy-loading-test',
            'feed_type' => 'public',
            'instagram_usernames' => '["testuser"]',
            'layout_type' => 'grid',
            'layout_settings' => json_encode(array(
                'columns' => 3,
                'gap' => 10,
            )),
            'display_settings' => json_encode(array(
                'show_likes' => true,
                'show_comments' => true,
            )),
            'styling_settings' => json_encode(array(
                'hover_effect' => 'overlay',
                'border_radius' => 8,
            )),
            'popup_settings' => json_encode(array(
                'enabled' => true,
            )),
            'post_count' => 12,
            'ordering' => 'newest',
            'cache_duration' => 3600,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        )
    );
    $feed_id = $wpdb->insert_id;
    echo "Created feed ID: $feed_id\n";
} else {
    $feed_id = $existing_feed->id;
    echo "Using existing feed ID: $feed_id\n";
}

// Generate 12 test posts with random placeholder images
$test_posts = array();
for ($i = 1; $i <= 12; $i++) {
    $test_posts[] = array(
        'id' => 'test_post_' . $i,
        'thumbnail' => 'https://picsum.photos/seed/post' . $i . '/400/400',
        'full_image' => 'https://picsum.photos/seed/post' . $i . '/800/800',
        'caption' => 'Test post #' . $i . ' for lazy loading verification',
        'likes' => rand(10, 500),
        'comments' => rand(1, 50),
        'link' => 'https://instagram.com/p/test' . $i,
        'timestamp' => time() - ($i * 3600), // 1 hour apart
    );
}

// Delete old cache and insert new one
$wpdb->delete($wpdb->prefix . 'bwg_igf_cache', array('feed_id' => $feed_id));
$wpdb->insert(
    $wpdb->prefix . 'bwg_igf_cache',
    array(
        'feed_id' => $feed_id,
        'cache_key' => 'feed_' . $feed_id,
        'cache_data' => json_encode($test_posts),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
    )
);
echo "Created cache with 12 test posts\n";

// Create or update test page
$page_title = 'Feature 143 Lazy Loading Test';
$existing_page = get_page_by_title($page_title, OBJECT, 'page');

if ($existing_page) {
    wp_update_post(array(
        'ID' => $existing_page->ID,
        'post_content' => '[bwg_igf id="' . $feed_id . '"]',
        'post_status' => 'publish',
    ));
    $page_id = $existing_page->ID;
    echo "Updated page ID: $page_id\n";
} else {
    $page_id = wp_insert_post(array(
        'post_title' => $page_title,
        'post_content' => '[bwg_igf id="' . $feed_id . '"]',
        'post_status' => 'publish',
        'post_type' => 'page',
    ));
    echo "Created page ID: $page_id\n";
}

echo "\n=== Test Setup Complete ===\n";
echo "Feed ID: $feed_id\n";
echo "Page ID: $page_id\n";
echo "Test URL: http://localhost:8088/?page_id=$page_id\n";
echo "\nThe page has 12 images configured for lazy loading testing.\n";
