<?php
require_once('/var/www/html/wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'bwg_igf_feeds';
$feed = $wpdb->get_row("SELECT * FROM {$table_name} WHERE id = 12");

echo "=== Feature 89 Test Feed Configuration (ID: 12) ===\n\n";
echo "Name: {$feed->name}\n";
echo "Layout Type: {$feed->layout_type}\n";
echo "Post Count: {$feed->post_count}\n";
echo "Status: {$feed->status}\n\n";

echo "Layout Settings:\n";
$layout = json_decode($feed->layout_settings, true);
print_r($layout);

echo "\nDisplay Settings:\n";
$display = json_decode($feed->display_settings, true);
print_r($display);

echo "\nStyling Settings:\n";
$styling = json_decode($feed->styling_settings, true);
print_r($styling);

// Count posts in cache
$cache_table = $wpdb->prefix . 'bwg_igf_cache';
$cache = $wpdb->get_row("SELECT cache_data FROM {$cache_table} WHERE feed_id = 12 ORDER BY id DESC LIMIT 1");
if ($cache) {
    $posts = json_decode($cache->cache_data, true);
    echo "\n\nCached posts count: " . count($posts) . "\n";
}
