<?php
/**
 * Test disconnect account functionality
 */
require_once '/var/www/html/wp-load.php';

// Login as admin
wp_set_current_user(1);

// Get nonce
$nonce = wp_create_nonce('bwg_igf_admin_nonce');

echo "Testing disconnect_account AJAX handler...\n\n";
echo "Nonce: $nonce\n";

// Simulate POST data
$_POST = [
    'nonce' => $nonce,
    'account_id' => 1  // test_encryption_user
];

// Get accounts before
global $wpdb;
$accounts_before = $wpdb->get_results("SELECT id, username FROM {$wpdb->prefix}bwg_igf_accounts");
echo "\nAccounts before disconnect: " . count($accounts_before) . "\n";
foreach ($accounts_before as $a) {
    echo "  - ID: {$a->id}, Username: {$a->username}\n";
}

// Call the handler directly
$ajax = new BWG_IGF_Admin_Ajax();

// Capture output
ob_start();
try {
    $ajax->disconnect_account();
} catch (Exception $e) {
    echo "\nException: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo "\nAJAX Output: $output\n";

// Get accounts after
$accounts_after = $wpdb->get_results("SELECT id, username FROM {$wpdb->prefix}bwg_igf_accounts");
echo "\nAccounts after disconnect: " . count($accounts_after) . "\n";
foreach ($accounts_after as $a) {
    echo "  - ID: {$a->id}, Username: {$a->username}\n";
}
