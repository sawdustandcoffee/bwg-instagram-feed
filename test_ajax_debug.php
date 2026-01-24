<?php
/**
 * Debug script to test if AJAX handlers are registered
 * Run via: curl http://localhost:8080/wp-content/plugins/bwg-instagram-feed/test_ajax_debug.php
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

header('Content-Type: text/plain');

echo "=== BWG AJAX Debug ===\n\n";

// Check plugin constant
echo "BWG_IGF_PLUGIN_DIR: " . (defined('BWG_IGF_PLUGIN_DIR') ? BWG_IGF_PLUGIN_DIR : 'NOT DEFINED') . "\n\n";

// Check if class exists
echo "Class BWG_IGF_Admin_Ajax exists: " . (class_exists('BWG_IGF_Admin_Ajax') ? 'YES' : 'NO') . "\n\n";

// Check registered actions
$actions_to_check = array(
    'wp_ajax_bwg_igf_delete_feed',
    'wp_ajax_bwg_igf_save_feed',
    'wp_ajax_bwg_igf_validate_username',
);

echo "=== Checking AJAX Actions ===\n";
foreach ($actions_to_check as $action) {
    $has = has_action($action);
    echo "$action: " . ($has ? "REGISTERED (priority: $has)" : "NOT REGISTERED") . "\n";
}

echo "\n=== All wp_ajax_bwg_igf Actions ===\n";
global $wp_filter;
$found = false;
foreach ($wp_filter as $tag => $callbacks) {
    if (strpos($tag, 'wp_ajax_bwg_igf') !== false) {
        echo "- $tag\n";
        $found = true;
    }
}
if (!$found) {
    echo "NO BWG AJAX actions found!\n";
}
