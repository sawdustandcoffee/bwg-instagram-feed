<?php
/**
 * Update mock GitHub data to use local ZIP file
 */

// Get current mock data
$mock_release = get_transient('bwg_igf_github_update_data');

if ($mock_release) {
    // Update download URL to point to local file
    $mock_release->download_url = site_url('/bwg-instagram-feed-2.0.0.zip');

    // Update transient
    set_transient('bwg_igf_github_update_data', $mock_release, 43200);

    echo "Updated download URL to: " . $mock_release->download_url . "\n";
} else {
    // Create new mock data with local URL
    $mock_release = new stdClass();
    $mock_release->version = '2.0.0';
    $mock_release->name = 'BWG Instagram Feed v2.0.0';
    $mock_release->body = "## What's New in 2.0.0\n\n- **New Feature**: Enhanced grid layouts\n- **Improvement**: Better caching performance\n- **Bug Fix**: Fixed OAuth token refresh";
    $mock_release->published = '2026-01-25T10:00:00Z';
    $mock_release->html_url = 'https://github.com/bostonwebgroup/bwg-instagram-feed/releases/tag/v2.0.0';
    $mock_release->download_url = site_url('/bwg-instagram-feed-2.0.0.zip');

    set_transient('bwg_igf_github_update_data', $mock_release, 43200);

    echo "Created new mock data with download URL: " . $mock_release->download_url . "\n";
}

// Clear update_plugins transient to force refresh
delete_site_transient('update_plugins');
echo "Cleared update_plugins transient.\n";
