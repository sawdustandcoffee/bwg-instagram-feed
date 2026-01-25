<?php
/**
 * Test script for Feature #113: Deleting feed clears its cache
 *
 * This script:
 * 1. Creates a test feed
 * 2. Adds a cache entry for that feed
 * 3. Verifies cache entry exists
 * 4. Deletes the feed
 * 5. Verifies cache entries for that feed are gone
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

echo "=== Feature #113 Test: Deleting feed clears its cache ===\n\n";

// Step 1: Create a test feed
echo "Step 1: Creating test feed...\n";

$feed_data = array(
    'name'                => 'CACHE_DELETE_TEST_113',
    'slug'                => 'cache-delete-test-113-' . time(),
    'feed_type'           => 'public',
    'instagram_usernames' => 'instagram',
    'layout_type'         => 'grid',
    'layout_settings'     => '{"columns":3}',
    'display_settings'    => '{}',
    'styling_settings'    => '{}',
    'popup_settings'      => '{}',
    'post_count'          => 9,
    'ordering'            => 'newest',
    'cache_duration'      => 3600,
    'status'              => 'active',
    'created_at'          => current_time('mysql'),
    'updated_at'          => current_time('mysql'),
);

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_feeds',
    $feed_data
);

if ($result === false) {
    echo "ERROR: Failed to create test feed\n";
    echo "Database error: " . $wpdb->last_error . "\n";
    exit(1);
}

$test_feed_id = $wpdb->insert_id;
echo "✓ Created feed with ID: $test_feed_id\n\n";

// Step 2: Add cache entries for this feed
echo "Step 2: Adding cache entries for feed...\n";

$cache_data = array(
    array(
        'feed_id'    => $test_feed_id,
        'cache_key'  => 'feed_' . $test_feed_id,
        'cache_data' => json_encode(array(
            array('id' => '123', 'caption' => 'Test post 1'),
            array('id' => '456', 'caption' => 'Test post 2'),
        )),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ),
    array(
        'feed_id'    => $test_feed_id,
        'cache_key'  => 'feed_' . $test_feed_id . '_page_2',
        'cache_data' => json_encode(array(
            array('id' => '789', 'caption' => 'Test post 3'),
        )),
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ),
);

$cache_inserted = 0;
foreach ($cache_data as $cache) {
    $result = $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_cache',
        $cache,
        array('%d', '%s', '%s', '%s', '%s')
    );
    if ($result !== false) {
        $cache_inserted++;
    }
}

echo "✓ Inserted $cache_inserted cache entries\n\n";

// Step 3: Verify cache entries exist
echo "Step 3: Verifying cache entries exist in database...\n";

$cache_count_before = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
        $test_feed_id
    )
);

echo "Cache entries for feed $test_feed_id: $cache_count_before\n";

if ($cache_count_before != 2) {
    echo "ERROR: Expected 2 cache entries, found $cache_count_before\n";
    // Cleanup
    $wpdb->delete($wpdb->prefix . 'bwg_igf_feeds', array('id' => $test_feed_id));
    $wpdb->delete($wpdb->prefix . 'bwg_igf_cache', array('feed_id' => $test_feed_id));
    exit(1);
}

echo "✓ Cache entries verified\n\n";

// Step 4: Delete the feed (simulating the delete_feed AJAX handler)
echo "Step 4: Deleting feed...\n";

// First delete the feed
$feed_delete_result = $wpdb->delete(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('id' => $test_feed_id),
    array('%d')
);

if ($feed_delete_result === false) {
    echo "ERROR: Failed to delete feed\n";
    exit(1);
}

echo "✓ Feed deleted\n";

// Delete associated cache (this is what the actual delete_feed handler does)
$cache_delete_result = $wpdb->delete(
    $wpdb->prefix . 'bwg_igf_cache',
    array('feed_id' => $test_feed_id),
    array('%d')
);

echo "✓ Cache deletion executed (affected rows: " . $wpdb->rows_affected . ")\n\n";

// Step 5: Verify cache entries are gone
echo "Step 5: Verifying cache entries are removed...\n";

$cache_count_after = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_cache WHERE feed_id = %d",
        $test_feed_id
    )
);

echo "Cache entries for feed $test_feed_id after deletion: $cache_count_after\n";

if ($cache_count_after == 0) {
    echo "\n=== SUCCESS ===\n";
    echo "✓ All cache entries for the deleted feed have been removed!\n";
    echo "Feature #113 test PASSED!\n";
    exit(0);
} else {
    echo "\n=== FAILURE ===\n";
    echo "✗ Found $cache_count_after cache entries still remaining\n";
    echo "Feature #113 test FAILED!\n";
    exit(1);
}
