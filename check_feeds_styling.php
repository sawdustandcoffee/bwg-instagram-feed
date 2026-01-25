<?php
require('/var/www/html/wp-load.php');
global $wpdb;
$feeds = $wpdb->get_results('SELECT id, name, styling_settings FROM ' . $wpdb->prefix . 'bwg_igf_feeds LIMIT 5');
foreach ($feeds as $feed) {
    echo 'Feed ID: ' . $feed->id . ', Name: ' . $feed->name . "\n";
    echo 'Styling: ' . $feed->styling_settings . "\n\n";
}
