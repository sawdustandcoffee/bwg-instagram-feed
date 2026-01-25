<?php
/**
 * Force password reset and verify
 */

require_once dirname(__FILE__) . '/../../../wp-load.php';

// Set new password
wp_set_password('admin', 1);

// Verify immediately
$user = get_user_by('login', 'admin');
$check = wp_check_password('admin', $user->user_pass, $user->ID);
echo "Password set to: admin\n";
echo "Verification: " . ($check ? 'SUCCESS' : 'FAILED') . "\n";
echo "Hash: " . $user->user_pass . "\n";
