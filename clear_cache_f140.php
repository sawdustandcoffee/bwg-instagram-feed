<?php
/**
 * Clear cache for Feature #140 testing
 */

require_once('/var/www/html/wp-load.php');

global $wpdb;

$table_cache = $wpdb->prefix . 'bwg_igf_cache';

// Get current cache status
$cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cache}");
echo "Current cache entries: {$cache_count}\n";

// Delete all cache entries
$deleted = $wpdb->query("DELETE FROM {$table_cache}");
echo "Deleted cache entries: {$deleted}\n";

// Re-populate cache for feed 4 (our test feed)
$feed_id = 4;

// Generate 9 test posts
$posts = array();
$base_timestamp = time();

for ($i = 1; $i <= 9; $i++) {
    $posts[] = array(
        'id'         => 'perf_test_refresh_' . $i,
        'thumbnail'  => 'https://picsum.photos/300/300?random=' . ($i + 100),
        'full_image' => 'https://picsum.photos/640/640?random=' . ($i + 100),
        'caption'    => "Performance test post {$i} (refreshed) from @testuser - Testing load time. #performance #test",
        'likes'      => rand(100, 5000),
        'comments'   => rand(10, 200),
        'link'       => "https://instagram.com/p/perftest{$i}/",
        'timestamp'  => $base_timestamp - ($i * 3600),
    );
}

// Insert fresh cache entry
$cache_data = array(
    'feed_id'    => $feed_id,
    'cache_key'  => 'feed_' . $feed_id,
    'cache_data' => json_encode($posts),
    'created_at' => current_time('mysql'),
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
);

$result = $wpdb->insert($table_cache, $cache_data);

echo "Cache repopulated for feed {$feed_id}\n";
echo "Cache entry created: " . ($result ? "YES" : "NO") . "\n";
