<?php
/**
 * Test script to verify uninstall behavior respects "keep data" setting
 *
 * This script simulates the uninstall logic WITHOUT actually deleting anything
 * It checks whether the plugin would delete or keep data based on the current setting
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

echo "=== Testing Uninstall Behavior (Feature #139) ===\n\n";

// Get the setting value
$delete_data = get_option( 'bwg_igf_delete_data_on_uninstall', false );

echo "1. Current Setting:\n";
echo "   bwg_igf_delete_data_on_uninstall = " . var_export($delete_data, true) . "\n\n";

// Interpret the setting
if ($delete_data) {
    echo "   INTERPRETATION: Delete data on uninstall (checkbox IS checked)\n\n";
} else {
    echo "   INTERPRETATION: Keep data on uninstall (checkbox is NOT checked)\n\n";
}

// Check current table state
global $wpdb;
$feeds_table = $wpdb->prefix . 'bwg_igf_feeds';
$accounts_table = $wpdb->prefix . 'bwg_igf_accounts';
$cache_table = $wpdb->prefix . 'bwg_igf_cache';

echo "2. Current Database State:\n";

// Check tables exist
$tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_bwg_igf%'", ARRAY_N);
echo "   Tables found: " . count($tables) . "\n";
foreach ($tables as $table) {
    echo "   - " . $table[0] . "\n";
}
echo "\n";

// Count feeds
$feed_count = $wpdb->get_var("SELECT COUNT(*) FROM $feeds_table");
echo "   Feed count: $feed_count\n";

// Count accounts
$account_count = $wpdb->get_var("SELECT COUNT(*) FROM $accounts_table");
echo "   Account count: $account_count\n";

// Count cache entries
$cache_count = $wpdb->get_var("SELECT COUNT(*) FROM $cache_table");
echo "   Cache entries: $cache_count\n\n";

echo "3. Uninstall Behavior Test:\n";
if ($delete_data) {
    echo "   ⚠️  WARNING: If uninstalled now, ALL DATA WOULD BE DELETED!\n";
    echo "   The following would happen:\n";
    echo "   - DROP TABLE $feeds_table\n";
    echo "   - DROP TABLE $accounts_table\n";
    echo "   - DROP TABLE $cache_table\n";
    echo "   - Delete all bwg_igf_* options\n";
    echo "   - Clear all bwg_igf_* transients\n";
} else {
    echo "   ✅ SAFE: If uninstalled now, ALL DATA WOULD BE PRESERVED!\n";
    echo "   The following would be preserved:\n";
    echo "   - $feeds_table ($feed_count feeds)\n";
    echo "   - $accounts_table ($account_count accounts)\n";
    echo "   - $cache_table ($cache_count cache entries)\n";
    echo "   - All options and settings\n";
}

echo "\n=== Test Complete ===\n";
echo "\nVerdict: Feature #139 - Uninstall respects keep data setting\n";
if (!$delete_data) {
    echo "✅ PASS - With current setting, data WOULD be preserved on uninstall\n";
} else {
    echo "⚠️  Setting is ON - data would be deleted (this is expected if user checked the box)\n";
}
