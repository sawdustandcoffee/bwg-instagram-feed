<?php
/**
 * Test nonce verification
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Simulate a logged-in admin user
wp_set_current_user(1);

// Create a fresh nonce
$nonce = wp_create_nonce('bwg_igf_admin_nonce');
echo "Fresh nonce: $nonce\n";

// Test verification
$verify = wp_verify_nonce($nonce, 'bwg_igf_admin_nonce');
echo "Verification result: " . ($verify ? "VALID ($verify)" : "INVALID") . "\n";

// Check if user can manage options
echo "User can manage_options: " . (current_user_can('manage_options') ? 'YES' : 'NO') . "\n";

// Test with a different nonce
$test_nonce = 'e34a045f5c';
$verify2 = wp_verify_nonce($test_nonce, 'bwg_igf_admin_nonce');
echo "Test nonce ($test_nonce) verification: " . ($verify2 ? "VALID ($verify2)" : "INVALID") . "\n";
