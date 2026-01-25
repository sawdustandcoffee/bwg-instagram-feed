<?php
/**
 * Cleanup script for Feature #129 test accounts
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Delete test accounts
$deleted = $wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}bwg_igf_accounts WHERE instagram_user_id IN (%d, %d)",
        129129129129, // test_expiring_account_129
        129129129130  // test_expired_account_129
    )
);

echo "Deleted {$deleted} test account(s)\n";
