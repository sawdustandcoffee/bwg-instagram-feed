<?php
/**
 * Create test feed for Feature 114 testing
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Create a test feed
$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_feeds',
    array(
        'name' => 'FEATURE_114_DELETE_TEST',
        'slug' => 'feature-114-delete-test',
        'feed_type' => 'public',
        'instagram_usernames' => '["testuser"]',
        'layout_type' => 'grid',
        'post_count' => 12,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ),
    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
);

if ($result) {
    $feed_id = $wpdb->insert_id;
    echo "Created feed ID: " . $feed_id . "\n";
} else {
    echo "Error creating feed: " . $wpdb->last_error . "\n";
}
