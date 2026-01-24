<?php
/**
 * Script to check feeds table contents
 */

// Load WordPress.
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Query the feeds table.
$feeds = $wpdb->get_results(
    "SELECT id, name, slug, feed_type, instagram_usernames, status, layout_type FROM {$wpdb->prefix}bwg_igf_feeds ORDER BY id ASC"
);

echo "=== BWG Instagram Feed - Feeds Table ===\n\n";

if (empty($feeds)) {
    echo "No feeds found.\n";
} else {
    foreach ($feeds as $feed) {
        echo "ID: {$feed->id}\n";
        echo "Name: {$feed->name}\n";
        echo "Slug: {$feed->slug}\n";
        echo "Type: {$feed->feed_type}\n";
        echo "Layout: {$feed->layout_type}\n";
        echo "Usernames: {$feed->instagram_usernames}\n";
        echo "Status: {$feed->status}\n";
        echo "---\n";
    }
}
