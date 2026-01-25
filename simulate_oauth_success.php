<?php
/**
 * Simulate OAuth success by directly inserting a test account.
 *
 * This script creates a test account in the database that simulates
 * a successful OAuth callback, then redirects to the accounts page
 * with a simulated success message.
 *
 * Usage: Access via browser at:
 * http://localhost:8088/wp-content/plugins/bwg-instagram-feed/simulate_oauth_success.php
 */

// Load WordPress.
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Require admin login.
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You must be logged in as an administrator to run this test.' );
}

// Load plugin dependencies.
require_once dirname( __FILE__ ) . '/includes/class-bwg-igf-encryption.php';

global $wpdb;

// Test data for a simulated successful OAuth.
$test_user_id = 98765432101;
$test_username = 'oauth_success_test_' . wp_rand( 100, 999 );
$test_token = 'IGQVJYYWdhdGVzdF90b2tlbl8' . wp_rand( 1000000, 9999999 );
$test_account_type = 'basic';

// Remove any existing account with this Instagram user ID.
$wpdb->delete(
    $wpdb->prefix . 'bwg_igf_accounts',
    array( 'instagram_user_id' => $test_user_id ),
    array( '%d' )
);

// Encrypt the token.
$encrypted_token = BWG_IGF_Encryption::encrypt( $test_token );
$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 60 * DAY_IN_SECONDS ) );

// Insert the test account.
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
    wp_die( 'Failed to insert test account: ' . $wpdb->last_error );
}

$account_id = $wpdb->insert_id;

// Store the success message in a transient that we'll check on the accounts page.
set_transient( 'bwg_igf_oauth_success', sprintf(
    'Instagram account @%s connected successfully!',
    $test_username
), 60 );

// Redirect to accounts page with a success indicator.
wp_redirect( admin_url( 'admin.php?page=bwg-igf-accounts&oauth_simulated=1&username=' . urlencode( $test_username ) ) );
exit;
