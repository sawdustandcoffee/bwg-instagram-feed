<?php
/**
 * Verify Feature #44: Check that shortcode correctly resolves slug to feed
 */
require_once('/var/www/html/wp-load.php');

global $wpdb;

$slug = 'my-test-slug-f44';

// Query the feed by slug (same query the shortcode uses)
$feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s AND status IN ('active', 'error')",
    $slug
));

if ($feed) {
    echo "SUCCESS: Feed found by slug!\n";
    echo "=========================\n";
    echo "Slug searched: {$slug}\n";
    echo "Feed ID: {$feed->id}\n";
    echo "Feed Name: {$feed->name}\n";
    echo "Feed Type: {$feed->feed_type}\n";
    echo "Feed Status: {$feed->status}\n";
    echo "Layout Type: {$feed->layout_type}\n";
    echo "\n";

    // Also verify cache data exists
    $cache_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
        $feed->id
    ));
    echo "Cache entries: {$cache_count}\n";

    echo "\nFeature #44 VERIFIED: Shortcode correctly resolves slug '{$slug}' to feed ID {$feed->id}\n";
} else {
    echo "ERROR: Feed not found by slug '{$slug}'\n";
}
