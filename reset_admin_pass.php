<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';
wp_set_password('admin123', 1);
echo "Password reset to: admin123\n";
