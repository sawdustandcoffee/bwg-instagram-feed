<?php
/**
 * Script to check cache table contents
 * Run via: docker exec bwg-instagram-feed-wordpress-1 php /var/www/html/wp-content/plugins/bwg-instagram-feed/check_cache.php
 */

// Load WordPress.
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Query the cache table.
$cache_entries = $wpdb->get_results(
    "SELECT id, feed_id, cache_key, LENGTH(cache_data) as data_length, created_at, expires_at FROM {$wpdb->prefix}bwg_igf_cache ORDER BY created_at DESC LIMIT 5"
);

echo "=== BWG Instagram Feed Cache Table ===\n\n";

if (empty($cache_entries)) {
    echo "No cache entries found.\n";
} else {
    foreach ($cache_entries as $entry) {
        echo "ID: {$entry->id}\n";
        echo "Feed ID: {$entry->feed_id}\n";
        echo "Cache Key: {$entry->cache_key}\n";
        echo "Data Length: {$entry->data_length} bytes\n";
        echo "Created: {$entry->created_at}\n";
        echo "Expires: {$entry->expires_at}\n";
        echo "---\n";
    }
}

// Get detailed cache data for feed 1.
echo "\n=== Cache Data Sample for Feed 1 ===\n\n";

$cache_data = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT cache_data FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d ORDER BY created_at DESC LIMIT 1",
        1
    )
);

if ($cache_data) {
    $posts = json_decode($cache_data, true);
    echo "Total posts in cache: " . count($posts) . "\n\n";

    // Show first 3 posts.
    for ($i = 0; $i < min(3, count($posts)); $i++) {
        $post = $posts[$i];
        echo "Post " . ($i + 1) . ":\n";
        echo "  Thumbnail: " . substr($post['thumbnail'] ?? 'N/A', 0, 80) . "...\n";
        echo "  Caption: " . substr($post['caption'] ?? 'N/A', 0, 100) . "...\n";
        echo "  Likes: " . ($post['likes'] ?? 0) . "\n";
        echo "  Comments: " . ($post['comments'] ?? 0) . "\n";
        echo "  Link: " . ($post['link'] ?? 'N/A') . "\n";
        echo "  Timestamp: " . ($post['timestamp'] ?? 0) . "\n";
        echo "\n";
    }
} else {
    echo "No cache data found for feed 1.\n";
}
