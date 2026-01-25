<?php
require_once '/var/www/html/wp-load.php';
wp_set_password('admin123', 1);
echo "Password reset to admin123 for user ID 1";
