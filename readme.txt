=== BWG Instagram Feed ===
Contributors: bostonwebgroup
Tags: instagram, feed, gallery, social media, slider
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.3.48
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

= 1.3.46 =
* Background refresh logs now name each feed (with its ID) and record every feed's outcome, including feeds that return no posts, so a feed that was skipped is distinguishable from one refreshed on another run.
* Feeds left in an "error" status by a failed manual refresh are now included in background refresh, and the status is cleared on the next successful refresh. Previously a single transient failure removed a feed from background refresh permanently.

= 1.3.45 =
* Fixed background cache refresh failing on connected-account feeds. On PHP 8 the cron refresher passed the wrong value to the fetcher and crashed silently, so connected feeds were never refreshed in the background and their images eventually expired.
* One failing feed can no longer abort the whole refresh run; failures are caught and logged per feed.
* Previously silent bail-outs (missing or inactive account, token retrieval failure, no feeds due) are now logged.
* The refresh cron event is re-scheduled on load if it was dropped.
* Rate-limit backoff is now evaluated via transients, so an expired-but-uncollected backoff can no longer suppress refreshes indefinitely.

= 1.3.44 =
* Fixed feeds serving expired Instagram CDN image URLs. The cache now respects the signed URL expiry, so a cached feed never outlives its images, and the stale-cache fallback refetches once image URLs are dead instead of rendering "Image unavailable" placeholders.
* Image proxy failures are now logged to the plugin Logs page, with expired-signature 403s distinguished from rate limiting and transport errors. An expired-signature 403 invalidates the affected cache.

= 1.3.43 =
* All frontend data attributes are now prefixed with data-bwgigf- to avoid collisions with theme CSS and JavaScript.

= 1.3.42 =
* Fixed the feed's post count not applying to cached feeds. Lowering the count now takes effect immediately, and raising it is no longer capped by the old cache.
* Hardened frontend CSS against theme rules that target bare img, figure, and a tags inside the feed. Renamed the bare .active class on slider dots and the lightbox to .bwg-igf-is-active.

= 1.3.41 =
* Added an optional Instagram Webhooks receiver so you can complete Meta's "Configure webhooks" setup step. The Settings page now provides a webhook Callback URL and a Verify Token to paste into your Meta app. This is only needed to satisfy Meta's setup; displaying feeds does not require it.

= 1.3.40 =
* Fixed "Invalid redirect_uri" when connecting an Instagram account. Instagram rejects OAuth redirect URLs that contain a query string, so the callback now uses a clean REST URL (/wp-json/bwg-igf/v1/instagram-oauth) with CSRF protection. IMPORTANT: after updating, copy the new OAuth Redirect URI from the Settings page and register it in your Meta app. Requires pretty permalinks (Settings → Permalinks set to anything other than "Plain").

= 1.3.39 =
* Settings page now shows the OAuth Redirect URI (callback URL) next to the Instagram App credentials, with a copy button, so you can register the exact value on your Meta app. Helps resolve "Invalid redirect_uri" errors.

= 1.3.38 =
* Removed leftover test/mock code that could make the plugin appear to work when it was not. Feeds that cannot reach Instagram now show an honest empty state instead of fabricated placeholder posts, and the account connection flow no longer has a test-mode prompt or simulated data.
* Fixed the Connect button so it correctly starts the real Instagram Login OAuth flow.

= 1.3.37 =
* Added an option to enter your Instagram App ID and App Secret on the Settings page instead of editing wp-config.php. The App Secret is stored encrypted at rest and is never shown again in the admin. Defining the wp-config.php constants still works and takes precedence over the saved values.

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

= 1.3.41 =
Adds an optional webhook Callback URL + Verify Token (on the Settings page) to complete Meta's "Configure webhooks" step.

= 1.3.40 =
Fixes the Instagram connection ("Invalid redirect_uri"). After updating, copy the new OAuth Redirect URI from the Settings page and register it in your Meta app. Requires pretty permalinks (not "Plain").

= 1.3.39 =
Adds the OAuth Redirect URI to the Settings page so you can copy the exact callback URL to register on your Meta app.

= 1.3.38 =
Removes leftover test/mock data. Feeds that cannot reach Instagram now show an empty state instead of fake placeholder images.

= 1.3.37 =
You can now enter your Instagram App ID and Secret on the Settings page instead of editing wp-config.php.

= 1.3.36 =
Important: Instagram connections now use the Instagram API with Instagram Login. The old Instagram Basic Display API was retired by Meta and can no longer connect. Reconnect using a Business or Creator account.

== Credits ==

Developed by [Boston Web Group](https://bostonwebgroup.com)
