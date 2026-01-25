<?php
/**
 * Auto login script for testing
 * Access this script directly to be logged in as admin
 */
require_once '/var/www/html/wp-load.php';

// Get redirect parameter
$redirect = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : admin_url('admin.php?page=bwg-igf-feeds&action=edit&feed_id=1');

// Check if already logged in
if (is_user_logged_in()) {
    wp_redirect($redirect);
    exit;
}

// Get admin user
$user = get_user_by('login', 'admin');
if (!$user) {
    die('Admin user not found');
}

// Set auth cookie
wp_set_auth_cookie($user->ID, true);
wp_set_current_user($user->ID);

// Redirect to target page
wp_redirect($redirect);
exit;
