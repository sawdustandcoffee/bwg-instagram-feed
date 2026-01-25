<?php
require_once('/var/www/html/wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'bwg_igf_feeds';
$feeds = $wpdb->get_results("SELECT id, name, slug, status FROM {$table_name} ORDER BY id ASC LIMIT 20");

echo "Available feeds:\n";
foreach ($feeds as $feed) {
    echo "ID: {$feed->id} | Name: {$feed->name} | Slug: {$feed->slug} | Status: {$feed->status}\n";
}
