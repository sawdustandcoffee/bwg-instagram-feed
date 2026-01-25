<?php
/**
 * Test AJAX handlers registration
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

echo "Testing AJAX handlers...\n";

// Check if action is registered
global $wp_filter;

$ajax_actions = array(
    'wp_ajax_bwg_igf_save_feed',
    'wp_ajax_bwg_igf_delete_feed',
    'wp_ajax_bwg_igf_refresh_cache',
);

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "$action: REGISTERED\n";
    } else {
        echo "$action: NOT REGISTERED\n";
    }
}

// Check if classes exist
echo "\nClass checks:\n";
echo "BWG_IGF_Admin_Ajax exists: " . (class_exists('BWG_IGF_Admin_Ajax') ? 'YES' : 'NO') . "\n";
echo "BWG_IGF_Instagram_API exists: " . (class_exists('BWG_IGF_Instagram_API') ? 'YES' : 'NO') . "\n";
echo "BWG_IGF_Encryption exists: " . (class_exists('BWG_IGF_Encryption') ? 'YES' : 'NO') . "\n";
