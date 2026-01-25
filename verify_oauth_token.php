<?php
/**
 * Verify OAuth token encryption for the test account.
 */

require_once dirname( __FILE__ ) . '/../../../wp-load.php';
require_once dirname( __FILE__ ) . '/includes/class-bwg-igf-encryption.php';

global $wpdb;

// Get the test account
$account = $wpdb->get_row(
    "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts WHERE username = 'oauth_success_test_693'"
);

if ($account) {
    echo "Account found: @" . $account->username . "\n";
    echo "Status: " . $account->status . "\n";
    echo "Account Type: " . $account->account_type . "\n";
    echo "Expires: " . $account->expires_at . "\n";
    echo "\n";

    // Check token encryption
    $verification = BWG_IGF_Encryption::verify_encrypted($account->access_token);
    echo "Token Encryption Status:\n";
    echo "  Is Encrypted: " . ($verification['is_encrypted'] ? 'YES' : 'NO') . "\n";
    echo "  Encryption Method: " . $verification['encryption_method'] . "\n";
    echo "  Is Plaintext: " . ($verification['is_plaintext'] ? 'YES' : 'NO') . "\n";

    // Try to decrypt
    $decrypted = BWG_IGF_Encryption::decrypt($account->access_token);
    echo "  Can Decrypt: " . ($decrypted ? 'YES' : 'NO') . "\n";

    // Show first 40 chars of stored token (encrypted)
    echo "\n";
    echo "Stored token (first 40 chars): " . substr($account->access_token, 0, 40) . "...\n";

    echo "\n=== VERIFICATION RESULT ===\n";
    if ($verification['is_encrypted'] && $decrypted) {
        echo "PASS: Token is properly encrypted and can be decrypted\n";
    } else {
        echo "FAIL: Token encryption issue detected\n";
    }
} else {
    echo "Account not found!\n";
}
