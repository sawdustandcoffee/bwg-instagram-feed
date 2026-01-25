<?php
/**
 * Recreate feed for Feature #140 testing
 */

require_once('/var/www/html/wp-load.php');

global $wpdb;

$table_feeds = $wpdb->prefix . 'bwg_igf_feeds';
$table_cache = $wpdb->prefix . 'bwg_igf_cache';

// Create the feed with specific ID 4
$feed_data = array(
    'id'                  => 4,
    'name'                => 'Feature 140 Performance Test',
    'slug'                => 'feature-140-performance',
    'feed_type'           => 'public',
    'instagram_usernames' => json_encode(array('testuser')),
    'connected_account_id' => null,
    'layout_type'         => 'grid',
    'layout_settings'     => json_encode(array(
        'columns' => 3,
        'gap' => 10,
        'rows' => 3
    )),
    'display_settings'    => json_encode(array(
        'show_likes'          => true,
        'show_comments'       => true,
        'show_caption'        => true,
        'show_follow_button'  => true,
        'follow_button_text'  => 'Follow on Instagram'
    )),
    'styling_settings'    => json_encode(array(
        'hover_effect'     => 'overlay',
        'border_radius'    => 8,
        'background_color' => ''
    )),
    'popup_settings'      => json_encode(array(
        'enabled' => true
    )),
    'filter_settings'     => json_encode(array()),
    'post_count'          => 9,
    'ordering'            => 'newest',
    'cache_duration'      => 3600,
    'status'              => 'active',
    'error_message'       => null,
    'created_at'          => current_time('mysql'),
    'updated_at'          => current_time('mysql')
);

// Use REPLACE INTO to create or update with specific ID
$result = $wpdb->replace($table_feeds, $feed_data);

if (!$result) {
    echo "Error creating feed: " . $wpdb->last_error . "\n";
    exit(1);
}

echo "Created/Updated feed ID: 4\n";

// Verify feed exists
$verify = $wpdb->get_row("SELECT id, name, status FROM {$table_feeds} WHERE id = 4");
echo "Verification: ";
if ($verify) {
    echo "Feed found - ID: {$verify->id}, Name: {$verify->name}, Status: {$verify->status}\n";
} else {
    echo "FAILED - Feed not found\n";
}

// Check cache
$cache = $wpdb->get_var("SELECT COUNT(*) FROM {$table_cache} WHERE feed_id = 4");
echo "Cache entries for feed 4: {$cache}\n";
