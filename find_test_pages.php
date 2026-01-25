<?php
require('/var/www/html/wp-load.php');
global $wpdb;

// Get test pages with shortcodes
$pages = $wpdb->get_results("
    SELECT ID, post_title, post_content
    FROM {$wpdb->prefix}posts
    WHERE post_type = 'page'
    AND post_status = 'publish'
    AND post_content LIKE '%bwg_igf%'
");

echo "Test pages with BWG IGF shortcodes:\n";
foreach ($pages as $page) {
    echo "ID: {$page->ID}, Title: {$page->post_title}\n";
    preg_match_all('/\[bwg_igf[^\]]*\]/', $page->post_content, $matches);
    if (!empty($matches[0])) {
        echo "  Shortcodes: " . implode(', ', $matches[0]) . "\n";
    }
}

// Also show feeds with overlay hover effect
echo "\n\nFeeds with overlay hover effect:\n";
$feeds = $wpdb->get_results("SELECT id, name, styling_settings FROM {$wpdb->prefix}bwg_igf_feeds");
foreach ($feeds as $feed) {
    $styling = json_decode($feed->styling_settings, true);
    if (isset($styling['hover_effect']) && $styling['hover_effect'] === 'overlay') {
        echo "ID: {$feed->id}, Name: {$feed->name}\n";
        echo "  Styling: " . $feed->styling_settings . "\n";
    }
}
