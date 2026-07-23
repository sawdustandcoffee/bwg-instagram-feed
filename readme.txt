=== BWG Instagram Feed ===
Contributors: bostonwebgroup
Tags: instagram, feed, gallery, social media, slider
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.3.36
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Instagram feeds on your WordPress website with customizable layouts, styling, and both public and connected account support.

== Description ==

BWG Instagram Feed allows you to easily display Instagram feeds on your WordPress website. It supports both public feeds (no authentication required) and connected Instagram accounts via OAuth for enhanced features.

**Key Features:**

* **Public Feeds** - Display any public Instagram profile without requiring authentication
* **Connected Accounts** - Connect your own Instagram account for advanced features
* **Multiple Layout Options** - Grid and Slider/Carousel layouts
* **Customizable** - Configure columns, spacing, colors, fonts, and more
* **Lightbox** - Built-in popup/lightbox for viewing posts
* **Gutenberg Block** - Native block editor support
* **Shortcode** - Simple shortcode for classic editor
* **Caching** - Built-in caching to reduce API calls
* **GitHub Updates** - Automatic updates from GitHub releases

**Requirements:**

* WordPress 5.8 or higher
* PHP 7.4 or higher
* MySQL 5.7+ or MariaDB 10.3+

== Installation ==

1. Download the plugin files
2. Upload to `/wp-content/plugins/bwg-instagram-feed/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Instagram Feed** in the admin menu to configure

== Frequently Asked Questions ==

= Do I need an Instagram account to use this plugin? =

No! You can display any public Instagram feed without authentication. However, connecting your own account provides more reliable access and additional features.

= How do I display a feed? =

Use the shortcode `[bwg_igf id="1"]` or the Gutenberg block. Replace "1" with your feed ID.

= Can I display multiple feeds on one page? =

Yes, you can use multiple shortcodes or blocks on a single page.

= How often is the feed updated? =

Feeds are cached based on your settings (default: 1 hour). You can configure this in Instagram Feed > Settings.

== Screenshots ==

1. Grid layout feed display
2. Slider/carousel layout
3. Feed configuration screen
4. Settings page
5. Lightbox view

== Changelog ==

= 1.3.36 =
* Migrated the Instagram connection to the Instagram API with Instagram Login (Meta retired the Instagram Basic Display API on 2024-12-04). Connect now uses the business-login OAuth flow and the instagram_business_basic scope. Requires a Business or Creator account.
* Updated the account connection screen and documentation link for the new flow.

= 1.3.8 - 1.3.35 =
Reliability, rate limiting, caching, and display improvements delivered across this series of releases:
* Feed reliability: native cURL requests to bypass Instagram bot detection, with an automatic API fallback when the profile page is blocked.
* Rate-limit handling: parses Instagram rate-limit headers, exponential backoff on rate-limit detection, admin dashboard status, remaining-quota display, and warnings when approaching limits.
* Caching: smart background cache refresh via WP Cron, cache warming on account connection, optional stale-data indicator, and automatic cache extension during rate-limit periods.
* Video posts: play-icon overlay on the frontend feed and admin preview, video playback in the lightbox, and capture of video media type and URL.
* Slider/carousel: seamless infinite loop, configurable transition duration, and responsive column/row settings for mobile and tablet.
* Lightbox: faster open performance and repositioned "View on Instagram" link.
* Connected accounts: store app credentials securely in code, one-click Connect button, account health/status indicators, and clearer errors for disconnected or problematic accounts.
* Admin & security: admin logging dashboard, improved input handling and security hardening, current-version display, and a link to GitHub releases.
* Updates: Plugin Update Checker integration for GitHub-based updates, multisite update-notice fix, and a Parsedown autoloader-conflict fix.

= 1.3.7 =
* Added auto-generated changelog to GitHub releases
* Improved Plugin Update Checker integration
* Fixed duplicate PUC initialization

= 1.3.6 =
* Enhanced GitHub Actions workflow error handling
* Added version validation before release creation

= 1.3.5 =
* Integrated Plugin Update Checker library v5.5
* Improved update notifications from GitHub

= 1.3.4 =
* Added last sync date/time to dashboard
* Improved feed management interface

= 1.3.0 =
* Added GitHub Updates support
* Added slider/carousel layout
* Improved responsive design
* Added popup/lightbox feature

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.3.36 =
Important: Instagram connections now use the Instagram API with Instagram Login. The old Instagram Basic Display API was retired by Meta and can no longer connect. Reconnect using a Business or Creator account.

== Credits ==

Developed by [Boston Web Group](https://bostonwebgroup.com)
