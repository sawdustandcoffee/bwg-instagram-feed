<?php
/**
 * Check Feature #74 - Instagram posts displayed are real
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

global $wpdb;

echo "=== Feature #74 Verification ===\n\n";

// Get feeds
$feeds = $wpdb->get_results("SELECT id, name, instagram_usernames, feed_type FROM {$wpdb->prefix}bwg_igf_feeds LIMIT 5");

echo "Feeds in database:\n";
foreach ($feeds as $feed) {
    echo "  - ID: {$feed->id}, Name: {$feed->name}, Username: {$feed->instagram_usernames}, Type: {$feed->feed_type}\n";
}

echo "\n";

// Check cache data for first feed
if (!empty($feeds)) {
    $feed = $feeds[0];
    $cache = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d LIMIT 1",
        $feed->id
    ));

    if ($cache) {
        $posts = json_decode($cache->cache_data, true);
        echo "Cache data for feed '{$feed->name}':\n";
        echo "  - Number of posts: " . count($posts) . "\n";

        if (!empty($posts)) {
            $first_post = $posts[0];
            echo "\n  First post details:\n";
            echo "    - thumbnail: " . substr($first_post['thumbnail'], 0, 80) . "...\n";
            echo "    - caption: " . substr($first_post['caption'], 0, 80) . "...\n";
            echo "    - likes: {$first_post['likes']}\n";
            echo "    - comments: {$first_post['comments']}\n";
            echo "    - link: {$first_post['link']}\n";
            echo "    - username: {$first_post['username']}\n";

            // Check if this is placeholder data (picsum.photos)
            $is_placeholder = strpos($first_post['thumbnail'], 'picsum.photos') !== false;
            echo "\n    - Is placeholder data: " . ($is_placeholder ? "YES" : "NO") . "\n";
        }
    } else {
        echo "No cache found for feed ID {$feed->id}\n";
    }
}

echo "\nDone.\n";
