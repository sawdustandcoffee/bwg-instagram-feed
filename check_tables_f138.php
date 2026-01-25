<?php
/**
 * Check BWG IGF database tables for Feature #138
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

global $wpdb;

// Check if tables exist
$tables = array(
    $wpdb->prefix . 'bwg_igf_feeds',
    $wpdb->prefix . 'bwg_igf_accounts',
    $wpdb->prefix . 'bwg_igf_cache',
);

echo "=== BWG IGF Database Tables Check ===\n\n";

foreach ($tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    $status = $exists ? 'EXISTS' : 'NOT FOUND';
    echo "Table: {$table} - {$status}\n";

    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "  - Row count: {$count}\n";
    }
}

echo "\n=== BWG IGF Options ===\n\n";

$options = array(
    'bwg_igf_db_version',
    'bwg_igf_default_cache_duration',
    'bwg_igf_delete_data_on_uninstall',
    'bwg_igf_instagram_app_id',
    'bwg_igf_instagram_app_secret',
    'bwg_igf_github_repo_url',
);

foreach ($options as $option) {
    $value = get_option($option, 'NOT SET');
    if (is_array($value)) {
        $value = json_encode($value);
    }
    echo "Option: {$option} = {$value}\n";
}

echo "\n=== Plugin Status ===\n";
$active_plugins = get_option('active_plugins', array());
$is_active = in_array('bwg-instagram-feed/bwg-instagram-feed.php', $active_plugins);
echo "BWG Instagram Feed plugin: " . ($is_active ? 'ACTIVE' : 'INACTIVE') . "\n";
