<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'bwg_igf_feeds';

// Create a test feed for deletion testing
$result = $wpdb->insert($table_name, array(
    'name' => 'DELETE_TEST_FEATURE90',
    'slug' => 'delete-test-feature90',
    'feed_type' => 'public',
    'instagram_usernames' => 'testuser',
    'status' => 'active',
    'layout_type' => 'grid',
    'post_count' => 12,
    'cache_duration' => 3600,
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql')
), array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'));

if ($result) {
    $feed_id = $wpdb->insert_id;
    echo "Created test feed with ID: $feed_id\n";
} else {
    echo "Failed to create feed: " . $wpdb->last_error . "\n";
}
