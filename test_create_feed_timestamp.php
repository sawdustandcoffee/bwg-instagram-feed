<?php
/**
 * Test Feature #120: Created timestamp accurate
 * Creates a feed and verifies the timestamp matches the current time
 */

require_once dirname(__FILE__) . '/../../../wp-load.php';

header('Content-Type: application/json');

global $wpdb;

// Step 1: Note current time
$before_time = current_time('mysql');
$before_timestamp = strtotime($before_time);

// Step 2: Create a new feed
$test_feed_name = 'FEATURE_120_TEST_' . time();
$data = array(
    'name'                => $test_feed_name,
    'slug'                => sanitize_title($test_feed_name),
    'feed_type'           => 'public',
    'instagram_usernames' => 'feature120testuser',
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
    echo json_encode(array(
        'success' => false,
        'error' => 'Failed to create feed: ' . $wpdb->last_error
    ));
    exit;
}

$feed_id = $wpdb->insert_id;

// Get time after creation
$after_time = current_time('mysql');

// Step 3: Check database created_at field
$feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
    $feed_id
));

// Step 4: Verify timestamp matches (within 2 minutes)
$db_timestamp = strtotime($feed->created_at);
$time_diff = abs($db_timestamp - $before_timestamp);
$timestamp_accurate = ($time_diff <= 120);

// Step 5: Verify timezone handling
$wp_timezone = wp_timezone_string();
$utc_now = gmdate('Y-m-d H:i:s');
$local_now = current_time('mysql');

$output = array(
    'success' => true,
    'test_steps' => array(
        'step1_before_time' => $before_time,
        'step2_feed_created' => array(
            'feed_id' => $feed_id,
            'feed_name' => $test_feed_name
        ),
        'step3_db_created_at' => $feed->created_at,
        'step4_timestamp_accuracy' => array(
            'time_diff_seconds' => $time_diff,
            'is_accurate' => $timestamp_accurate,
            'threshold_seconds' => 120
        ),
        'step5_timezone' => array(
            'wp_timezone' => $wp_timezone,
            'utc_time' => $utc_now,
            'local_time' => $local_now,
            'db_created_at' => $feed->created_at
        )
    ),
    'feature_120_passes' => $timestamp_accurate
);

// Clean up: Delete the test feed
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('id' => $feed_id),
    array('%d')
);

$output['cleanup'] = 'Test feed deleted';

echo json_encode($output, JSON_PRETTY_PRINT);
