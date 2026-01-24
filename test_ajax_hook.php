<?php
/**
 * Test AJAX hook registration
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wp_filter;

$hook = 'wp_ajax_bwg_igf_refresh_cache';

echo "Testing AJAX hook: $hook\n";
echo "Hook registered: " . (isset($wp_filter[$hook]) ? 'YES' : 'NO') . "\n";

if (isset($wp_filter[$hook])) {
    echo "Callbacks:\n";
    foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $id => $callback) {
            echo "  Priority $priority: $id\n";
        }
    }
}
