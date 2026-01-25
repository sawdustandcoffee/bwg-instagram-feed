<?php
/**
 * Mock GitHub update data for testing Feature #134.
 * This simulates a newer version being available on GitHub.
 *
 * Run this from WP-CLI: wp eval-file mock_github_update.php
 */

// Mock release data - version 2.0.0 is newer than current 1.0.2
$mock_release = new stdClass();
$mock_release->version = '2.0.0';
$mock_release->name = 'BWG Instagram Feed v2.0.0';
$mock_release->body = "## What's New in 2.0.0\n\n- **New Feature**: Enhanced grid layouts\n- **Improvement**: Better caching performance\n- **Bug Fix**: Fixed OAuth token refresh\n\n## Changelog\n\n- Added support for carousel posts\n- Improved mobile responsiveness\n- Fixed memory issues with large feeds";
$mock_release->published = '2026-01-25T10:00:00Z';
$mock_release->html_url = 'https://github.com/bostonwebgroup/bwg-instagram-feed/releases/tag/v2.0.0';
$mock_release->download_url = 'https://github.com/bostonwebgroup/bwg-instagram-feed/releases/download/v2.0.0/bwg-instagram-feed.zip';

// Set the transient that the GitHub updater uses
set_transient('bwg_igf_github_update_data', $mock_release, 43200);

echo "Mock GitHub update data set successfully!\n";
echo "Mock version: 2.0.0 (current: 1.0.2)\n";
echo "This will show as an available update.\n";

// Also clear the WordPress plugin update transient to force a check
delete_site_transient('update_plugins');
echo "Cleared update_plugins transient to force refresh.\n";
