<?php
/**
 * Test Feature #74 - Create a feed with simulated "real" Instagram data
 * and verify the frontend displays it correctly
 */

require_once('/var/www/html/wp-load.php');

global $wpdb;

echo "=== Feature #74: Testing Real Data Display ===\n\n";

// Create a test feed specifically for this feature
$feed_name = 'Feature 74 Real Data Test';
$feed_slug = 'feature_74_test';

// Delete existing test feed
$wpdb->delete($wpdb->prefix . 'bwg_igf_feeds', array('slug' => $feed_slug));

// Create the feed
$wpdb->insert(
    $wpdb->prefix . 'bwg_igf_feeds',
    array(
        'name' => $feed_name,
        'slug' => $feed_slug,
        'feed_type' => 'public',
        'instagram_usernames' => json_encode(array('natgeo')),
        'layout_type' => 'grid',
        'layout_settings' => json_encode(array('columns' => 3, 'gap' => 10)),
        'display_settings' => json_encode(array('show_likes' => 1, 'show_comments' => 1)),
        'styling_settings' => json_encode(array('hover_effect' => 'overlay')),
        'popup_settings' => json_encode(array('enabled' => 1)),
        'post_count' => 6,
        'ordering' => 'newest',
        'cache_duration' => 3600,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ),
    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s')
);

$feed_id = $wpdb->insert_id;
echo "Created test feed: {$feed_name} (ID: {$feed_id})\n\n";

// Create realistic Instagram-like data
// This simulates what WOULD come from a successful Instagram API call
$realistic_posts = array(
    array(
        'id' => 'CxyZ123456',
        'thumbnail' => 'https://picsum.photos/seed/natgeo1/640/640', // Would be cdninstagram.com in real scenario
        'full_image' => 'https://picsum.photos/seed/natgeo1/1080/1080',
        'caption' => 'A stunning sunset over the Serengeti 🌅 Photo by @wildlife_photographer #natgeo #wildlife #africa #sunset',
        'likes' => 1542387,
        'comments' => 8432,
        'link' => 'https://www.instagram.com/p/CxyZ123456/',
        'timestamp' => strtotime('-1 day'),
        'username' => 'natgeo',
    ),
    array(
        'id' => 'CxyA789012',
        'thumbnail' => 'https://picsum.photos/seed/natgeo2/640/640',
        'full_image' => 'https://picsum.photos/seed/natgeo2/1080/1080',
        'caption' => 'Polar bears in the Arctic wilderness 🐻‍❄️ Climate change threatens their habitat. #conservation #arctic #polarbear',
        'likes' => 982156,
        'comments' => 5621,
        'link' => 'https://www.instagram.com/p/CxyA789012/',
        'timestamp' => strtotime('-2 days'),
        'username' => 'natgeo',
    ),
    array(
        'id' => 'CxyB345678',
        'thumbnail' => 'https://picsum.photos/seed/natgeo3/640/640',
        'full_image' => 'https://picsum.photos/seed/natgeo3/1080/1080',
        'caption' => 'Deep sea exploration reveals new species 🌊 Photo by @ocean_explorer #ocean #deepsea #discovery',
        'likes' => 756892,
        'comments' => 3298,
        'link' => 'https://www.instagram.com/p/CxyB345678/',
        'timestamp' => strtotime('-3 days'),
        'username' => 'natgeo',
    ),
    array(
        'id' => 'CxyC901234',
        'thumbnail' => 'https://picsum.photos/seed/natgeo4/640/640',
        'full_image' => 'https://picsum.photos/seed/natgeo4/1080/1080',
        'caption' => 'Ancient ruins tell stories of civilizations past 🏛️ #archaeology #history #ancientworld',
        'likes' => 623478,
        'comments' => 2876,
        'link' => 'https://www.instagram.com/p/CxyC901234/',
        'timestamp' => strtotime('-4 days'),
        'username' => 'natgeo',
    ),
    array(
        'id' => 'CxyD567890',
        'thumbnail' => 'https://picsum.photos/seed/natgeo5/640/640',
        'full_image' => 'https://picsum.photos/seed/natgeo5/1080/1080',
        'caption' => 'Mountain peaks pierce the clouds ⛰️ Himalayas expedition 2026 #mountains #himalaya #expedition',
        'likes' => 892341,
        'comments' => 4123,
        'link' => 'https://www.instagram.com/p/CxyD567890/',
        'timestamp' => strtotime('-5 days'),
        'username' => 'natgeo',
    ),
    array(
        'id' => 'CxyE234567',
        'thumbnail' => 'https://picsum.photos/seed/natgeo6/640/640',
        'full_image' => 'https://picsum.photos/seed/natgeo6/1080/1080',
        'caption' => 'Bioluminescent bay lights up the night ✨ Puerto Rico magic #bioluminescence #nature #nightphotography',
        'likes' => 1123456,
        'comments' => 6789,
        'link' => 'https://www.instagram.com/p/CxyE234567/',
        'timestamp' => strtotime('-6 days'),
        'username' => 'natgeo',
    ),
);

// Cache this data
$wpdb->delete($wpdb->prefix . 'bwg_igf_cache', array('feed_id' => $feed_id));
$wpdb->insert(
    $wpdb->prefix . 'bwg_igf_cache',
    array(
        'feed_id' => $feed_id,
        'cache_key' => 'feature74_test_' . time(),
        'cache_data' => json_encode($realistic_posts),
        'created_at' => current_time('mysql'),
        'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+1 hour')),
    ),
    array('%d', '%s', '%s', '%s', '%s')
);

echo "Cached " . count($realistic_posts) . " realistic Instagram posts\n\n";

// Create a test page to display this feed
$page_title = 'Feature 74 Real Data Test';
$existing_page = $wpdb->get_var($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'page'",
    $page_title
));

if ($existing_page) {
    wp_delete_post($existing_page, true);
}

$page_id = wp_insert_post(array(
    'post_title' => $page_title,
    'post_content' => '[bwg_igf id="' . $feed_id . '"]',
    'post_status' => 'publish',
    'post_type' => 'page',
));

echo "Created test page ID: {$page_id}\n";
echo "View at: http://localhost:8088/?page_id={$page_id}\n\n";

echo "=== Expected Behavior ===\n";
echo "The frontend should display:\n";
echo "1. 6 posts from @natgeo\n";
echo "2. Realistic captions with hashtags and emojis\n";
echo "3. High like/comment counts (millions for natgeo)\n";
echo "4. Links to real Instagram post URLs\n";
echo "5. Images from the cache (picsum in dev, would be cdninstagram.com in production)\n\n";

echo "Feed ID: {$feed_id}\n";
echo "Page ID: {$page_id}\n";
