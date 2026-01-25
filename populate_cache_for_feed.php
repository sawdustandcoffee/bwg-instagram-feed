<?php
/**
 * Populate cache for a specific feed
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

$feed_id = isset($argv[1]) ? intval($argv[1]) : 31;

echo "Populating cache for feed ID: $feed_id\n";

// Check if feed exists
$feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
    $feed_id
));

if (!$feed) {
    echo "ERROR: Feed not found\n";
    exit(1);
}

echo "Feed name: {$feed->name}\n";

// Delete any existing cache for this feed
$wpdb->delete($wpdb->prefix . 'bwg_igf_cache', array('feed_id' => $feed_id), array('%d'));
echo "Cleared existing cache\n";

// Insert test cache entries
$cache_data = array(
    'feed_id'    => $feed_id,
    'cache_key'  => 'feed_' . $feed_id,
    'cache_data' => json_encode(array(
        array('id' => 'test_post_1', 'caption' => 'Test post for feature 113'),
        array('id' => 'test_post_2', 'caption' => 'Another test post'),
    )),
    'created_at' => current_time('mysql'),
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
);

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_cache',
    $cache_data,
    array('%d', '%s', '%s', '%s', '%s')
);

if ($result !== false) {
    echo "✓ Cache entry created\n";
} else {
    echo "ERROR: Failed to create cache entry\n";
    echo "DB Error: " . $wpdb->last_error . "\n";
    exit(1);
}

// Verify cache exists
$count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
    $feed_id
));

echo "✓ Cache count for feed $feed_id: $count\n";
echo "Done!\n";
