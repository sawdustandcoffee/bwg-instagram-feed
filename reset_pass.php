<?php
require '/var/www/html/wp-load.php';
wp_set_password('admin', 1);
echo "Password reset complete";
