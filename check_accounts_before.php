<?php
/**
 * Check accounts table before disconnect test
 */
require_once '/var/www/html/wp-load.php';

global $wpdb;

// Get all accounts
$accounts = $wpdb->get_results("SELECT id, instagram_user_id, username, access_token, status FROM {$wpdb->prefix}bwg_igf_accounts");

header('Content-Type: application/json');
echo json_encode([
    'account_count' => count($accounts),
    'accounts' => array_map(function($a) {
        return [
            'id' => $a->id,
            'username' => $a->username,
            'instagram_user_id' => $a->instagram_user_id,
            'token_exists' => !empty($a->access_token),
            'token_length' => strlen($a->access_token),
            'token_preview' => substr($a->access_token, 0, 30) . '...',
            'status' => $a->status
        ];
    }, $accounts)
], JSON_PRETTY_PRINT);
