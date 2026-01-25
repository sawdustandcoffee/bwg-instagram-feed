<?php
/**
 * Check if BWG IGF uninstall cleaned up properly
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

echo "=== BWG IGF Uninstall Verification ===\n\n";
echo "Checking if tables were removed...\n\n";

$all_tables_removed = true;
foreach ($tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    $status = $exists ? 'STILL EXISTS (ERROR!)' : 'REMOVED (OK)';
    echo "Table: {$table} - {$status}\n";
    if ($exists) {
        $all_tables_removed = false;
    }
}

echo "\n=== Checking if options were removed ===\n\n";

$options = array(
    'bwg_igf_db_version',
    'bwg_igf_default_cache_duration',
    'bwg_igf_delete_data_on_uninstall',
    'bwg_igf_instagram_app_id',
    'bwg_igf_instagram_app_secret',
    'bwg_igf_github_repo_url',
);

$all_options_removed = true;
foreach ($options as $option) {
    $value = get_option($option, '__NOT_SET__');
    if ($value === '__NOT_SET__') {
        echo "Option: {$option} - REMOVED (OK)\n";
    } else {
        echo "Option: {$option} - STILL EXISTS: {$value} (ERROR!)\n";
        $all_options_removed = false;
    }
}

echo "\n=== Checking if transients were removed ===\n\n";

$transients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_bwg_igf_%' OR option_name LIKE '_transient_timeout_bwg_igf_%'");
if ($transients == 0) {
    echo "Transients: REMOVED (OK)\n";
} else {
    echo "Transients: {$transients} still exist (ERROR!)\n";
}

echo "\n=== SUMMARY ===\n";
if ($all_tables_removed && $all_options_removed && $transients == 0) {
    echo "SUCCESS: All plugin data was properly cleaned up!\n";
} else {
    echo "FAILURE: Some data was not cleaned up.\n";
}
