<?php
/**
 * Script to check pages with our shortcode
 */

// Load WordPress.
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

// Query for pages with bwg_igf shortcode.
$pages = $wpdb->get_results(
    "SELECT ID, post_title, post_content, post_status FROM {$wpdb->posts} WHERE post_type = 'page' AND post_content LIKE '%bwg_igf%' ORDER BY ID ASC"
);

echo "=== Pages with BWG Instagram Feed Shortcode ===\n\n";

if (empty($pages)) {
    echo "No pages with shortcode found.\n";
} else {
    foreach ($pages as $page) {
        echo "Page ID: {$page->ID}\n";
        echo "Title: {$page->post_title}\n";
        echo "Status: {$page->post_status}\n";
        // Extract shortcode from content
        preg_match('/\[bwg_igf[^\]]*\]/', $page->post_content, $matches);
        echo "Shortcode: " . ($matches[0] ?? 'Not found') . "\n";
        echo "URL: http://localhost:8088/?page_id={$page->ID}\n";
        echo "---\n";
    }
}
