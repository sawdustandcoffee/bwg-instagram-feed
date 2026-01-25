<?php
/**
 * Check cache entries and feeds
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

echo "=== Current Feeds ===\n";
$feeds = $wpdb->get_results("SELECT id, name, slug FROM {$wpdb->prefix}bwg_igf_feeds ORDER BY id DESC LIMIT 10");
foreach ($feeds as $feed) {
    echo "  ID: {$feed->id} - {$feed->name} ({$feed->slug})\n";
}

echo "\n=== Current Cache Entries ===\n";
$cache = $wpdb->get_results("SELECT id, feed_id, cache_key, created_at FROM {$wpdb->prefix}bwg_igf_cache ORDER BY id DESC LIMIT 10");
if (empty($cache)) {
    echo "  No cache entries found\n";
} else {
    foreach ($cache as $c) {
        echo "  ID: {$c->id} - Feed ID: {$c->feed_id} - Key: {$c->cache_key} - Created: {$c->created_at}\n";
    }
}

echo "\n=== Cache Count by Feed ===\n";
$cache_counts = $wpdb->get_results("SELECT feed_id, COUNT(*) as count FROM {$wpdb->prefix}bwg_igf_cache GROUP BY feed_id");
if (empty($cache_counts)) {
    echo "  No cache entries\n";
} else {
    foreach ($cache_counts as $cc) {
        echo "  Feed ID: {$cc->feed_id} - Count: {$cc->count}\n";
    }
}
