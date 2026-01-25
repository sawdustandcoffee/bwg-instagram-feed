<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Generate proper WordPress password hash
$hash = wp_hash_password('admin123');
echo "Generated hash: $hash\n";

// Update directly in database
$result = $wpdb->update(
    $wpdb->users,
    array('user_pass' => $hash),
    array('ID' => 1)
);
echo "Updated: $result rows\n";

// Clear any caches
wp_cache_delete(1, 'users');
wp_cache_delete('admin', 'userlogins');
clean_user_cache(1);

// Verify
$user = get_user_by('ID', 1);
echo "New hash: " . $user->user_pass . "\n";
echo "Check: " . (wp_check_password('admin123', $user->user_pass, 1) ? 'PASS' : 'FAIL') . "\n";
