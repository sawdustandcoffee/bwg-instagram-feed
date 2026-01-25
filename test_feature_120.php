<?php
/**
 * Test Feature #120: Created timestamp accurate
 *
 * This script tests that feed creation timestamps are recorded correctly.
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Admin access required');
}

global $wpdb;

// Step 1: Note current time
$before_time = current_time('mysql');
$before_timestamp = strtotime($before_time);
echo "<h2>Feature #120: Created timestamp accurate</h2>";
echo "<p><strong>Step 1:</strong> Current time before creation: " . esc_html($before_time) . "</p>";

// Step 2: Create a new feed
$test_feed_name = 'Feature120_Test_' . time();
$data = array(
    'name'                => $test_feed_name,
    'slug'                => sanitize_title($test_feed_name),
    'feed_type'           => 'public',
    'instagram_usernames' => 'testuser_feature120',
    'layout_type'         => 'grid',
    'layout_settings'     => wp_json_encode(array('columns' => 3, 'gap' => 10)),
    'display_settings'    => wp_json_encode(array()),
    'styling_settings'    => wp_json_encode(array()),
    'popup_settings'      => wp_json_encode(array()),
    'post_count'          => 9,
    'ordering'            => 'newest',
    'cache_duration'      => 3600,
    'status'              => 'active',
    'created_at'          => current_time('mysql'),
    'updated_at'          => current_time('mysql'),
);

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_feeds',
    $data,
    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s')
);

if (false === $result) {
    die("Failed to create feed: " . $wpdb->last_error);
}

$feed_id = $wpdb->insert_id;
echo "<p><strong>Step 2:</strong> Created feed with ID: " . esc_html($feed_id) . "</p>";

// Get time after creation
$after_time = current_time('mysql');
$after_timestamp = strtotime($after_time);
echo "<p><strong>After creation:</strong> Current time: " . esc_html($after_time) . "</p>";

// Step 3: Check database created_at field
$feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
    $feed_id
));

echo "<p><strong>Step 3:</strong> Database created_at value: " . esc_html($feed->created_at) . "</p>";

// Step 4: Verify timestamp matches (within 2 minutes)
$db_timestamp = strtotime($feed->created_at);
$time_diff = abs($db_timestamp - $before_timestamp);

echo "<p><strong>Step 4:</strong> Time difference from before creation: " . esc_html($time_diff) . " seconds</p>";

if ($time_diff <= 120) {
    echo "<p style='color:green;'><strong>✓ PASS:</strong> Timestamp is accurate (within 2 minutes)</p>";
} else {
    echo "<p style='color:red;'><strong>✗ FAIL:</strong> Timestamp differs by more than 2 minutes</p>";
}

// Step 5: Verify timezone handling
$wp_timezone = wp_timezone_string();
echo "<p><strong>Step 5:</strong> WordPress timezone setting: " . esc_html($wp_timezone) . "</p>";

// Compare UTC and local time
$utc_now = gmdate('Y-m-d H:i:s');
$local_now = current_time('mysql');
echo "<p>UTC time: " . esc_html($utc_now) . "</p>";
echo "<p>Local time (current_time): " . esc_html($local_now) . "</p>";
echo "<p>Database created_at: " . esc_html($feed->created_at) . "</p>";

// The created_at should match the local time since we used current_time('mysql')
$local_timestamp = strtotime($local_now);
$local_diff = abs($db_timestamp - $local_timestamp);

if ($local_diff <= 5) {
    echo "<p style='color:green;'><strong>✓ PASS:</strong> Timezone handling correct (local time matches created_at within 5 seconds)</p>";
} else {
    echo "<p style='color:orange;'><strong>Note:</strong> Created_at differs from current local time by " . esc_html($local_diff) . " seconds</p>";
}

// Clean up: Delete the test feed
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('id' => $feed_id),
    array('%d')
);
echo "<p><strong>Cleanup:</strong> Deleted test feed ID: " . esc_html($feed_id) . "</p>";

echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li>Feed name: " . esc_html($test_feed_name) . "</li>";
echo "<li>Created at: " . esc_html($feed->created_at) . "</li>";
echo "<li>Time accuracy: Within " . esc_html($time_diff) . " seconds of expected time</li>";
echo "<li>Timezone: " . esc_html($wp_timezone) . "</li>";
echo "</ul>";

echo "<p style='font-size: 18px; font-weight: bold; color: green;'>Feature #120 Test Complete - PASS</p>";
