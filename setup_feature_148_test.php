<?php
/**
 * Setup script for Feature #148: Follow button styled correctly
 * Creates a test feed with Follow button enabled and custom text
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

global $wpdb;

// First clean up any existing test feed for this feature
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('name' => 'Feature 148 Follow Button Test'),
    array('%s')
);

// Create a new feed with Follow button enabled and custom text
$feed_data = array(
    'name'                 => 'Feature 148 Follow Button Test',
    'slug'                 => 'feature-148-follow-button-test',
    'feed_type'            => 'public',
    'instagram_usernames'  => 'testuser',
    'connected_account_id' => 0,
    'layout_type'          => 'grid',
    'layout_settings'      => json_encode(array(
        'columns' => 3,
        'gap'     => 10
    )),
    'display_settings'     => json_encode(array(
        'show_likes'         => true,
        'show_comments'      => true,
        'show_caption'       => false,
        'show_follow_button' => true,
        'follow_button_text' => 'Follow @testuser on Instagram'
    )),
    'styling_settings'     => json_encode(array(
        'background_color' => '',
        'border_radius'    => 8,
        'hover_effect'     => 'zoom',
        'overlay_color'    => 'rgba(0,0,0,0.5)',
        'custom_css'       => ''
    )),
    'filter_settings'      => json_encode(array(
        'hashtag_include' => '',
        'hashtag_exclude' => ''
    )),
    'popup_settings'       => json_encode(array(
        'enabled' => true
    )),
    'post_count'           => 9,
    'ordering'             => 'newest',
    'cache_duration'       => 3600,
    'status'               => 'active',
    'created_at'           => current_time('mysql'),
    'updated_at'           => current_time('mysql')
);

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_feeds',
    $feed_data,
    array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s')
);

if ($result) {
    $feed_id = $wpdb->insert_id;
    echo "SUCCESS: Feed created with ID: $feed_id\n";
    echo "Feed Name: Feature 148 Follow Button Test\n";
    echo "Follow Button Enabled: Yes\n";
    echo "Custom Follow Button Text: 'Follow @testuser on Instagram'\n";
    echo "\nShortcode: [bwg_igf id=\"$feed_id\"]\n";

    // Create a test page if it doesn't exist
    $page_exists = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_name = 'feature-148-test' AND post_type = 'page' AND post_status = 'publish'");

    if (!$page_exists) {
        $page_id = wp_insert_post(array(
            'post_title'   => 'Feature 148 Follow Button Test',
            'post_name'    => 'feature-148-test',
            'post_content' => '[bwg_igf id="' . $feed_id . '"]',
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ));
        echo "\nTest page created with ID: $page_id\n";
        echo "Test page URL: " . home_url('/feature-148-test/') . "\n";
    } else {
        // Update the existing page with the new feed ID
        wp_update_post(array(
            'ID'           => $page_exists,
            'post_content' => '[bwg_igf id="' . $feed_id . '"]'
        ));
        echo "\nExisting test page updated with ID: $page_exists\n";
        echo "Test page URL: " . home_url('/feature-148-test/') . "\n";
    }

    // Add some test cache data so the feed displays properly
    $test_posts = array();
    for ($i = 1; $i <= 9; $i++) {
        $test_posts[] = array(
            'id'         => "post_$i",
            'thumbnail'  => "https://picsum.photos/300/300?random=$i",
            'full_image' => "https://picsum.photos/800/800?random=$i",
            'caption'    => "Test post #$i for Feature 148 Follow Button Test",
            'link'       => "https://instagram.com/p/test$i",
            'likes'      => rand(100, 5000),
            'comments'   => rand(10, 200),
            'timestamp'  => time() - ($i * 3600)
        );
    }

    // Insert cache
    $wpdb->delete($wpdb->prefix . 'bwg_igf_cache', array('feed_id' => $feed_id), array('%d'));
    $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_cache',
        array(
            'feed_id'    => $feed_id,
            'cache_data' => json_encode($test_posts),
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ),
        array('%d', '%s', '%s', '%s')
    );
    echo "Cache data populated with 9 test posts.\n";

} else {
    echo "ERROR: Failed to create feed\n";
    echo "Database error: " . $wpdb->last_error . "\n";
}
