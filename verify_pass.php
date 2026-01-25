<?php
require '/var/www/html/wp-load.php';
$user = get_user_by('login', 'admin');
echo "Password check result: " . wp_check_password('admin', $user->user_pass, $user->ID);
