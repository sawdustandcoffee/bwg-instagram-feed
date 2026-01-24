# BWG Instagram Feed

A professional WordPress plugin for displaying Instagram feeds on WordPress websites by [Boston Web Group](https://bostonwebgroup.com).

## Description

BWG Instagram Feed allows you to easily display Instagram feeds on your WordPress website. It supports both public feeds (no authentication required) and connected Instagram accounts via OAuth for enhanced features.

### Features

- **Public Feeds**: Display any public Instagram profile without requiring authentication
- **Connected Accounts**: Connect your own Instagram account for advanced features
- **Multiple Layout Options**: Grid and Slider/Carousel layouts
- **Customizable**: Configure columns, spacing, colors, fonts, and more
- **Lightbox**: Built-in popup/lightbox for viewing posts
- **Gutenberg Block**: Native block editor support
- **Shortcode**: Simple shortcode for classic editor
- **Caching**: Built-in caching to reduce API calls
- **GitHub Updates**: Automatic updates from GitHub releases

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/bwg-instagram-feed/`
3. Activate the plugin in WordPress admin
4. Navigate to **Instagram Feed** in the admin menu

## Usage

### Shortcode

Display a feed using the shortcode:

```
[bwg_igf id="1"]
```

Or use the feed slug:

```
[bwg_igf feed="my-feed-name"]
```

### Gutenberg Block

1. Edit a page with the Block Editor
2. Search for "Instagram" or "BWG" in the block inserter
3. Add the **BWG Instagram Feed** block
4. Select your feed from the dropdown

## Development Setup

1. Clone this repository
2. Run `./init.sh` to set up the development environment
3. For Docker: Access WordPress at `http://localhost:8080`
4. Activate the plugin in WordPress admin

## Configuration

### Public Feeds

1. Go to **Instagram Feed > Feeds > Add New**
2. Select "Public" feed type
3. Enter one or more Instagram usernames
4. Configure layout and styling options
5. Save and copy the shortcode

### Connected Accounts

1. Go to **Instagram Feed > Settings**
2. Enter your Instagram App credentials
3. Go to **Instagram Feed > Accounts**
4. Click "Connect Account" and authorize
5. Create a feed using your connected account

## Support

For support, please visit [Boston Web Group Support](https://bostonwebgroup.com/support).

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

Developed by [Boston Web Group](https://bostonwebgroup.com)
