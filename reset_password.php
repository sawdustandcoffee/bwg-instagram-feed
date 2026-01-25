<?php
/**
 * Password reset script for testing
 */

// Bootstrap WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Set new password for admin user
wp_set_password('test123', 1);

echo "Password has been reset to: test123\n";
