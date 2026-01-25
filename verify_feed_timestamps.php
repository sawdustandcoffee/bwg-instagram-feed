<?php
/**
 * Verify existing feed timestamps
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

header('Content-Type: text/plain');

global $wpdb;

// Get feeds with their timestamps
$feeds = $wpdb->get_results(
    "SELECT id, name, created_at, updated_at FROM {$wpdb->prefix}bwg_igf_feeds ORDER BY id ASC LIMIT 5"
);

echo "Feed Timestamps:\n";
echo "================\n\n";

foreach ($feeds as $feed) {
    echo "ID: {$feed->id}\n";
    echo "Name: {$feed->name}\n";
    echo "Created: {$feed->created_at}\n";
    echo "Updated: {$feed->updated_at}\n";
    echo "---\n";
}

echo "\nCurrent WordPress time: " . current_time('mysql') . "\n";
echo "WordPress timezone: " . wp_timezone_string() . "\n";
