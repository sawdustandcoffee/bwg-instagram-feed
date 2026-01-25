<?php
/**
 * Test script for Feature #129: Token expiration warning displayed
 *
 * Creates a test account with token expiring soon and verifies warning display
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Step 1: Create or update a test account with token expiring in 5 days
$test_username = 'test_expiring_account_129';
$test_instagram_id = 129129129129;
$expires_in_days = 5; // Token expiring in 5 days (should trigger warning)

// Calculate expiration date
$expires_at = gmdate('Y-m-d H:i:s', time() + ($expires_in_days * DAY_IN_SECONDS));

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
            'access_token'   => 'test_token_for_feature_129',
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
    echo "Updated test account (ID: {$existing->id})\n";
} else {
    // Insert new
    $result = $wpdb->insert(
        $wpdb->prefix . 'bwg_igf_accounts',
        array(
            'instagram_user_id' => $test_instagram_id,
            'username'          => $test_username,
            'access_token'      => 'test_token_for_feature_129',
            'token_type'        => 'bearer',
            'expires_at'        => $expires_at,
            'account_type'      => 'basic',
            'connected_at'      => current_time('mysql'),
            'status'            => 'active',
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    echo "Created test account (ID: {$wpdb->insert_id})\n";
}

echo "Token expires at: {$expires_at}\n";
echo "Days until expiration: {$expires_in_days}\n";

// Verify account was created
$account = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts WHERE instagram_user_id = %d",
        $test_instagram_id
    )
);

if ($account) {
    echo "\nAccount details:\n";
    echo "- ID: {$account->id}\n";
    echo "- Username: {$account->username}\n";
    echo "- Status: {$account->status}\n";
    echo "- Expires At: {$account->expires_at}\n";
    echo "\nTest setup complete. Navigate to Accounts page to verify warning display.\n";
} else {
    echo "ERROR: Failed to create test account\n";
}
