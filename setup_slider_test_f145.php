<?php
/**
 * Setup test data for Feature #145: Slider arrows styled correctly
 * Run this in WordPress context
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

global $wpdb;

// Check if test feed already exists
$existing = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}bwg_igf_feeds WHERE name = 'Feature 145 Slider Test'");

if ($existing) {
    echo "Feed already exists with ID: {$existing}\n";
    $feed_id = $existing;
} else {
    // Create slider feed with navigation arrows enabled
    $feed_data = array(
        'name' => 'Feature 145 Slider Test',
        'slug' => 'feature-145-slider-test',
        'feed_type' => 'public',
        'instagram_usernames' => json_encode(array('instagram')),
        'layout_type' => 'slider',
        'layout_settings' => json_encode(array(
            'slides_to_show' => 3,
            'slides_to_scroll' => 1,
            'autoplay' => false,
            'autoplay_speed' => 3000,
            'show_arrows' => true,
            'show_dots' => true,
            'infinite' => true
        )),
        'display_settings' => json_encode(array(
            'show_likes' => true,
            'show_comments' => true,
            'show_caption' => false,
            'show_follow' => false
        )),
        'styling_settings' => json_encode(array(
            'background_color' => '#ffffff',
            'border_radius' => '8',
            'hover_effect' => 'overlay',
            'overlay_color' => 'rgba(0, 0, 0, 0.5)'
        )),
        'popup_settings' => json_encode(array(
            'enabled' => true
        )),
        'post_count' => 9,
        'ordering' => 'newest',
        'cache_duration' => 3600,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );

    $wpdb->insert("{$wpdb->prefix}bwg_igf_feeds", $feed_data);
    $feed_id = $wpdb->insert_id;
    echo "Created feed with ID: {$feed_id}\n";
}

// Create cache entries with 9 test posts for the slider
$existing_cache = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
    $feed_id
));

if (!$existing_cache) {
    $test_posts = array();
    for ($i = 1; $i <= 9; $i++) {
        $test_posts[] = array(
            'id' => 'test_post_' . $i,
            'thumbnail' => 'https://picsum.photos/400/400?random=' . $i,
            'full_image' => 'https://picsum.photos/800/800?random=' . $i,
            'caption' => 'Test slider post #' . $i . ' for Feature 145',
            'link' => 'https://instagram.com/p/test' . $i,
            'likes' => rand(100, 5000),
            'comments' => rand(5, 200),
            'timestamp' => time() - ($i * 3600)
        );
    }

    $cache_data = array(
        'feed_id' => $feed_id,
        'cache_key' => 'feed_' . $feed_id . '_posts',
        'cache_data' => json_encode($test_posts),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
    );

    $wpdb->insert("{$wpdb->prefix}bwg_igf_cache", $cache_data);
    echo "Created cache with 9 test posts\n";
} else {
    echo "Cache already exists\n";
}

// Create or update test page
$page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->prefix}posts WHERE post_name = 'slider-test-f145' AND post_type = 'page'");

if (!$page_id) {
    $page_data = array(
        'post_title' => 'Slider Test Feature 145',
        'post_name' => 'slider-test-f145',
        'post_content' => '[bwg_igf id="' . $feed_id . '"]',
        'post_status' => 'publish',
        'post_type' => 'page'
    );
    $page_id = wp_insert_post($page_data);
    echo "Created test page with ID: {$page_id}\n";
} else {
    // Update page content with correct feed ID
    wp_update_post(array(
        'ID' => $page_id,
        'post_content' => '[bwg_igf id="' . $feed_id . '"]'
    ));
    echo "Updated test page ID: {$page_id}\n";
}

echo "\n=== Test Setup Complete ===\n";
echo "Feed ID: {$feed_id}\n";
echo "Page ID: {$page_id}\n";
echo "Test URL: http://localhost:8088/?page_id={$page_id}\n";
echo "Admin URL: http://localhost:8088/wp-admin/admin.php?page=bwg-igf-feeds&action=edit&id={$feed_id}\n";
