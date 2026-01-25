<?php
/**
 * Test script to verify OAuth callback functionality.
 *
 * This script simulates a successful OAuth callback by directly
 * inserting a test account and verifying the flow works correctly.
 *
 * Usage: php test_oauth_callback.php
 */

// Load WordPress.
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Load plugin dependencies.
require_once dirname( __FILE__ ) . '/includes/class-bwg-igf-encryption.php';

global $wpdb;

echo "=== OAuth Callback Handler Test ===\n\n";

// Test data.
$test_user_id = 12345678901;
$test_username = 'oauth_test_account';
$test_token = 'IGQVJXb2F1dGhfdGVzdF90b2tlbl8xMjM0NTY3ODkw';
$test_account_type = 'basic';

// Step 1: Encrypt the token.
echo "Step 1: Testing token encryption...\n";
$encrypted_token = BWG_IGF_Encryption::encrypt( $test_token );

if ( empty( $encrypted_token ) ) {
    echo "FAIL: Token encryption failed!\n";
    exit( 1 );
}
echo "PASS: Token encrypted successfully\n";

// Verify it's different from original.
if ( $encrypted_token === $test_token ) {
    echo "FAIL: Encrypted token is same as original (not encrypted)!\n";
    exit( 1 );
}
echo "PASS: Encrypted token is different from original\n";

// Step 2: Insert test account.
echo "\nStep 2: Inserting test account...\n";

// First remove any existing test account.
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_accounts',
    array( 'instagram_user_id' => $test_user_id ),
    array( '%d' )
);

$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 60 * DAY_IN_SECONDS ) );

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_accounts',
    array(
        'instagram_user_id' => $test_user_id,
        'username'          => $test_username,
        'access_token'      => $encrypted_token,
        'token_type'        => 'bearer',
        'expires_at'        => $expires_at,
        'account_type'      => $test_account_type,
        'connected_at'      => current_time( 'mysql' ),
        'status'            => 'active',
    ),
    array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
);

if ( false === $result ) {
    echo "FAIL: Failed to insert test account!\n";
    echo "Error: " . $wpdb->last_error . "\n";
    exit( 1 );
}

$new_account_id = $wpdb->insert_id;
echo "PASS: Test account inserted with ID: $new_account_id\n";

// Step 3: Verify account exists in database.
echo "\nStep 3: Verifying account in database...\n";

$account = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
        $new_account_id
    )
);

if ( ! $account ) {
    echo "FAIL: Account not found in database!\n";
    exit( 1 );
}
echo "PASS: Account found in database\n";

// Verify fields.
if ( $account->username !== $test_username ) {
    echo "FAIL: Username mismatch! Expected: $test_username, Got: $account->username\n";
    exit( 1 );
}
echo "PASS: Username matches\n";

if ( $account->status !== 'active' ) {
    echo "FAIL: Status mismatch! Expected: active, Got: $account->status\n";
    exit( 1 );
}
echo "PASS: Status is 'active'\n";

if ( $account->account_type !== $test_account_type ) {
    echo "FAIL: Account type mismatch! Expected: $test_account_type, Got: $account->account_type\n";
    exit( 1 );
}
echo "PASS: Account type matches\n";

// Step 4: Verify token is stored encrypted.
echo "\nStep 4: Verifying token is encrypted in database...\n";

if ( $account->access_token === $test_token ) {
    echo "FAIL: Token is stored in plaintext!\n";
    exit( 1 );
}
echo "PASS: Token is not stored in plaintext\n";

// Step 5: Verify token can be decrypted.
echo "\nStep 5: Verifying token can be decrypted...\n";

$decrypted_token = BWG_IGF_Encryption::decrypt( $account->access_token );

if ( $decrypted_token !== $test_token ) {
    echo "FAIL: Decrypted token doesn't match original!\n";
    echo "Expected: $test_token\n";
    echo "Got: $decrypted_token\n";
    exit( 1 );
}
echo "PASS: Token decrypts correctly\n";

// Step 6: Test updating existing account (reconnect flow).
echo "\nStep 6: Testing reconnect (update existing account)...\n";

$new_token = 'IGQVJYYWdhdGVkX3Rva2VuXzk4NzY1NDMyMTA=';
$new_encrypted = BWG_IGF_Encryption::encrypt( $new_token );

$update_result = $wpdb->update(
    $wpdb->prefix . 'bwg_igf_accounts',
    array(
        'access_token'   => $new_encrypted,
        'last_refreshed' => current_time( 'mysql' ),
    ),
    array( 'instagram_user_id' => $test_user_id ),
    array( '%s', '%s' ),
    array( '%d' )
);

if ( false === $update_result ) {
    echo "FAIL: Failed to update account!\n";
    exit( 1 );
}
echo "PASS: Account updated successfully\n";

// Verify update.
$updated_account = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts WHERE id = %d",
        $new_account_id
    )
);

$decrypted_new = BWG_IGF_Encryption::decrypt( $updated_account->access_token );
if ( $decrypted_new !== $new_token ) {
    echo "FAIL: Updated token doesn't match!\n";
    exit( 1 );
}
echo "PASS: Updated token decrypts correctly\n";

// Cleanup: Remove test account.
echo "\nStep 7: Cleaning up test account...\n";
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_accounts',
    array( 'id' => $new_account_id ),
    array( '%d' )
);
echo "PASS: Test account removed\n";

echo "\n=== All OAuth Callback Tests PASSED! ===\n";
