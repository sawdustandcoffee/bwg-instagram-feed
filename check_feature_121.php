<?php
// Check created_at and updated_at timestamps for Feature #121 test feed

require_once('/var/www/html/wp-config.php');

global $wpdb;

$feed = $wpdb->get_row(
    "SELECT id, name, created_at, updated_at FROM {$wpdb->prefix}bwg_igf_feeds WHERE name = 'FEATURE_121_TIMESTAMP_TEST'"
);

if ($feed) {
    echo "Feed ID: " . $feed->id . "\n";
    echo "Name: " . $feed->name . "\n";
    echo "Created At: " . $feed->created_at . "\n";
    echo "Updated At: " . $feed->updated_at . "\n";
    echo "Timestamps Match: " . ($feed->created_at === $feed->updated_at ? "YES (expected for new feed)" : "NO") . "\n";
} else {
    echo "Feed not found!\n";
}
