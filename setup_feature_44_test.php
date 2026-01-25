<?php
/**
 * Setup test for Feature #44: Shortcode with slug attribute
 * Creates a feed with known slug and a page using shortcode with feed slug
 */
require_once('/var/www/html/wp-load.php');

global $wpdb;

// Test slug
$test_slug = 'my-test-slug-f44';

// Check if feed with this slug already exists
$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s",
    $test_slug
));

if ($existing) {
    echo "Feed with slug '{$test_slug}' already exists (ID: {$existing})\n";
} else {
    // Create a new feed with known slug
    $result = $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_feeds',
        array(
            'name' => 'Feature 44 Slug Test',
            'slug' => $test_slug,
            'feed_type' => 'public',
            'instagram_usernames' => 'testuser',
            'layout_type' => 'grid',
            'layout_settings' => json_encode(array(
                'columns' => 3,
                'gap' => 10,
            )),
            'display_settings' => json_encode(array(
                'show_likes' => 1,
                'show_comments' => 1,
            )),
            'styling_settings' => json_encode(array(
                'border_radius' => 8,
                'hover_effect' => 'zoom',
            )),
            'filter_settings' => json_encode(array()),
            'popup_settings' => json_encode(array('enabled' => 1)),
            'post_count' => 9,
            'ordering' => 'newest',
            'cache_duration' => 3600,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s')
    );

    if ($result) {
        $feed_id = $wpdb->insert_id;
        echo "Created feed 'Feature 44 Slug Test' with ID: {$feed_id} and slug: {$test_slug}\n";
    } else {
        echo "Error creating feed: " . $wpdb->last_error . "\n";
        exit(1);
    }
}

// Get the feed ID
$feed_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s",
    $test_slug
));

echo "\nFeed ID: {$feed_id}\n";
echo "Feed Slug: {$test_slug}\n";

// Add cache data for the feed
$cache_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
    $feed_id
));

if (!$cache_exists) {
    // Create mock posts for the cache
    $posts = array();
    for ($i = 1; $i <= 9; $i++) {
        $posts[] = array(
            'id' => 'f44_' . $i,
            'thumbnail' => "https://picsum.photos/seed/f44-{$i}/400/400",
            'full_image' => "https://picsum.photos/seed/f44-{$i}/800/800",
            'caption' => "Feature 44 Test Post #{$i} - Testing shortcode with slug attribute",
            'link' => "https://instagram.com/p/f44test{$i}",
            'likes' => rand(100, 5000),
            'comments' => rand(5, 200),
            'timestamp' => time() - ($i * 3600),
        );
    }

    $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_cache',
        array(
            'feed_id' => $feed_id,
            'cache_data' => json_encode($posts),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s')
    );
    echo "Added cache data for feed\n";
} else {
    echo "Cache data already exists for feed\n";
}

// Create a test page with the shortcode using feed slug
$page_title = 'Feature 44 Slug Shortcode Test';
$existing_page = get_page_by_title($page_title, OBJECT, 'page');

if ($existing_page) {
    echo "Test page already exists: ID {$existing_page->ID}\n";
    echo "Page URL: " . get_permalink($existing_page->ID) . "\n";
} else {
    // Create page with shortcode using slug attribute
    $page_content = "<h2>Testing Shortcode with Feed Slug</h2>\n\n";
    $page_content .= "<p>This page uses the shortcode with the <code>feed</code> attribute (slug) instead of <code>id</code>.</p>\n\n";
    $page_content .= "<p><strong>Shortcode used:</strong> <code>[bwg_igf feed=\"{$test_slug}\"]</code></p>\n\n";
    $page_content .= "[bwg_igf feed=\"{$test_slug}\"]\n\n";
    $page_content .= "<hr>\n\n";
    $page_content .= "<p>If you see the Instagram feed grid above, Feature #44 is working correctly!</p>";

    $page_id = wp_insert_post(array(
        'post_title' => $page_title,
        'post_content' => $page_content,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_author' => 1,
    ));

    if ($page_id && !is_wp_error($page_id)) {
        echo "Created test page: ID {$page_id}\n";
        echo "Page URL: " . get_permalink($page_id) . "\n";
    } else {
        echo "Error creating test page\n";
    }
}

echo "\n=== Test Instructions ===\n";
echo "1. Visit the test page at the URL above\n";
echo "2. Verify that the feed displays correctly\n";
echo "3. The shortcode [bwg_igf feed=\"{$test_slug}\"] should load feed ID {$feed_id}\n";
