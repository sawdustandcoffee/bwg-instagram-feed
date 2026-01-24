<?php
/**
 * Test if AJAX actions are registered
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Check if the action is registered
$action_name = 'wp_ajax_bwg_igf_delete_feed';
$is_registered = has_action($action_name);

echo "Action '$action_name' registered: " . ($is_registered ? "YES (priority: $is_registered)" : "NO") . "\n";

// Check if the class exists
echo "Class BWG_IGF_Admin_Ajax exists: " . (class_exists('BWG_IGF_Admin_Ajax') ? "YES" : "NO") . "\n";

// Check plugin constant
echo "BWG_IGF_PLUGIN_DIR defined: " . (defined('BWG_IGF_PLUGIN_DIR') ? "YES - " . BWG_IGF_PLUGIN_DIR : "NO") . "\n";
