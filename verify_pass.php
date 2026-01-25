<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';
$user = get_user_by('login', 'admin');
echo "User ID: " . $user->ID . "\n";
echo "Hash: " . $user->user_pass . "\n";
echo "Check admin123: " . (wp_check_password('admin123', $user->user_pass, $user->ID) ? 'PASS' : 'FAIL') . "\n";
