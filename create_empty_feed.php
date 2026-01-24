<?php
/**
 * Create an empty feed for testing empty state.
 */

require '/var/www/html/wp-load.php';

global $wpdb;

$result = $wpdb->insert(
    $wpdb->prefix . 'bwg_igf_feeds',
    array(
        'name' => 'Empty Feed No Username',
        'slug' => 'empty-feed-no-username',
        'feed_type' => 'public',
        'instagram_usernames' => '',
        'layout_type' => 'grid',
        'layout_settings' => json_encode(array('columns' => 3)),
        'display_settings' => '{}',
        'styling_settings' => '{}',
        'popup_settings' => '{}',
        'post_count' => 9,
        'ordering' => 'newest',
        'cache_duration' => 3600,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    )
);

echo 'Insert ID: ' . $wpdb->insert_id . "\n";
