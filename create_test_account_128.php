<?php
/**
 * Create a test account for Feature #128 testing
 */
require_once '/var/www/html/wp-load.php';

global $wpdb;

// First, get current account count
$before_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_accounts");

// Create test account
$test_token = 'FEATURE_128_TEST_TOKEN_' . wp_generate_password(50, false, false);
$encrypted_token = BWG_IGF_Encryption::encrypt($test_token);

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_accounts',
    [
        'instagram_user_id' => 128128128128,
        'username' => 'feature_128_test_account',
        'access_token' => $encrypted_token,
        'token_type' => 'bearer',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
        'account_type' => 'basic',
        'connected_at' => current_time('mysql'),
        'status' => 'active'
    ],
    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
);

$new_id = $wpdb->insert_id;
$after_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_accounts");

header('Content-Type: application/json');
echo json_encode([
    'success' => $result !== false,
    'account_id' => $new_id,
    'username' => 'feature_128_test_account',
    'accounts_before' => $before_count,
    'accounts_after' => $after_count,
    'token_length' => strlen($encrypted_token),
    'token_encrypted' => strpos($encrypted_token, 'bwg_enc_v1:') === 0
], JSON_PRETTY_PRINT);
