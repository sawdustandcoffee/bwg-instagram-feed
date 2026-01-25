<?php
/**
 * List all accounts in database
 */
require_once '/var/www/html/wp-load.php';

global $wpdb;
$accounts = $wpdb->get_results("SELECT id, username, status, connected_at FROM {$wpdb->prefix}bwg_igf_accounts ORDER BY id DESC");
echo json_encode(['accounts' => $accounts, 'count' => count($accounts)], JSON_PRETTY_PRINT);
