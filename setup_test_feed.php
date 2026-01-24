<?php
/**
 * Set up a test feed with cached data for testing network error handling
 */

// Load WordPress.
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Update Feed ID 1 to have proper username and cache some test data
$feed_id = 1;

// Create sample cached posts (simulating real Instagram data)
$sample_posts = [
    [
        'id' => '1001',
        'thumbnail' => 'https://via.placeholder.com/300x300/e1306c/ffffff?text=Post+1',
        'full_image' => 'https://via.placeholder.com/800x800/e1306c/ffffff?text=Post+1',
        'caption' => 'This is a sample Instagram post for testing network error handling',
        'likes' => 1234,
        'comments' => 56,
        'link' => 'https://instagram.com/p/test1',
        'timestamp' => time() - 3600,
    ],
    [
        'id' => '1002',
        'thumbnail' => 'https://via.placeholder.com/300x300/f77737/ffffff?text=Post+2',
        'full_image' => 'https://via.placeholder.com/800x800/f77737/ffffff?text=Post+2',
        'caption' => 'Another beautiful day captured in this photo!',
        'likes' => 2345,
        'comments' => 78,
        'link' => 'https://instagram.com/p/test2',
        'timestamp' => time() - 7200,
    ],
    [
        'id' => '1003',
        'thumbnail' => 'https://via.placeholder.com/300x300/fcaf45/ffffff?text=Post+3',
        'full_image' => 'https://via.placeholder.com/800x800/fcaf45/ffffff?text=Post+3',
        'caption' => 'Nature at its finest #photography #nature',
        'likes' => 3456,
        'comments' => 90,
        'link' => 'https://instagram.com/p/test3',
        'timestamp' => time() - 10800,
    ],
    [
        'id' => '1004',
        'thumbnail' => 'https://via.placeholder.com/300x300/5b51d8/ffffff?text=Post+4',
        'full_image' => 'https://via.placeholder.com/800x800/5b51d8/ffffff?text=Post+4',
        'caption' => 'City vibes and urban exploration',
        'likes' => 4567,
        'comments' => 120,
        'link' => 'https://instagram.com/p/test4',
        'timestamp' => time() - 14400,
    ],
    [
        'id' => '1005',
        'thumbnail' => 'https://via.placeholder.com/300x300/405de6/ffffff?text=Post+5',
        'full_image' => 'https://via.placeholder.com/800x800/405de6/ffffff?text=Post+5',
        'caption' => 'Weekend adventures with friends',
        'likes' => 5678,
        'comments' => 150,
        'link' => 'https://instagram.com/p/test5',
        'timestamp' => time() - 18000,
    ],
    [
        'id' => '1006',
        'thumbnail' => 'https://via.placeholder.com/300x300/833ab4/ffffff?text=Post+6',
        'full_image' => 'https://via.placeholder.com/800x800/833ab4/ffffff?text=Post+6',
        'caption' => 'Art and creativity everywhere we go',
        'likes' => 6789,
        'comments' => 180,
        'link' => 'https://instagram.com/p/test6',
        'timestamp' => time() - 21600,
    ],
];

// Delete existing cache for this feed
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_cache',
    ['feed_id' => $feed_id],
    ['%d']
);

// Insert new cache data
$cache_key = 'feed_' . $feed_id . '_posts';
$wpdb->insert(
    $wpdb->prefix . 'bwg_igf_cache',
    [
        'feed_id' => $feed_id,
        'cache_key' => $cache_key,
        'cache_data' => json_encode($sample_posts),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hour from now
    ],
    ['%d', '%s', '%s', '%s', '%s']
);

echo "Cache populated successfully for Feed ID {$feed_id}\n";
echo "Inserted " . count($sample_posts) . " sample posts\n";

// Verify the cache was created
$cache = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
        $feed_id
    )
);

if ($cache) {
    echo "Cache verified - ID: {$cache->id}, Key: {$cache->cache_key}\n";
} else {
    echo "ERROR: Cache not found after insert\n";
}
