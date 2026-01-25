<?php
/**
 * Test script for Feature #129: Create an expired token account
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Create test account with ALREADY EXPIRED token (yesterday)
$test_username = 'test_expired_account_129';
$test_instagram_id = 129129129130;
$expires_at = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS); // Expired yesterday

// Check if test account exists
$existing = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bwg_igf_accounts WHERE instagram_user_id = %d",
        $test_instagram_id
    )
);

if ($existing) {
    // Update existing
    $result = $wpdb->update(
        $wpdb->prefix . 'bwg_igf_accounts',
        array(
            'username'       => $test_username,
            'access_token'   => 'test_expired_token_for_feature_129',
            'token_type'     => 'bearer',
            'expires_at'     => $expires_at,
            'account_type'   => 'basic',
            'last_refreshed' => current_time('mysql'),
            'status'         => 'active',
        ),
        array('id' => $existing->id),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
        array('%d')
    );
    echo "Updated expired test account (ID: {$existing->id})\n";
} else {
    // Insert new
    $result = $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_accounts',
        array(
            'instagram_user_id' => $test_instagram_id,
            'username'          => $test_username,
            'access_token'      => 'test_expired_token_for_feature_129',
            'token_type'        => 'bearer',
            'expires_at'        => $expires_at,
            'account_type'      => 'basic',
            'connected_at'      => current_time('mysql'),
            'status'            => 'active',
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    echo "Created expired test account (ID: {$wpdb->insert_id})\n";
}

echo "Token expired at: {$expires_at}\n";
echo "This account should show 'Expired' warning.\n";
