<?php
require_once('/var/www/html/wp-load.php');

$user = get_user_by('login', 'admin');
if ($user) {
    wp_set_password('admin123', $user->ID);
    echo "Password reset for user ID: " . $user->ID . "\n";
} else {
    echo "User 'admin' not found\n";
}
