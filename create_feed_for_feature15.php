<?php
/**
 * Create a test feed for Feature #15 delete confirmation test
 */

require_once '/var/www/html/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'bwg_igf_feeds';

// First check if a feed with this name already exists
$existing = $wpdb->get_row(
    $wpdb->prepare("SELECT id FROM {$table_name} WHERE name = %s", 'FEATURE_15_DELETE_TEST')
);

if ($existing) {
    echo "Feed 'FEATURE_15_DELETE_TEST' already exists with ID: {$existing->id}\n";
    exit(0);
}

$feed_data = array(
    'name' => 'FEATURE_15_DELETE_TEST',
    'slug' => 'feature-15-delete-test-' . time(),
    'feed_type' => 'public',
    'instagram_usernames' => 'delete_test_user',
    'layout_type' => 'grid',
    'post_count' => 9,
    'ordering' => 'newest',
    'cache_duration' => 3600,
    'status' => 'active',
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql')
);

$result = $wpdb->insert($table_name, $feed_data);

if ($result === false) {
    echo "ERROR: Failed to create test feed. Error: " . $wpdb->last_error . "\n";
    exit(1);
}

$feed_id = $wpdb->insert_id;
echo "SUCCESS: Created feed 'FEATURE_15_DELETE_TEST' with ID: {$feed_id}\n";
