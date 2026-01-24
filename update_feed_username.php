<?php
/**
 * Update feed username for testing private account warning
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Update feed ID 15 to use testprivate username
$result = $wpdb->update(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('instagram_usernames' => 'testprivate'),
    array('id' => 15),
    array('%s'),
    array('%d')
);

if ($result !== false) {
    echo "Feed ID 15 updated to use 'testprivate' username.\n";
} else {
    echo "Failed to update feed: " . $wpdb->last_error . "\n";
}
