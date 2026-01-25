<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'bwg_igf_feeds';

$columns = $wpdb->get_results("DESCRIBE $table_name");
echo "Columns in $table_name:\n";
foreach ($columns as $col) {
    echo "- " . $col->Field . " (" . $col->Type . ")\n";
}
