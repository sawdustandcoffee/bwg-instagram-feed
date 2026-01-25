<?php
/**
 * Fix null filter_settings to avoid deprecation warning
 */

require_once('/var/www/html/wp-load.php');

global $wpdb;

// Fix the deprecation warning - set empty JSON object for null filter_settings
$wpdb->query("UPDATE {$wpdb->prefix}bwg_igf_feeds SET filter_settings = '{}' WHERE filter_settings IS NULL OR filter_settings = ''");

echo "Fixed null filter_settings\n";
