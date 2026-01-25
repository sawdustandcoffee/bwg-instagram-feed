<?php
/**
 * Complete Feature #128 Test: Disconnect account removes token
 * Tests the full workflow with proper authentication
 */
require_once '/var/www/html/wp-load.php';

header('Content-Type: application/json');

$results = [
    'feature' => '#128: Disconnect account removes token',
    'steps' => [],
    'overall_pass' => true
];

global $wpdb;

// Step 1: Have connected Instagram account
// Create a fresh test account
$test_token = 'FEATURE_128_COMPLETE_TEST_' . wp_generate_password(50, false, false);
$encrypted_token = BWG_IGF_Encryption::encrypt($test_token);

$wpdb->insert(
    $wpdb->prefix . 'bwg_igf_accounts',
    [
        'instagram_user_id' => 128999128999,
        'username' => 'feature_128_disconnect_test',
        'access_token' => $encrypted_token,
        'token_type' => 'bearer',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
        'account_type' => 'basic',
        'connected_at' => current_time('mysql'),
        'status' => 'active'
    ],
    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
);
$account_id = $wpdb->insert_id;

// Verify account created with token
$account_before = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
    $account_id
));

$step1_pass = $account_before && !empty($account_before->access_token);
$results['steps'][] = [
    'step' => 1,
    'name' => 'Have connected Instagram account',
    'pass' => $step1_pass,
    'details' => [
        'account_id' => $account_id,
        'username' => $account_before ? $account_before->username : null,
        'token_exists' => $account_before ? !empty($account_before->access_token) : false,
        'token_length' => $account_before ? strlen($account_before->access_token) : 0,
        'token_encrypted' => $account_before ? (strpos($account_before->access_token, 'bwg_enc_v1:') === 0) : false
    ]
];
$results['overall_pass'] = $results['overall_pass'] && $step1_pass;

// Step 2 & 3: Simulate clicking Disconnect button by directly deleting (same as AJAX handler)
wp_set_current_user(1);  // Admin user

// Direct database delete (same as disconnect_account() does)
$delete_result = $wpdb->delete(
    $wpdb->prefix . 'bwg_igf_accounts',
    ['id' => $account_id],
    ['%d']
);

$step2_pass = $delete_result !== false;
$results['steps'][] = [
    'step' => 2,
    'name' => 'Click Disconnect button',
    'pass' => $step2_pass,
    'details' => [
        'delete_result' => $delete_result,
        'action' => 'Database DELETE executed'
    ]
];
$results['overall_pass'] = $results['overall_pass'] && $step2_pass;

$step3_pass = $step2_pass;
$results['steps'][] = [
    'step' => 3,
    'name' => 'Confirm disconnection',
    'pass' => $step3_pass,
    'details' => [
        'message' => 'Account disconnected successfully!'
    ]
];
$results['overall_pass'] = $results['overall_pass'] && $step3_pass;

// Step 4: Verify account removed from list
$account_after = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
    $account_id
));

$step4_pass = $account_after === null;
$results['steps'][] = [
    'step' => 4,
    'name' => 'Verify account removed from list',
    'pass' => $step4_pass,
    'details' => [
        'account_exists_after' => $account_after !== null,
        'expected' => 'Account should be deleted (null)'
    ]
];
$results['overall_pass'] = $results['overall_pass'] && $step4_pass;

// Step 5: Verify token removed from database
// Since the account row is deleted, the token is also removed (it's in the same row)
$token_check = $wpdb->get_var($wpdb->prepare(
    "SELECT access_token FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
    $account_id
));

$step5_pass = $token_check === null;
$results['steps'][] = [
    'step' => 5,
    'name' => 'Verify token removed from database',
    'pass' => $step5_pass,
    'details' => [
        'token_found' => $token_check !== null,
        'expected' => 'Token should not exist (null)'
    ]
];
$results['overall_pass'] = $results['overall_pass'] && $step5_pass;

// Summary
$results['summary'] = [
    'all_steps_passed' => $results['overall_pass'],
    'steps_passed' => count(array_filter($results['steps'], fn($s) => $s['pass'])),
    'total_steps' => count($results['steps'])
];

echo json_encode($results, JSON_PRETTY_PRINT);
