<?php
/**
 * Verify that feed #21 (FEATURE_15_DELETE_TEST) was deleted from database
 */

require_once '/var/www/html/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'bwg_igf_feeds';

// Check if the feed still exists
$feed = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", 21)
);

if ($feed === null) {
    echo "SUCCESS: Feed #21 (FEATURE_15_DELETE_TEST) has been deleted from database.\n";
    exit(0);
} else {
    echo "ERROR: Feed #21 still exists in database!\n";
    echo "Feed name: {$feed->name}\n";
    exit(1);
}
