<?php
/**
 * Update mock GitHub data to use URL that works from within the container
 */

// Create mock data - use 127.0.0.1:80 which is the internal WordPress server
$mock_release = new stdClass();
$mock_release->version = '2.0.0';
$mock_release->name = 'BWG Instagram Feed v2.0.0';
$mock_release->body = "## What's New in 2.0.0\n\n- **New Feature**: Enhanced grid layouts\n- **Improvement**: Better caching performance\n- **Bug Fix**: Fixed OAuth token refresh";
$mock_release->published = '2026-01-25T10:00:00Z';
$mock_release->html_url = 'https://github.com/bostonwebgroup/bwg-instagram-feed/releases/tag/v2.0.0';

// Use 127.0.0.1 which works inside the container
$mock_release->download_url = 'http://127.0.0.1/bwg-instagram-feed-2.0.0.zip';

set_transient('bwg_igf_github_update_data', $mock_release, 43200);

echo "Updated download URL to: " . $mock_release->download_url . "\n";

// Clear update_plugins transient to force refresh
delete_site_transient('update_plugins');
echo "Cleared update_plugins transient.\n";
