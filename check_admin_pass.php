<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

$user = get_user_by('login', 'admin');
echo "User ID: " . $user->ID . "\n";
echo "Password hash: " . $user->user_pass . "\n";
echo "Check 'admin': " . (wp_check_password('admin', $user->user_pass, $user->ID) ? 'PASS' : 'FAIL') . "\n";
