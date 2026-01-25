<?php
/**
 * Simple timestamp check - outputs JSON for easy verification
 */

// Minimal WordPress load
require_once dirname(__FILE__) . '/../../../wp-load.php';

header('Content-Type: application/json');

global $wpdb;

// Get the first feed's timestamps
$feed = $wpdb->get_row(
    "SELECT id, name, created_at, updated_at FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = 1"
);

$result = array(
    'current_time' => current_time('mysql'),
    'wp_timezone' => wp_timezone_string(),
    'utc_time' => gmdate('Y-m-d H:i:s'),
    'feed' => array(
        'id' => $feed->id,
        'name' => $feed->name,
        'created_at' => $feed->created_at,
        'updated_at' => $feed->updated_at,
    ),
);

echo json_encode($result, JSON_PRETTY_PRINT);
