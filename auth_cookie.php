<?php
/**
 * Generate an authentication cookie for testing
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Set password
wp_set_password('testpass123', 1);

// Generate auth cookie - this is what WordPress uses to log in
$user = get_user_by('ID', 1);
$expiration = time() + (14 * DAY_IN_SECONDS);
$cookie = wp_generate_auth_cookie($user->ID, $expiration, 'auth');
$logged_in_cookie = wp_generate_auth_cookie($user->ID, $expiration, 'logged_in');

echo "Auth cookie: wordpress_" . COOKIEHASH . "=" . $cookie . "\n";
echo "Logged in cookie: wordpress_logged_in_" . COOKIEHASH . "=" . $logged_in_cookie . "\n";
echo "Domain: " . COOKIE_DOMAIN . "\n";
echo "Path: " . COOKIEPATH . "\n";
