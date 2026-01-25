<?php
/**
 * Reset admin password
 */
require_once('/var/www/html/wp-load.php');

$user_id = 1;
wp_set_password('admin123', $user_id);
echo "Password reset successfully for user ID: " . $user_id;
