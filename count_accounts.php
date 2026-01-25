<?php
/**
 * Count accounts in database
 */
require_once '/var/www/html/wp-load.php';

global $wpdb;
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_accounts");
echo json_encode(['account_count' => (int)$count]);
