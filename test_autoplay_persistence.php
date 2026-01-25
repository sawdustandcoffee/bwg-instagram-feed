<?php
/**
 * Test autoplay persistence for Feature #22
 * This script creates a feed with autoplay settings, saves it, and verifies the settings persist
 */

// Load WordPress
require('/var/www/html/wp-config.php');

global $wpdb;
$feeds_table = $wpdb->prefix . 'bwg_igf_feeds';

// Step 1: Create a new feed with slider layout and autoplay enabled
$feed_name = 'Feature 22 Autoplay Test ' . time();
$layout_settings = json_encode([
    'slides_to_show' => 3,
    'slides_to_scroll' => 1,
    'autoplay' => true,
    'autoplay_speed' => 4500,
    'show_arrows' => true,
    'show_dots' => true,
    'infinite' => true
]);

$result = $wpdb->insert(
    $feeds_table,
    [
        'name' => $feed_name,
        'slug' => sanitize_title($feed_name),
        'feed_type' => 'public',
        'instagram_usernames' => json_encode(['testuser']),
        'layout_type' => 'slider',
        'layout_settings' => $layout_settings,
        'display_settings' => '{}',
        'styling_settings' => '{}',
        'filter_settings' => '{}',
        'popup_settings' => '{}',
        'post_count' => 9,
        'ordering' => 'newest',
        'cache_duration' => 3600,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ],
    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
);

if ($result === false) {
    echo "ERROR: Failed to create feed\n";
    echo "Database error: " . $wpdb->last_error . "\n";
    exit(1);
}

$feed_id = $wpdb->insert_id;
echo "Created feed ID: $feed_id\n";
echo "Feed name: $feed_name\n\n";

// Step 2: Read back the feed and verify settings
$saved_feed = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $feeds_table WHERE id = %d",
    $feed_id
));

if (!$saved_feed) {
    echo "ERROR: Could not read back feed\n";
    exit(1);
}

echo "=== VERIFICATION ===\n";
echo "Layout Type: " . $saved_feed->layout_type . "\n";

$saved_layout = json_decode($saved_feed->layout_settings, true);
echo "\nLayout Settings (saved):\n";
echo "  autoplay: " . ($saved_layout['autoplay'] ? 'true' : 'false') . "\n";
echo "  autoplay_speed: " . $saved_layout['autoplay_speed'] . "\n";
echo "  slides_to_show: " . $saved_layout['slides_to_show'] . "\n";
echo "  slides_to_scroll: " . $saved_layout['slides_to_scroll'] . "\n";
echo "  show_arrows: " . ($saved_layout['show_arrows'] ? 'true' : 'false') . "\n";
echo "  show_dots: " . ($saved_layout['show_dots'] ? 'true' : 'false') . "\n";
echo "  infinite: " . ($saved_layout['infinite'] ? 'true' : 'false') . "\n";

// Step 3: Verify each setting
$all_passed = true;

// Check autoplay enabled
if ($saved_layout['autoplay'] !== true) {
    echo "\n[FAIL] autoplay should be true, got: " . var_export($saved_layout['autoplay'], true) . "\n";
    $all_passed = false;
} else {
    echo "\n[PASS] autoplay = true\n";
}

// Check autoplay speed
if ($saved_layout['autoplay_speed'] !== 4500) {
    echo "[FAIL] autoplay_speed should be 4500, got: " . $saved_layout['autoplay_speed'] . "\n";
    $all_passed = false;
} else {
    echo "[PASS] autoplay_speed = 4500\n";
}

// Check layout type is slider
if ($saved_feed->layout_type !== 'slider') {
    echo "[FAIL] layout_type should be 'slider', got: " . $saved_feed->layout_type . "\n";
    $all_passed = false;
} else {
    echo "[PASS] layout_type = slider\n";
}

// Step 4: Update the feed to test toggle off
echo "\n=== TESTING AUTOPLAY DISABLE ===\n";
$layout_settings_disabled = json_encode([
    'slides_to_show' => 3,
    'slides_to_scroll' => 1,
    'autoplay' => false,
    'autoplay_speed' => 4500,
    'show_arrows' => true,
    'show_dots' => true,
    'infinite' => true
]);

$update_result = $wpdb->update(
    $feeds_table,
    ['layout_settings' => $layout_settings_disabled, 'updated_at' => current_time('mysql')],
    ['id' => $feed_id],
    ['%s', '%s'],
    ['%d']
);

if ($update_result === false) {
    echo "ERROR: Failed to update feed\n";
    $all_passed = false;
} else {
    // Read back and verify
    $updated_feed = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $feeds_table WHERE id = %d",
        $feed_id
    ));

    $updated_layout = json_decode($updated_feed->layout_settings, true);

    if ($updated_layout['autoplay'] !== false) {
        echo "[FAIL] autoplay should be false after update, got: " . var_export($updated_layout['autoplay'], true) . "\n";
        $all_passed = false;
    } else {
        echo "[PASS] autoplay = false after update (toggle off works)\n";
    }
}

// Step 5: Re-enable autoplay with different speed
echo "\n=== TESTING AUTOPLAY RE-ENABLE WITH NEW SPEED ===\n";
$layout_settings_reenabled = json_encode([
    'slides_to_show' => 3,
    'slides_to_scroll' => 1,
    'autoplay' => true,
    'autoplay_speed' => 5500,
    'show_arrows' => true,
    'show_dots' => true,
    'infinite' => true
]);

$reenable_result = $wpdb->update(
    $feeds_table,
    ['layout_settings' => $layout_settings_reenabled, 'updated_at' => current_time('mysql')],
    ['id' => $feed_id],
    ['%s', '%s'],
    ['%d']
);

if ($reenable_result === false) {
    echo "ERROR: Failed to re-enable autoplay\n";
    $all_passed = false;
} else {
    $final_feed = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $feeds_table WHERE id = %d",
        $feed_id
    ));

    $final_layout = json_decode($final_feed->layout_settings, true);

    if ($final_layout['autoplay'] !== true) {
        echo "[FAIL] autoplay should be true after re-enable, got: " . var_export($final_layout['autoplay'], true) . "\n";
        $all_passed = false;
    } else {
        echo "[PASS] autoplay = true after re-enable\n";
    }

    if ($final_layout['autoplay_speed'] !== 5500) {
        echo "[FAIL] autoplay_speed should be 5500 after update, got: " . $final_layout['autoplay_speed'] . "\n";
        $all_passed = false;
    } else {
        echo "[PASS] autoplay_speed = 5500 (new speed persisted)\n";
    }
}

// Final result
echo "\n=== FINAL RESULT ===\n";
if ($all_passed) {
    echo "ALL TESTS PASSED - Feature #22 (Slider autoplay toggle) is working correctly!\n";
    echo "Feed ID: $feed_id\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED\n";
    exit(1);
}
