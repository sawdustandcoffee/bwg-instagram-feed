<?php
/**
 * Delete test feed for Feature 114 testing
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Delete the test feed
$result = $wpdb->delete(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('id' => 33),
    array('%d')
);

if ($result) {
    echo "Deleted feed ID 33\n";
} else {
    echo "Error or feed not found: " . $wpdb->last_error . "\n";
}
