<?php
/**
 * Check existing feeds for Feature #140 testing
 */

require_once('/var/www/html/wp-load.php');

global $wpdb;

// Check if tables exist
$tables = $wpdb->get_results("SHOW TABLES LIKE '%bwg_igf%'");
echo "Tables found:\n";
foreach ($tables as $table) {
    $val = (array) $table;
    echo "  " . array_values($val)[0] . "\n";
}

$feeds = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bwg_igf_feeds LIMIT 10");

echo "\nExisting feeds (count: " . count($feeds) . "):\n";
foreach ($feeds as $feed) {
    echo "  ID: {$feed->id}, Name: {$feed->name}, Status: {$feed->status}, Posts: {$feed->post_count}\n";
}

// Check cache entries
$cache = $wpdb->get_results("SELECT id, feed_id, expires_at FROM {$wpdb->prefix}bwg_igf_cache LIMIT 10");
echo "\nCache entries (count: " . count($cache) . "):\n";
foreach ($cache as $c) {
    echo "  ID: {$c->id}, Feed ID: {$c->feed_id}, Expires: {$c->expires_at}\n";
}

// Check page content
$page = get_post(53);
if ($page) {
    echo "\nPage 53 content: " . $page->post_content . "\n";
}
