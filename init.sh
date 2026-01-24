#!/bin/bash
#
# BWG Instagram Feed - Development Environment Setup
# This script sets up a local WordPress development environment for plugin development.
#
# Prerequisites:
# - Docker and Docker Compose installed
# - OR PHP 7.4+ with MySQL/MariaDB
# - OR Local by Flywheel / MAMP / XAMPP
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║       BWG Instagram Feed - Development Setup              ║"
echo "║              Boston Web Group                             ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Check for required tools
check_requirements() {
    echo -e "${YELLOW}Checking requirements...${NC}"

    # Check for Docker (preferred method)
    if command -v docker &> /dev/null && command -v docker-compose &> /dev/null; then
        echo -e "${GREEN}✓ Docker and Docker Compose found${NC}"
        SETUP_METHOD="docker"
        return 0
    fi

    # Check for PHP (alternative method)
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
        echo -e "${GREEN}✓ PHP $PHP_VERSION found${NC}"
        SETUP_METHOD="local"
        return 0
    fi

    echo -e "${RED}✗ No suitable development environment found${NC}"
    echo "Please install one of the following:"
    echo "  - Docker and Docker Compose (recommended)"
    echo "  - PHP 7.4+ with MySQL/MariaDB"
    echo "  - Local by Flywheel, MAMP, or XAMPP"
    exit 1
}

# Create Docker-based WordPress environment
setup_docker() {
    echo -e "${YELLOW}Setting up Docker-based WordPress environment...${NC}"

    # Create docker-compose.yml if it doesn't exist
    if [ ! -f "docker-compose.yml" ]; then
        cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  wordpress:
    image: wordpress:6.4-php8.2-apache
    container_name: bwg-igf-wordpress
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DEBUG: 1
    volumes:
      - wordpress_data:/var/www/html
      - ./:/var/www/html/wp-content/plugins/bwg-instagram-feed
    depends_on:
      - db
    restart: unless-stopped

  db:
    image: mariadb:10.11
    container_name: bwg-igf-db
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    volumes:
      - db_data:/var/lib/mysql
    restart: unless-stopped

  phpmyadmin:
    image: phpmyadmin:latest
    container_name: bwg-igf-phpmyadmin
    ports:
      - "8081:80"
    environment:
      PMA_HOST: db
      PMA_USER: root
      PMA_PASSWORD: rootpassword
    depends_on:
      - db
    restart: unless-stopped

volumes:
  wordpress_data:
  db_data:
EOF
        echo -e "${GREEN}✓ Created docker-compose.yml${NC}"
    fi

    # Start containers
    echo -e "${YELLOW}Starting Docker containers...${NC}"
    docker-compose up -d

    # Wait for WordPress to be ready
    echo -e "${YELLOW}Waiting for WordPress to initialize...${NC}"
    sleep 10

    # Check if containers are running
    if docker-compose ps | grep -q "Up"; then
        echo -e "${GREEN}✓ Docker containers started successfully${NC}"
    else
        echo -e "${RED}✗ Failed to start Docker containers${NC}"
        exit 1
    fi
}

# Setup for local PHP environment
setup_local() {
    echo -e "${YELLOW}Setting up for local PHP development...${NC}"

    echo ""
    echo -e "${YELLOW}For local development, please:${NC}"
    echo "1. Install WordPress 5.8+ in your web server directory"
    echo "2. Create a symbolic link or copy this plugin to wp-content/plugins/"
    echo "3. Activate the plugin in WordPress admin"
    echo ""
    echo "Example symlink command:"
    echo "  ln -s $(pwd) /path/to/wordpress/wp-content/plugins/bwg-instagram-feed"
    echo ""
}

# Create plugin directory structure
create_structure() {
    echo -e "${YELLOW}Creating plugin directory structure...${NC}"

    # Create directories
    directories=(
        "includes"
        "includes/admin"
        "includes/api"
        "includes/frontend"
        "includes/blocks"
        "assets"
        "assets/css"
        "assets/js"
        "assets/images"
        "languages"
        "templates"
        "templates/admin"
        "templates/frontend"
    )

    for dir in "${directories[@]}"; do
        if [ ! -d "$dir" ]; then
            mkdir -p "$dir"
            echo -e "${GREEN}  ✓ Created $dir${NC}"
        fi
    done
}

# Create main plugin file
create_plugin_file() {
    if [ ! -f "bwg-instagram-feed.php" ]; then
        echo -e "${YELLOW}Creating main plugin file...${NC}"
        cat > bwg-instagram-feed.php << 'EOF'
<?php
/**
 * Plugin Name: BWG Instagram Feed
 * Plugin URI: https://bostonwebgroup.com/plugins/instagram-feed
 * Description: Display Instagram feeds on your WordPress website with customizable layouts, styling, and both public and connected account support.
 * Version: 1.0.0
 * Author: Boston Web Group
 * Author URI: https://bostonwebgroup.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bwg-instagram-feed
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'BWG_IGF_VERSION', '1.0.0' );
define( 'BWG_IGF_PLUGIN_FILE', __FILE__ );
define( 'BWG_IGF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BWG_IGF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BWG_IGF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class BWG_Instagram_Feed {

    /**
     * Plugin instance.
     *
     * @var BWG_Instagram_Feed
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return BWG_Instagram_Feed
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook( BWG_IGF_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( BWG_IGF_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );

        // Register shortcode.
        add_shortcode( 'bwg_igf', array( $this, 'render_shortcode' ) );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $this->create_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bwg-instagram-feed',
            false,
            dirname( BWG_IGF_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Initialize components here.
    }

    /**
     * Create database tables.
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Feeds table.
        $feeds_table = $wpdb->prefix . 'bwg_igf_feeds';
        $feeds_sql = "CREATE TABLE $feeds_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            feed_type enum('public','connected') NOT NULL DEFAULT 'public',
            instagram_usernames text,
            connected_account_id bigint(20) UNSIGNED DEFAULT NULL,
            layout_type enum('grid','slider') NOT NULL DEFAULT 'grid',
            layout_settings longtext,
            display_settings longtext,
            styling_settings longtext,
            filter_settings longtext,
            popup_settings longtext,
            post_count int(11) NOT NULL DEFAULT 9,
            ordering enum('newest','oldest','random','most_liked','most_commented') NOT NULL DEFAULT 'newest',
            cache_duration int(11) NOT NULL DEFAULT 3600,
            status enum('active','inactive','error') NOT NULL DEFAULT 'active',
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY feed_type (feed_type),
            KEY status (status)
        ) $charset_collate;";

        // Accounts table.
        $accounts_table = $wpdb->prefix . 'bwg_igf_accounts';
        $accounts_sql = "CREATE TABLE $accounts_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            instagram_user_id bigint(20) UNSIGNED NOT NULL,
            username varchar(100) NOT NULL,
            access_token text NOT NULL,
            token_type varchar(50),
            expires_at datetime,
            account_type enum('basic','business','creator') NOT NULL DEFAULT 'basic',
            profile_picture_url text,
            connected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_refreshed datetime,
            status enum('active','expired','revoked') NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY instagram_user_id (instagram_user_id),
            KEY status (status)
        ) $charset_collate;";

        // Cache table.
        $cache_table = $wpdb->prefix . 'bwg_igf_cache';
        $cache_sql = "CREATE TABLE $cache_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_id bigint(20) UNSIGNED NOT NULL,
            cache_key varchar(255) NOT NULL,
            cache_data longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY feed_id (feed_id),
            KEY cache_key (cache_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $feeds_sql );
        dbDelta( $accounts_sql );
        dbDelta( $cache_sql );

        update_option( 'bwg_igf_db_version', '1.0.0' );
    }

    /**
     * Set default options.
     */
    private function set_default_options() {
        $defaults = array(
            'default_cache_duration' => 3600,
            'delete_data_on_uninstall' => false,
            'instagram_app_id' => '',
            'instagram_app_secret' => '',
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( 'bwg_igf_' . $key ) ) {
                add_option( 'bwg_igf_' . $key, $value );
            }
        }
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'BWG Instagram Feed', 'bwg-instagram-feed' ),
            __( 'Instagram Feed', 'bwg-instagram-feed' ),
            'manage_options',
            'bwg-igf',
            array( $this, 'render_dashboard_page' ),
            'dashicons-instagram',
            30
        );

        add_submenu_page(
            'bwg-igf',
            __( 'Dashboard', 'bwg-instagram-feed' ),
            __( 'Dashboard', 'bwg-instagram-feed' ),
            'manage_options',
            'bwg-igf',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'bwg-igf',
            __( 'All Feeds', 'bwg-instagram-feed' ),
            __( 'Feeds', 'bwg-instagram-feed' ),
            'manage_options',
            'bwg-igf-feeds',
            array( $this, 'render_feeds_page' )
        );

        add_submenu_page(
            'bwg-igf',
            __( 'Connected Accounts', 'bwg-instagram-feed' ),
            __( 'Accounts', 'bwg-instagram-feed' ),
            'manage_options',
            'bwg-igf-accounts',
            array( $this, 'render_accounts_page' )
        );

        add_submenu_page(
            'bwg-igf',
            __( 'Settings', 'bwg-instagram-feed' ),
            __( 'Settings', 'bwg-instagram-feed' ),
            'manage_options',
            'bwg-igf-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'bwg-igf' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'bwg-igf-admin',
            BWG_IGF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BWG_IGF_VERSION
        );

        wp_enqueue_script(
            'bwg-igf-admin',
            BWG_IGF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            BWG_IGF_VERSION,
            true
        );

        wp_localize_script(
            'bwg-igf-admin',
            'bwgIgfAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'bwg_igf_admin_nonce' ),
                'i18n'    => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this feed?', 'bwg-instagram-feed' ),
                    'saving'        => __( 'Saving...', 'bwg-instagram-feed' ),
                    'saved'         => __( 'Saved!', 'bwg-instagram-feed' ),
                    'error'         => __( 'An error occurred. Please try again.', 'bwg-instagram-feed' ),
                ),
            )
        );
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function frontend_enqueue_scripts() {
        wp_enqueue_style(
            'bwg-igf-frontend',
            BWG_IGF_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BWG_IGF_VERSION
        );

        wp_enqueue_script(
            'bwg-igf-frontend',
            BWG_IGF_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            BWG_IGF_VERSION,
            true
        );

        wp_localize_script(
            'bwg-igf-frontend',
            'bwgIgfFrontend',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'bwg_igf_frontend_nonce' ),
            )
        );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard_page() {
        include BWG_IGF_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render feeds page.
     */
    public function render_feeds_page() {
        include BWG_IGF_PLUGIN_DIR . 'templates/admin/feeds.php';
    }

    /**
     * Render accounts page.
     */
    public function render_accounts_page() {
        include BWG_IGF_PLUGIN_DIR . 'templates/admin/accounts.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        include BWG_IGF_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'   => 0,
                'feed' => '',
            ),
            $atts,
            'bwg_igf'
        );

        ob_start();
        include BWG_IGF_PLUGIN_DIR . 'templates/frontend/feed.php';
        return ob_get_clean();
    }
}

// Initialize plugin.
BWG_Instagram_Feed::get_instance();
EOF
        echo -e "${GREEN}✓ Created bwg-instagram-feed.php${NC}"
    fi
}

# Create placeholder files
create_placeholder_files() {
    echo -e "${YELLOW}Creating placeholder files...${NC}"

    # Admin CSS
    if [ ! -f "assets/css/admin.css" ]; then
        cat > assets/css/admin.css << 'EOF'
/**
 * BWG Instagram Feed - Admin Styles
 *
 * @package BWG_Instagram_Feed
 */

/* Plugin header branding */
.bwg-igf-header {
    display: flex;
    align-items: center;
    padding: 20px 0;
    margin-bottom: 20px;
    border-bottom: 1px solid #c3c4c7;
}

.bwg-igf-header .logo {
    max-width: 200px;
    height: auto;
}

.bwg-igf-header h1 {
    margin: 0 0 0 15px;
    font-size: 23px;
    font-weight: 400;
}

/* Dashboard widgets */
.bwg-igf-dashboard-widgets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.bwg-igf-widget {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.bwg-igf-widget h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #c3c4c7;
}

/* Feed editor layout */
.bwg-igf-editor {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 20px;
}

.bwg-igf-editor-panel {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.bwg-igf-editor-tabs {
    display: flex;
    border-bottom: 1px solid #c3c4c7;
}

.bwg-igf-editor-tab {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 3px solid transparent;
}

.bwg-igf-editor-tab.active {
    border-bottom-color: #2271b1;
    font-weight: 600;
}

.bwg-igf-editor-content {
    padding: 20px;
}

/* Preview panel */
.bwg-igf-preview {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    position: sticky;
    top: 32px;
}

.bwg-igf-preview h3 {
    margin-top: 0;
}

/* Form elements */
.bwg-igf-field {
    margin-bottom: 15px;
}

.bwg-igf-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.bwg-igf-field input[type="text"],
.bwg-igf-field input[type="number"],
.bwg-igf-field select,
.bwg-igf-field textarea {
    width: 100%;
}

/* Status indicators */
.bwg-igf-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.bwg-igf-status-active {
    background: #d4edda;
    color: #155724;
}

.bwg-igf-status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.bwg-igf-status-error {
    background: #fff3cd;
    color: #856404;
}

/* Responsive */
@media (max-width: 782px) {
    .bwg-igf-editor {
        grid-template-columns: 1fr;
    }

    .bwg-igf-preview {
        position: static;
    }
}
EOF
        echo -e "${GREEN}  ✓ Created assets/css/admin.css${NC}"
    fi

    # Frontend CSS
    if [ ! -f "assets/css/frontend.css" ]; then
        cat > assets/css/frontend.css << 'EOF'
/**
 * BWG Instagram Feed - Frontend Styles
 *
 * @package BWG_Instagram_Feed
 */

/* Feed container */
.bwg-igf-feed {
    width: 100%;
    max-width: 100%;
}

/* Grid layout */
.bwg-igf-grid {
    display: grid;
    gap: var(--bwg-igf-gap, 10px);
}

.bwg-igf-grid-1 { grid-template-columns: repeat(1, 1fr); }
.bwg-igf-grid-2 { grid-template-columns: repeat(2, 1fr); }
.bwg-igf-grid-3 { grid-template-columns: repeat(3, 1fr); }
.bwg-igf-grid-4 { grid-template-columns: repeat(4, 1fr); }
.bwg-igf-grid-5 { grid-template-columns: repeat(5, 1fr); }
.bwg-igf-grid-6 { grid-template-columns: repeat(6, 1fr); }

/* Feed item */
.bwg-igf-item {
    position: relative;
    overflow: hidden;
    border-radius: var(--bwg-igf-border-radius, 0);
}

.bwg-igf-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

/* Hover effects */
.bwg-igf-hover-zoom .bwg-igf-item:hover img {
    transform: scale(1.1);
}

.bwg-igf-hover-brightness .bwg-igf-item:hover img {
    filter: brightness(1.2);
}

/* Overlay */
.bwg-igf-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--bwg-igf-overlay-color, rgba(0, 0, 0, 0.5));
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.bwg-igf-item:hover .bwg-igf-overlay {
    opacity: 1;
}

.bwg-igf-overlay-content {
    color: #fff;
    text-align: center;
}

/* Engagement stats */
.bwg-igf-stats {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.bwg-igf-stat {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Slider layout */
.bwg-igf-slider {
    position: relative;
    overflow: hidden;
}

.bwg-igf-slider-track {
    display: flex;
    transition: transform 0.3s ease;
}

.bwg-igf-slider-slide {
    flex-shrink: 0;
}

/* Slider navigation */
.bwg-igf-slider-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    transition: background 0.3s ease;
}

.bwg-igf-slider-nav:hover {
    background: #fff;
}

.bwg-igf-slider-prev {
    left: 10px;
}

.bwg-igf-slider-next {
    right: 10px;
}

/* Slider pagination */
.bwg-igf-slider-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 15px 0;
}

.bwg-igf-slider-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ccc;
    border: none;
    cursor: pointer;
    transition: background 0.3s ease;
}

.bwg-igf-slider-dot.active {
    background: #333;
}

/* Popup/Lightbox */
.bwg-igf-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.bwg-igf-popup.active {
    opacity: 1;
    visibility: visible;
}

.bwg-igf-popup-content {
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    gap: 20px;
}

.bwg-igf-popup-image {
    max-width: 70vw;
    max-height: 90vh;
    object-fit: contain;
}

.bwg-igf-popup-details {
    color: #fff;
    width: 300px;
}

.bwg-igf-popup-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 40px;
    height: 40px;
    background: none;
    border: none;
    color: #fff;
    font-size: 30px;
    cursor: pointer;
}

.bwg-igf-popup-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.bwg-igf-popup-nav:hover {
    background: rgba(255, 255, 255, 0.2);
}

.bwg-igf-popup-prev {
    left: 20px;
}

.bwg-igf-popup-next {
    right: 20px;
}

/* Follow button */
.bwg-igf-follow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.bwg-igf-follow:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    color: #fff;
}

/* Loading state */
.bwg-igf-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.bwg-igf-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #e1306c;
    border-radius: 50%;
    animation: bwg-igf-spin 1s linear infinite;
}

@keyframes bwg-igf-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Error state */
.bwg-igf-error {
    padding: 20px;
    text-align: center;
    color: #721c24;
    background: #f8d7da;
    border-radius: 5px;
}

/* Empty state */
.bwg-igf-empty {
    padding: 40px;
    text-align: center;
    color: #666;
}

/* Responsive */
@media (max-width: 768px) {
    .bwg-igf-grid-4,
    .bwg-igf-grid-5,
    .bwg-igf-grid-6 {
        grid-template-columns: repeat(2, 1fr);
    }

    .bwg-igf-popup-content {
        flex-direction: column;
    }

    .bwg-igf-popup-details {
        width: auto;
    }
}

@media (max-width: 480px) {
    .bwg-igf-grid-3,
    .bwg-igf-grid-4,
    .bwg-igf-grid-5,
    .bwg-igf-grid-6 {
        grid-template-columns: repeat(1, 1fr);
    }
}
EOF
        echo -e "${GREEN}  ✓ Created assets/css/frontend.css${NC}"
    fi

    # Admin JS
    if [ ! -f "assets/js/admin.js" ]; then
        cat > assets/js/admin.js << 'EOF'
/**
 * BWG Instagram Feed - Admin JavaScript
 *
 * @package BWG_Instagram_Feed
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        BWGIGFAdmin.init();
    });

    var BWGIGFAdmin = {
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initColorPickers();
            this.initLivePreview();
        },

        bindEvents: function() {
            // Delete feed confirmation
            $(document).on('click', '.bwg-igf-delete-feed', this.handleDeleteFeed);

            // Save feed
            $(document).on('submit', '#bwg-igf-feed-form', this.handleSaveFeed);

            // Copy shortcode
            $(document).on('click', '.bwg-igf-copy-shortcode', this.handleCopyShortcode);

            // Refresh cache
            $(document).on('click', '.bwg-igf-refresh-cache', this.handleRefreshCache);

            // Clear all cache
            $(document).on('click', '.bwg-igf-clear-cache', this.handleClearCache);

            // Validate username
            $(document).on('blur', '#bwg-igf-username', this.handleValidateUsername);
        },

        initTabs: function() {
            $('.bwg-igf-editor-tab').on('click', function() {
                var target = $(this).data('tab');

                $('.bwg-igf-editor-tab').removeClass('active');
                $(this).addClass('active');

                $('.bwg-igf-tab-content').removeClass('active');
                $('#bwg-igf-tab-' + target).addClass('active');
            });
        },

        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.bwg-igf-color-picker').wpColorPicker({
                    change: function() {
                        BWGIGFAdmin.updatePreview();
                    }
                });
            }
        },

        initLivePreview: function() {
            // Update preview on setting changes
            $('.bwg-igf-editor-content input, .bwg-igf-editor-content select').on('change input', function() {
                BWGIGFAdmin.updatePreview();
            });
        },

        updatePreview: function() {
            // Get current settings and update preview
            var settings = this.getFormSettings();

            // Apply settings to preview
            var $preview = $('.bwg-igf-preview-content');

            // Update columns
            $preview.attr('class', 'bwg-igf-preview-content bwg-igf-grid bwg-igf-grid-' + settings.columns);

            // Update gap
            $preview.css('--bwg-igf-gap', settings.gap + 'px');

            // Update border radius
            $preview.find('.bwg-igf-item').css('border-radius', settings.borderRadius + 'px');
        },

        getFormSettings: function() {
            return {
                columns: $('#bwg-igf-columns').val() || 3,
                gap: $('#bwg-igf-gap').val() || 10,
                borderRadius: $('#bwg-igf-border-radius').val() || 0,
                hoverEffect: $('#bwg-igf-hover-effect').val() || 'none'
            };
        },

        handleDeleteFeed: function(e) {
            e.preventDefault();

            if (!confirm(bwgIgfAdmin.i18n.confirmDelete)) {
                return;
            }

            var $button = $(this);
            var feedId = $button.data('feed-id');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_delete_feed',
                    nonce: bwgIgfAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleSaveFeed: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();

            $button.prop('disabled', true).text(bwgIgfAdmin.i18n.saving);

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: $form.serialize() + '&action=bwg_igf_save_feed&nonce=' + bwgIgfAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        $button.text(bwgIgfAdmin.i18n.saved);
                        setTimeout(function() {
                            $button.text(originalText).prop('disabled', false);
                        }, 2000);

                        // Show success notice
                        BWGIGFAdmin.showNotice('success', response.data.message);
                    } else {
                        $button.text(originalText).prop('disabled', false);
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    $button.text(originalText).prop('disabled', false);
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleCopyShortcode: function(e) {
            e.preventDefault();

            var shortcode = $(this).data('shortcode');

            navigator.clipboard.writeText(shortcode).then(function() {
                BWGIGFAdmin.showNotice('success', 'Shortcode copied to clipboard!');
            });
        },

        handleRefreshCache: function(e) {
            e.preventDefault();

            var $button = $(this);
            var feedId = $button.data('feed-id');

            $button.prop('disabled', true);

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_refresh_cache',
                    nonce: bwgIgfAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', 'Cache refreshed successfully!');
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleClearCache: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all cached data?')) {
                return;
            }

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_clear_all_cache',
                    nonce: bwgIgfAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', 'All cache cleared successfully!');
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                }
            });
        },

        handleValidateUsername: function() {
            var username = $(this).val().trim();

            if (!username) {
                return;
            }

            var $indicator = $(this).siblings('.bwg-igf-validation-indicator');
            $indicator.html('<span class="spinner is-active"></span>');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_validate_username',
                    nonce: bwgIgfAdmin.nonce,
                    username: username
                },
                success: function(response) {
                    if (response.success) {
                        $indicator.html('<span class="dashicons dashicons-yes" style="color: green;"></span>');
                    } else {
                        $indicator.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + response.data.message);
                    }
                }
            });
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.wrap h1').first().after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Export for global access if needed
    window.BWGIGFAdmin = BWGIGFAdmin;

})(jQuery);
EOF
        echo -e "${GREEN}  ✓ Created assets/js/admin.js${NC}"
    fi

    # Frontend JS
    if [ ! -f "assets/js/frontend.js" ]; then
        cat > assets/js/frontend.js << 'EOF'
/**
 * BWG Instagram Feed - Frontend JavaScript
 *
 * @package BWG_Instagram_Feed
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        BWGIGFFrontend.init();
    });

    var BWGIGFFrontend = {
        init: function() {
            this.initSliders();
            this.initPopups();
            this.initLazyLoading();
        },

        initSliders: function() {
            var sliders = document.querySelectorAll('.bwg-igf-slider');

            sliders.forEach(function(slider) {
                new BWGIGFSlider(slider);
            });
        },

        initPopups: function() {
            var feeds = document.querySelectorAll('.bwg-igf-feed[data-popup="true"]');

            feeds.forEach(function(feed) {
                new BWGIGFPopup(feed);
            });
        },

        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                var images = document.querySelectorAll('.bwg-igf-item img[data-src]');

                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    });
                });

                images.forEach(function(img) {
                    observer.observe(img);
                });
            }
        }
    };

    // Slider class
    function BWGIGFSlider(element) {
        this.element = element;
        this.track = element.querySelector('.bwg-igf-slider-track');
        this.slides = element.querySelectorAll('.bwg-igf-slider-slide');
        this.prevBtn = element.querySelector('.bwg-igf-slider-prev');
        this.nextBtn = element.querySelector('.bwg-igf-slider-next');
        this.dots = element.querySelectorAll('.bwg-igf-slider-dot');

        this.currentIndex = 0;
        this.slidesToShow = parseInt(element.dataset.slidesToShow) || 1;
        this.autoplay = element.dataset.autoplay === 'true';
        this.autoplaySpeed = parseInt(element.dataset.autoplaySpeed) || 3000;
        this.infinite = element.dataset.infinite === 'true';

        this.init();
    }

    BWGIGFSlider.prototype = {
        init: function() {
            this.bindEvents();
            this.updateSlideWidth();

            if (this.autoplay) {
                this.startAutoplay();
            }

            // Handle touch events
            this.initTouch();
        },

        bindEvents: function() {
            var self = this;

            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', function() {
                    self.prev();
                });
            }

            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', function() {
                    self.next();
                });
            }

            this.dots.forEach(function(dot, index) {
                dot.addEventListener('click', function() {
                    self.goTo(index);
                });
            });

            // Pause autoplay on hover
            if (this.autoplay) {
                this.element.addEventListener('mouseenter', function() {
                    self.stopAutoplay();
                });

                this.element.addEventListener('mouseleave', function() {
                    self.startAutoplay();
                });
            }
        },

        updateSlideWidth: function() {
            var width = 100 / this.slidesToShow;

            this.slides.forEach(function(slide) {
                slide.style.width = width + '%';
            });
        },

        goTo: function(index) {
            var maxIndex = this.slides.length - this.slidesToShow;

            if (this.infinite) {
                if (index < 0) index = maxIndex;
                if (index > maxIndex) index = 0;
            } else {
                if (index < 0) index = 0;
                if (index > maxIndex) index = maxIndex;
            }

            this.currentIndex = index;
            var offset = -(index * (100 / this.slidesToShow));
            this.track.style.transform = 'translateX(' + offset + '%)';

            this.updateDots();
        },

        next: function() {
            this.goTo(this.currentIndex + 1);
        },

        prev: function() {
            this.goTo(this.currentIndex - 1);
        },

        updateDots: function() {
            var self = this;

            this.dots.forEach(function(dot, index) {
                dot.classList.toggle('active', index === self.currentIndex);
            });
        },

        startAutoplay: function() {
            var self = this;

            this.autoplayInterval = setInterval(function() {
                self.next();
            }, this.autoplaySpeed);
        },

        stopAutoplay: function() {
            clearInterval(this.autoplayInterval);
        },

        initTouch: function() {
            var self = this;
            var startX, moveX;

            this.element.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
            });

            this.element.addEventListener('touchmove', function(e) {
                moveX = e.touches[0].clientX;
            });

            this.element.addEventListener('touchend', function() {
                if (startX && moveX) {
                    var diff = startX - moveX;

                    if (Math.abs(diff) > 50) {
                        if (diff > 0) {
                            self.next();
                        } else {
                            self.prev();
                        }
                    }
                }

                startX = null;
                moveX = null;
            });
        }
    };

    // Popup class
    function BWGIGFPopup(feed) {
        this.feed = feed;
        this.items = feed.querySelectorAll('.bwg-igf-item');
        this.currentIndex = 0;

        this.init();
    }

    BWGIGFPopup.prototype = {
        init: function() {
            this.createPopup();
            this.bindEvents();
        },

        createPopup: function() {
            this.popup = document.createElement('div');
            this.popup.className = 'bwg-igf-popup';
            this.popup.innerHTML = [
                '<button class="bwg-igf-popup-close" aria-label="Close">&times;</button>',
                '<button class="bwg-igf-popup-nav bwg-igf-popup-prev" aria-label="Previous">&lsaquo;</button>',
                '<button class="bwg-igf-popup-nav bwg-igf-popup-next" aria-label="Next">&rsaquo;</button>',
                '<div class="bwg-igf-popup-content">',
                '  <img class="bwg-igf-popup-image" src="" alt="">',
                '  <div class="bwg-igf-popup-details">',
                '    <p class="bwg-igf-popup-caption"></p>',
                '    <div class="bwg-igf-popup-stats"></div>',
                '    <a class="bwg-igf-popup-link" href="" target="_blank" rel="noopener">View on Instagram</a>',
                '  </div>',
                '</div>'
            ].join('');

            document.body.appendChild(this.popup);
        },

        bindEvents: function() {
            var self = this;

            // Open popup on item click
            this.items.forEach(function(item, index) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.open(index);
                });

                // Keyboard support
                item.setAttribute('tabindex', '0');
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        self.open(index);
                    }
                });
            });

            // Close button
            this.popup.querySelector('.bwg-igf-popup-close').addEventListener('click', function() {
                self.close();
            });

            // Click outside to close
            this.popup.addEventListener('click', function(e) {
                if (e.target === self.popup) {
                    self.close();
                }
            });

            // Navigation
            this.popup.querySelector('.bwg-igf-popup-prev').addEventListener('click', function() {
                self.prev();
            });

            this.popup.querySelector('.bwg-igf-popup-next').addEventListener('click', function() {
                self.next();
            });

            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (!self.popup.classList.contains('active')) return;

                switch (e.key) {
                    case 'Escape':
                        self.close();
                        break;
                    case 'ArrowLeft':
                        self.prev();
                        break;
                    case 'ArrowRight':
                        self.next();
                        break;
                }
            });
        },

        open: function(index) {
            this.currentIndex = index;
            this.updateContent();
            this.popup.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Focus management
            this.lastFocusedElement = document.activeElement;
            this.popup.querySelector('.bwg-igf-popup-close').focus();
        },

        close: function() {
            this.popup.classList.remove('active');
            document.body.style.overflow = '';

            // Restore focus
            if (this.lastFocusedElement) {
                this.lastFocusedElement.focus();
            }
        },

        updateContent: function() {
            var item = this.items[this.currentIndex];
            var img = item.querySelector('img');

            this.popup.querySelector('.bwg-igf-popup-image').src = item.dataset.fullImage || img.src;
            this.popup.querySelector('.bwg-igf-popup-image').alt = img.alt || '';
            this.popup.querySelector('.bwg-igf-popup-caption').textContent = item.dataset.caption || '';
            this.popup.querySelector('.bwg-igf-popup-stats').innerHTML = [
                item.dataset.likes ? '<span>❤️ ' + item.dataset.likes + '</span>' : '',
                item.dataset.comments ? '<span>💬 ' + item.dataset.comments + '</span>' : ''
            ].join(' ');
            this.popup.querySelector('.bwg-igf-popup-link').href = item.dataset.link || '#';
        },

        next: function() {
            this.currentIndex = (this.currentIndex + 1) % this.items.length;
            this.updateContent();
        },

        prev: function() {
            this.currentIndex = (this.currentIndex - 1 + this.items.length) % this.items.length;
            this.updateContent();
        }
    };

    // Export for global access
    window.BWGIGFFrontend = BWGIGFFrontend;

})();
EOF
        echo -e "${GREEN}  ✓ Created assets/js/frontend.js${NC}"
    fi

    # Dashboard template
    if [ ! -f "templates/admin/dashboard.php" ]; then
        cat > templates/admin/dashboard.php << 'EOF'
<?php
/**
 * Admin Dashboard Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <h1><?php esc_html_e( 'BWG Instagram Feed', 'bwg-instagram-feed' ); ?></h1>
    </div>

    <div class="bwg-igf-dashboard-widgets">
        <!-- Quick Stats -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Quick Stats', 'bwg-instagram-feed' ); ?></h2>
            <?php
            global $wpdb;
            $feeds_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_feeds" );
            $accounts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bwg_igf_accounts WHERE status = 'active'" );
            ?>
            <p><strong><?php esc_html_e( 'Total Feeds:', 'bwg-instagram-feed' ); ?></strong> <?php echo esc_html( $feeds_count ); ?></p>
            <p><strong><?php esc_html_e( 'Connected Accounts:', 'bwg-instagram-feed' ); ?></strong> <?php echo esc_html( $accounts_count ); ?></p>
        </div>

        <!-- Getting Started -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Getting Started', 'bwg-instagram-feed' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Create a new feed by clicking "Add New Feed"', 'bwg-instagram-feed' ); ?></li>
                <li><?php esc_html_e( 'Enter an Instagram username for public feeds, or connect your account', 'bwg-instagram-feed' ); ?></li>
                <li><?php esc_html_e( 'Customize the layout and styling options', 'bwg-instagram-feed' ); ?></li>
                <li><?php esc_html_e( 'Copy the shortcode and add it to any page or post', 'bwg-instagram-feed' ); ?></li>
            </ol>
        </div>

        <!-- Quick Links -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Quick Links', 'bwg-instagram-feed' ); ?></h2>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=new' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Create New Feed', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds' ) ); ?>" class="button">
                    <?php esc_html_e( 'View All Feeds', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-settings' ) ); ?>" class="button">
                    <?php esc_html_e( 'Settings', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
        </div>

        <!-- Support -->
        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'Need Help?', 'bwg-instagram-feed' ); ?></h2>
            <p><?php esc_html_e( 'Check out our documentation or contact support:', 'bwg-instagram-feed' ); ?></p>
            <p>
                <a href="https://bostonwebgroup.com/support" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Visit Support Center', 'bwg-instagram-feed' ); ?>
                </a>
            </p>
        </div>
    </div>
</div>
EOF
        echo -e "${GREEN}  ✓ Created templates/admin/dashboard.php${NC}"
    fi

    # Feeds template
    if [ ! -f "templates/admin/feeds.php" ]; then
        cat > templates/admin/feeds.php << 'EOF'
<?php
/**
 * Admin Feeds List Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;

if ( 'edit' === $action || 'new' === $action ) {
    include BWG_IGF_PLUGIN_DIR . 'templates/admin/feed-editor.php';
    return;
}
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <h1>
            <?php esc_html_e( 'Instagram Feeds', 'bwg-instagram-feed' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=new' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New Feed', 'bwg-instagram-feed' ); ?>
            </a>
        </h1>
    </div>

    <?php
    global $wpdb;
    $feeds = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds ORDER BY created_at DESC" );
    ?>

    <?php if ( empty( $feeds ) ) : ?>
        <div class="bwg-igf-empty-state">
            <h2><?php esc_html_e( 'No feeds yet!', 'bwg-instagram-feed' ); ?></h2>
            <p><?php esc_html_e( 'Create your first Instagram feed to display on your website.', 'bwg-instagram-feed' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=new' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Create Your First Feed', 'bwg-instagram-feed' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'bwg-instagram-feed' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'bwg-instagram-feed' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'bwg-instagram-feed' ); ?></th>
                    <th><?php esc_html_e( 'Shortcode', 'bwg-instagram-feed' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'bwg-instagram-feed' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $feeds as $feed ) : ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=edit&feed_id=' . $feed->id ) ); ?>">
                                    <?php echo esc_html( $feed->name ); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php echo esc_html( ucfirst( $feed->feed_type ) ); ?>
                        </td>
                        <td>
                            <span class="bwg-igf-status bwg-igf-status-<?php echo esc_attr( $feed->status ); ?>">
                                <?php echo esc_html( ucfirst( $feed->status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <code>[bwg_igf id="<?php echo esc_attr( $feed->id ); ?>"]</code>
                            <button type="button" class="button-link bwg-igf-copy-shortcode" data-shortcode='[bwg_igf id="<?php echo esc_attr( $feed->id ); ?>"]'>
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=edit&feed_id=' . $feed->id ) ); ?>">
                                <?php esc_html_e( 'Edit', 'bwg-instagram-feed' ); ?>
                            </a> |
                            <a href="#" class="bwg-igf-duplicate-feed" data-feed-id="<?php echo esc_attr( $feed->id ); ?>">
                                <?php esc_html_e( 'Duplicate', 'bwg-instagram-feed' ); ?>
                            </a> |
                            <a href="#" class="bwg-igf-delete-feed" data-feed-id="<?php echo esc_attr( $feed->id ); ?>" style="color: #a00;">
                                <?php esc_html_e( 'Delete', 'bwg-instagram-feed' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
EOF
        echo -e "${GREEN}  ✓ Created templates/admin/feeds.php${NC}"
    fi

    # Feed editor template
    if [ ! -f "templates/admin/feed-editor.php" ]; then
        cat > templates/admin/feed-editor.php << 'EOF'
<?php
/**
 * Feed Editor Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$feed = null;
if ( $feed_id > 0 ) {
    global $wpdb;
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
        $feed_id
    ) );
}

$is_new = empty( $feed );
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <h1>
            <?php echo $is_new ? esc_html__( 'Create New Feed', 'bwg-instagram-feed' ) : esc_html__( 'Edit Feed', 'bwg-instagram-feed' ); ?>
        </h1>
    </div>

    <form id="bwg-igf-feed-form" method="post">
        <input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>">

        <div class="bwg-igf-editor">
            <!-- Configuration Panel -->
            <div class="bwg-igf-editor-panel">
                <div class="bwg-igf-editor-tabs">
                    <button type="button" class="bwg-igf-editor-tab active" data-tab="source">
                        <?php esc_html_e( 'Source', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="layout">
                        <?php esc_html_e( 'Layout', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="display">
                        <?php esc_html_e( 'Display', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="styling">
                        <?php esc_html_e( 'Styling', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="popup">
                        <?php esc_html_e( 'Popup', 'bwg-instagram-feed' ); ?>
                    </button>
                    <button type="button" class="bwg-igf-editor-tab" data-tab="advanced">
                        <?php esc_html_e( 'Advanced', 'bwg-instagram-feed' ); ?>
                    </button>
                </div>

                <div class="bwg-igf-editor-content">
                    <!-- Source Tab -->
                    <div id="bwg-igf-tab-source" class="bwg-igf-tab-content active">
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-name"><?php esc_html_e( 'Feed Name', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-name" name="name" value="<?php echo $feed ? esc_attr( $feed->name ) : ''; ?>" required>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-feed-type"><?php esc_html_e( 'Feed Type', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-feed-type" name="feed_type">
                                <option value="public" <?php selected( $feed ? $feed->feed_type : '', 'public' ); ?>><?php esc_html_e( 'Public (Username)', 'bwg-instagram-feed' ); ?></option>
                                <option value="connected" <?php selected( $feed ? $feed->feed_type : '', 'connected' ); ?>><?php esc_html_e( 'Connected Account', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field" id="bwg-igf-username-field">
                            <label for="bwg-igf-username"><?php esc_html_e( 'Instagram Username(s)', 'bwg-instagram-feed' ); ?></label>
                            <input type="text" id="bwg-igf-username" name="instagram_usernames" value="<?php echo $feed ? esc_attr( $feed->instagram_usernames ) : ''; ?>" placeholder="<?php esc_attr_e( 'username or username1, username2', 'bwg-instagram-feed' ); ?>">
                            <span class="bwg-igf-validation-indicator"></span>
                            <p class="description"><?php esc_html_e( 'Enter one or more Instagram usernames (comma-separated for multiple).', 'bwg-instagram-feed' ); ?></p>
                        </div>
                    </div>

                    <!-- Layout Tab -->
                    <div id="bwg-igf-tab-layout" class="bwg-igf-tab-content">
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-layout-type"><?php esc_html_e( 'Layout Type', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-layout-type" name="layout_type">
                                <option value="grid"><?php esc_html_e( 'Grid', 'bwg-instagram-feed' ); ?></option>
                                <option value="slider"><?php esc_html_e( 'Slider', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-columns"><?php esc_html_e( 'Columns', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-columns" name="columns" value="3" min="1" max="6">
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-gap"><?php esc_html_e( 'Gap (px)', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-gap" name="gap" value="10" min="0" max="50">
                        </div>
                    </div>

                    <!-- Display Tab -->
                    <div id="bwg-igf-tab-display" class="bwg-igf-tab-content">
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-post-count"><?php esc_html_e( 'Number of Posts', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-post-count" name="post_count" value="<?php echo $feed ? esc_attr( $feed->post_count ) : '9'; ?>" min="1" max="50">
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" name="show_likes" value="1" checked>
                                <?php esc_html_e( 'Show like count', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" name="show_comments" value="1" checked>
                                <?php esc_html_e( 'Show comment count', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" name="show_caption" value="1">
                                <?php esc_html_e( 'Show caption', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>

                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" name="show_follow_button" value="1" checked>
                                <?php esc_html_e( 'Show "Follow on Instagram" button', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>
                    </div>

                    <!-- Styling Tab -->
                    <div id="bwg-igf-tab-styling" class="bwg-igf-tab-content">
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-border-radius"><?php esc_html_e( 'Border Radius (px)', 'bwg-instagram-feed' ); ?></label>
                            <input type="number" id="bwg-igf-border-radius" name="border_radius" value="0" min="0" max="50">
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-hover-effect"><?php esc_html_e( 'Hover Effect', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-hover-effect" name="hover_effect">
                                <option value="none"><?php esc_html_e( 'None', 'bwg-instagram-feed' ); ?></option>
                                <option value="zoom"><?php esc_html_e( 'Zoom', 'bwg-instagram-feed' ); ?></option>
                                <option value="overlay"><?php esc_html_e( 'Overlay', 'bwg-instagram-feed' ); ?></option>
                                <option value="brightness"><?php esc_html_e( 'Brightness', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-custom-css"><?php esc_html_e( 'Custom CSS', 'bwg-instagram-feed' ); ?></label>
                            <textarea id="bwg-igf-custom-css" name="custom_css" rows="5"></textarea>
                        </div>
                    </div>

                    <!-- Popup Tab -->
                    <div id="bwg-igf-tab-popup" class="bwg-igf-tab-content">
                        <div class="bwg-igf-field">
                            <label>
                                <input type="checkbox" name="popup_enabled" value="1" checked>
                                <?php esc_html_e( 'Enable popup/lightbox', 'bwg-instagram-feed' ); ?>
                            </label>
                        </div>
                    </div>

                    <!-- Advanced Tab -->
                    <div id="bwg-igf-tab-advanced" class="bwg-igf-tab-content">
                        <div class="bwg-igf-field">
                            <label for="bwg-igf-ordering"><?php esc_html_e( 'Post Ordering', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-ordering" name="ordering">
                                <option value="newest"><?php esc_html_e( 'Newest First', 'bwg-instagram-feed' ); ?></option>
                                <option value="oldest"><?php esc_html_e( 'Oldest First', 'bwg-instagram-feed' ); ?></option>
                                <option value="random"><?php esc_html_e( 'Random', 'bwg-instagram-feed' ); ?></option>
                                <option value="most_liked"><?php esc_html_e( 'Most Liked', 'bwg-instagram-feed' ); ?></option>
                                <option value="most_commented"><?php esc_html_e( 'Most Commented', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field">
                            <label for="bwg-igf-cache-duration"><?php esc_html_e( 'Cache Duration', 'bwg-instagram-feed' ); ?></label>
                            <select id="bwg-igf-cache-duration" name="cache_duration">
                                <option value="900"><?php esc_html_e( '15 Minutes', 'bwg-instagram-feed' ); ?></option>
                                <option value="1800"><?php esc_html_e( '30 Minutes', 'bwg-instagram-feed' ); ?></option>
                                <option value="3600" selected><?php esc_html_e( '1 Hour', 'bwg-instagram-feed' ); ?></option>
                                <option value="21600"><?php esc_html_e( '6 Hours', 'bwg-instagram-feed' ); ?></option>
                                <option value="43200"><?php esc_html_e( '12 Hours', 'bwg-instagram-feed' ); ?></option>
                                <option value="86400"><?php esc_html_e( '24 Hours', 'bwg-instagram-feed' ); ?></option>
                            </select>
                        </div>

                        <div class="bwg-igf-field">
                            <button type="button" class="button bwg-igf-refresh-cache" data-feed-id="<?php echo esc_attr( $feed_id ); ?>">
                                <?php esc_html_e( 'Refresh Cache Now', 'bwg-instagram-feed' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bwg-igf-editor-footer">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds' ) ); ?>" class="button">
                        <?php esc_html_e( 'Cancel', 'bwg-instagram-feed' ); ?>
                    </a>
                    <button type="submit" class="button button-primary">
                        <?php echo $is_new ? esc_html__( 'Create Feed', 'bwg-instagram-feed' ) : esc_html__( 'Save Changes', 'bwg-instagram-feed' ); ?>
                    </button>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="bwg-igf-preview">
                <h3><?php esc_html_e( 'Preview', 'bwg-instagram-feed' ); ?></h3>
                <div class="bwg-igf-preview-content bwg-igf-grid bwg-igf-grid-3">
                    <?php for ( $i = 0; $i < 9; $i++ ) : ?>
                        <div class="bwg-igf-item">
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3Crect fill='%23e1306c' width='1' height='1'/%3E%3C/svg%3E" alt="Preview placeholder">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </form>
</div>
EOF
        echo -e "${GREEN}  ✓ Created templates/admin/feed-editor.php${NC}"
    fi

    # Accounts template
    if [ ! -f "templates/admin/accounts.php" ]; then
        cat > templates/admin/accounts.php << 'EOF'
<?php
/**
 * Admin Accounts Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <h1><?php esc_html_e( 'Connected Instagram Accounts', 'bwg-instagram-feed' ); ?></h1>
    </div>

    <div class="bwg-igf-widget">
        <h2><?php esc_html_e( 'Connect an Account', 'bwg-instagram-feed' ); ?></h2>
        <p><?php esc_html_e( 'Connect your Instagram account to access more features like hashtag filtering and reliable data access.', 'bwg-instagram-feed' ); ?></p>

        <?php
        $app_id = get_option( 'bwg_igf_instagram_app_id' );
        if ( empty( $app_id ) ) :
        ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e( 'Please configure your Instagram App credentials in Settings before connecting an account.', 'bwg-instagram-feed' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-settings' ) ); ?>">
                        <?php esc_html_e( 'Go to Settings', 'bwg-instagram-feed' ); ?>
                    </a>
                </p>
            </div>
        <?php else : ?>
            <a href="#" class="button button-primary bwg-igf-connect-account">
                <?php esc_html_e( 'Connect Instagram Account', 'bwg-instagram-feed' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="bwg-igf-widget" style="margin-top: 20px;">
        <h2><?php esc_html_e( 'Connected Accounts', 'bwg-instagram-feed' ); ?></h2>

        <?php
        global $wpdb;
        $accounts = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bwg_igf_accounts ORDER BY connected_at DESC" );
        ?>

        <?php if ( empty( $accounts ) ) : ?>
            <p><?php esc_html_e( 'No accounts connected yet.', 'bwg-instagram-feed' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Username', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Expires', 'bwg-instagram-feed' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'bwg-instagram-feed' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $accounts as $account ) : ?>
                        <tr>
                            <td>
                                <strong>@<?php echo esc_html( $account->username ); ?></strong>
                            </td>
                            <td><?php echo esc_html( ucfirst( $account->account_type ) ); ?></td>
                            <td>
                                <span class="bwg-igf-status bwg-igf-status-<?php echo esc_attr( $account->status ); ?>">
                                    <?php echo esc_html( ucfirst( $account->status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ( $account->expires_at ) {
                                    $expires = strtotime( $account->expires_at );
                                    $days_left = ceil( ( $expires - time() ) / DAY_IN_SECONDS );
                                    if ( $days_left < 7 ) {
                                        echo '<span style="color: #dc3232;">' . sprintf( esc_html__( '%d days', 'bwg-instagram-feed' ), $days_left ) . '</span>';
                                    } else {
                                        echo esc_html( date_i18n( get_option( 'date_format' ), $expires ) );
                                    }
                                } else {
                                    esc_html_e( 'N/A', 'bwg-instagram-feed' );
                                }
                                ?>
                            </td>
                            <td>
                                <a href="#" class="bwg-igf-disconnect-account" data-account-id="<?php echo esc_attr( $account->id ); ?>" style="color: #a00;">
                                    <?php esc_html_e( 'Disconnect', 'bwg-instagram-feed' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
EOF
        echo -e "${GREEN}  ✓ Created templates/admin/accounts.php${NC}"
    fi

    # Settings template
    if [ ! -f "templates/admin/settings.php" ]; then
        cat > templates/admin/settings.php << 'EOF'
<?php
/**
 * Admin Settings Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Save settings
if ( isset( $_POST['bwg_igf_save_settings'] ) && check_admin_referer( 'bwg_igf_settings_nonce' ) ) {
    update_option( 'bwg_igf_default_cache_duration', absint( $_POST['default_cache_duration'] ) );
    update_option( 'bwg_igf_delete_data_on_uninstall', isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0 );
    update_option( 'bwg_igf_instagram_app_id', sanitize_text_field( $_POST['instagram_app_id'] ) );
    update_option( 'bwg_igf_instagram_app_secret', sanitize_text_field( $_POST['instagram_app_secret'] ) );

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bwg-instagram-feed' ) . '</p></div>';
}

$default_cache = get_option( 'bwg_igf_default_cache_duration', 3600 );
$delete_data = get_option( 'bwg_igf_delete_data_on_uninstall', 0 );
$app_id = get_option( 'bwg_igf_instagram_app_id', '' );
$app_secret = get_option( 'bwg_igf_instagram_app_secret', '' );
?>
<div class="wrap">
    <div class="bwg-igf-header">
        <h1><?php esc_html_e( 'Settings', 'bwg-instagram-feed' ); ?></h1>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'bwg_igf_settings_nonce' ); ?>

        <div class="bwg-igf-widget">
            <h2><?php esc_html_e( 'General Settings', 'bwg-instagram-feed' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_cache_duration"><?php esc_html_e( 'Default Cache Duration', 'bwg-instagram-feed' ); ?></label>
                    </th>
                    <td>
                        <select name="default_cache_duration" id="default_cache_duration">
                            <option value="900" <?php selected( $default_cache, 900 ); ?>><?php esc_html_e( '15 Minutes', 'bwg-instagram-feed' ); ?></option>
                            <option value="1800" <?php selected( $default_cache, 1800 ); ?>><?php esc_html_e( '30 Minutes', 'bwg-instagram-feed' ); ?></option>
                            <option value="3600" <?php selected( $default_cache, 3600 ); ?>><?php esc_html_e( '1 Hour', 'bwg-instagram-feed' ); ?></option>
                            <option value="21600" <?php selected( $default_cache, 21600 ); ?>><?php esc_html_e( '6 Hours', 'bwg-instagram-feed' ); ?></option>
                            <option value="43200" <?php selected( $default_cache, 43200 ); ?>><?php esc_html_e( '12 Hours', 'bwg-instagram-feed' ); ?></option>
                            <option value="86400" <?php selected( $default_cache, 86400 ); ?>><?php esc_html_e( '24 Hours', 'bwg-instagram-feed' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'How long to cache Instagram data before fetching fresh content.', 'bwg-instagram-feed' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="delete_data_on_uninstall"><?php esc_html_e( 'Delete Data on Uninstall', 'bwg-instagram-feed' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="delete_data_on_uninstall" id="delete_data_on_uninstall" value="1" <?php checked( $delete_data, 1 ); ?>>
                            <?php esc_html_e( 'Delete all plugin data when uninstalling', 'bwg-instagram-feed' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Warning: This will permanently delete all feeds, settings, and connected accounts.', 'bwg-instagram-feed' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bwg-igf-widget" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Instagram API Settings', 'bwg-instagram-feed' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'To use connected accounts, you need to create an Instagram App. Visit the Meta Developer Portal to create one.', 'bwg-instagram-feed' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="instagram_app_id"><?php esc_html_e( 'Instagram App ID', 'bwg-instagram-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="instagram_app_id" id="instagram_app_id" value="<?php echo esc_attr( $app_id ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="instagram_app_secret"><?php esc_html_e( 'Instagram App Secret', 'bwg-instagram-feed' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="instagram_app_secret" id="instagram_app_secret" value="<?php echo esc_attr( $app_secret ); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <div class="bwg-igf-widget" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Cache Management', 'bwg-instagram-feed' ); ?></h2>
            <p>
                <button type="button" class="button bwg-igf-clear-cache">
                    <?php esc_html_e( 'Clear All Cache', 'bwg-instagram-feed' ); ?>
                </button>
            </p>
            <p class="description"><?php esc_html_e( 'Clear all cached Instagram data. Fresh data will be fetched on the next page load.', 'bwg-instagram-feed' ); ?></p>
        </div>

        <p class="submit">
            <input type="submit" name="bwg_igf_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'bwg-instagram-feed' ); ?>">
        </p>
    </form>
</div>
EOF
        echo -e "${GREEN}  ✓ Created templates/admin/settings.php${NC}"
    fi

    # Frontend feed template
    if [ ! -f "templates/frontend/feed.php" ]; then
        cat > templates/frontend/feed.php << 'EOF'
<?php
/**
 * Frontend Feed Template
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get feed by ID or slug
$feed_id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : 0;
$feed_slug = ! empty( $atts['feed'] ) ? sanitize_title( $atts['feed'] ) : '';

global $wpdb;

if ( $feed_id > 0 ) {
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d AND status = 'active'",
        $feed_id
    ) );
} elseif ( ! empty( $feed_slug ) ) {
    $feed = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE slug = %s AND status = 'active'",
        $feed_slug
    ) );
} else {
    $feed = null;
}

if ( ! $feed ) {
    echo '<div class="bwg-igf-error">' . esc_html__( 'Feed not found or inactive.', 'bwg-instagram-feed' ) . '</div>';
    return;
}

// Parse settings
$layout_settings = json_decode( $feed->layout_settings, true ) ?: array();
$display_settings = json_decode( $feed->display_settings, true ) ?: array();
$styling_settings = json_decode( $feed->styling_settings, true ) ?: array();
$popup_settings = json_decode( $feed->popup_settings, true ) ?: array();

// Defaults
$columns = isset( $layout_settings['columns'] ) ? absint( $layout_settings['columns'] ) : 3;
$gap = isset( $layout_settings['gap'] ) ? absint( $layout_settings['gap'] ) : 10;
$hover_effect = isset( $styling_settings['hover_effect'] ) ? $styling_settings['hover_effect'] : 'none';
$border_radius = isset( $styling_settings['border_radius'] ) ? absint( $styling_settings['border_radius'] ) : 0;
$popup_enabled = isset( $popup_settings['enabled'] ) ? $popup_settings['enabled'] : true;

// Get cached posts (placeholder for now)
$posts = array(); // TODO: Implement cache retrieval

// Custom styles
$custom_styles = array(
    '--bwg-igf-gap: ' . $gap . 'px',
    '--bwg-igf-border-radius: ' . $border_radius . 'px',
);

if ( isset( $styling_settings['overlay_color'] ) ) {
    $custom_styles[] = '--bwg-igf-overlay-color: ' . $styling_settings['overlay_color'];
}

$feed_classes = array(
    'bwg-igf-feed',
    'bwg-igf-' . $feed->layout_type,
);

if ( 'grid' === $feed->layout_type ) {
    $feed_classes[] = 'bwg-igf-grid-' . $columns;
}

if ( 'none' !== $hover_effect ) {
    $feed_classes[] = 'bwg-igf-hover-' . $hover_effect;
}
?>
<div
    class="<?php echo esc_attr( implode( ' ', $feed_classes ) ); ?>"
    data-feed-id="<?php echo esc_attr( $feed->id ); ?>"
    data-popup="<?php echo $popup_enabled ? 'true' : 'false'; ?>"
    style="<?php echo esc_attr( implode( '; ', $custom_styles ) ); ?>"
>
    <?php if ( empty( $posts ) ) : ?>
        <div class="bwg-igf-loading">
            <div class="bwg-igf-spinner"></div>
        </div>
    <?php else : ?>
        <?php if ( 'slider' === $feed->layout_type ) : ?>
            <div class="bwg-igf-slider-track">
        <?php endif; ?>

        <?php foreach ( $posts as $post ) : ?>
            <div
                class="bwg-igf-item<?php echo 'slider' === $feed->layout_type ? ' bwg-igf-slider-slide' : ''; ?>"
                data-full-image="<?php echo esc_url( $post['full_image'] ?? '' ); ?>"
                data-caption="<?php echo esc_attr( $post['caption'] ?? '' ); ?>"
                data-likes="<?php echo esc_attr( $post['likes'] ?? 0 ); ?>"
                data-comments="<?php echo esc_attr( $post['comments'] ?? 0 ); ?>"
                data-link="<?php echo esc_url( $post['link'] ?? '' ); ?>"
            >
                <img
                    src="<?php echo esc_url( $post['thumbnail'] ?? '' ); ?>"
                    alt="<?php echo esc_attr( $post['caption'] ?? __( 'Instagram post', 'bwg-instagram-feed' ) ); ?>"
                    loading="lazy"
                >

                <?php if ( 'overlay' === $hover_effect ) : ?>
                    <div class="bwg-igf-overlay">
                        <div class="bwg-igf-overlay-content">
                            <div class="bwg-igf-stats">
                                <?php if ( ! empty( $display_settings['show_likes'] ) ) : ?>
                                    <span class="bwg-igf-stat">❤️ <?php echo esc_html( $post['likes'] ?? 0 ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! empty( $display_settings['show_comments'] ) ) : ?>
                                    <span class="bwg-igf-stat">💬 <?php echo esc_html( $post['comments'] ?? 0 ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ( 'slider' === $feed->layout_type ) : ?>
            </div>

            <?php if ( ! empty( $layout_settings['show_arrows'] ) ) : ?>
                <button class="bwg-igf-slider-nav bwg-igf-slider-prev" aria-label="<?php esc_attr_e( 'Previous', 'bwg-instagram-feed' ); ?>">‹</button>
                <button class="bwg-igf-slider-nav bwg-igf-slider-next" aria-label="<?php esc_attr_e( 'Next', 'bwg-instagram-feed' ); ?>">›</button>
            <?php endif; ?>

            <?php if ( ! empty( $layout_settings['show_dots'] ) ) : ?>
                <div class="bwg-igf-slider-dots">
                    <?php for ( $i = 0; $i < count( $posts ); $i++ ) : ?>
                        <button class="bwg-igf-slider-dot<?php echo 0 === $i ? ' active' : ''; ?>"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( ! empty( $display_settings['show_follow_button'] ) && ! empty( $feed->instagram_usernames ) ) :
        $usernames = json_decode( $feed->instagram_usernames, true ) ?: array( $feed->instagram_usernames );
        $first_username = is_array( $usernames ) ? $usernames[0] : $usernames;
    ?>
        <div class="bwg-igf-follow-wrapper">
            <a href="https://instagram.com/<?php echo esc_attr( $first_username ); ?>" class="bwg-igf-follow" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( $display_settings['follow_button_text'] ?? __( 'Follow on Instagram', 'bwg-instagram-feed' ) ); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
EOF
        echo -e "${GREEN}  ✓ Created templates/frontend/feed.php${NC}"
    fi

    # Create uninstall.php
    if [ ! -f "uninstall.php" ]; then
        cat > uninstall.php << 'EOF'
<?php
/**
 * Uninstall BWG Instagram Feed
 *
 * @package BWG_Instagram_Feed
 */

// Exit if not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if user opted to delete data.
$delete_data = get_option( 'bwg_igf_delete_data_on_uninstall', false );

if ( $delete_data ) {
    global $wpdb;

    // Delete database tables.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bwg_igf_feeds" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bwg_igf_accounts" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bwg_igf_cache" );

    // Delete options.
    $options = array(
        'bwg_igf_db_version',
        'bwg_igf_default_cache_duration',
        'bwg_igf_delete_data_on_uninstall',
        'bwg_igf_instagram_app_id',
        'bwg_igf_instagram_app_secret',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Clear transients.
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bwg_igf_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bwg_igf_%'" );
}
EOF
        echo -e "${GREEN}  ✓ Created uninstall.php${NC}"
    fi
}

# Print success information
print_success() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              Setup Complete!                              ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""

    if [ "$SETUP_METHOD" = "docker" ]; then
        echo -e "${BLUE}Access your development environment:${NC}"
        echo ""
        echo "  WordPress:    http://localhost:8080"
        echo "  phpMyAdmin:   http://localhost:8081"
        echo ""
        echo "  Database credentials:"
        echo "    Host:       db"
        echo "    User:       wordpress"
        echo "    Password:   wordpress"
        echo "    Database:   wordpress"
        echo ""
        echo -e "${YELLOW}Next steps:${NC}"
        echo "  1. Open http://localhost:8080 and complete WordPress installation"
        echo "  2. Log into WordPress admin"
        echo "  3. Navigate to Plugins and activate 'BWG Instagram Feed'"
        echo ""
    else
        echo -e "${YELLOW}Next steps:${NC}"
        echo "  1. Copy or symlink this plugin to your WordPress wp-content/plugins/ directory"
        echo "  2. Activate the plugin in WordPress admin"
        echo ""
    fi

    echo -e "${BLUE}Plugin structure created:${NC}"
    echo "  ./bwg-instagram-feed.php    - Main plugin file"
    echo "  ./includes/                 - PHP class files"
    echo "  ./assets/css/               - Stylesheets"
    echo "  ./assets/js/                - JavaScript files"
    echo "  ./templates/                - Template files"
    echo "  ./languages/                - Translation files"
    echo ""
    echo -e "${GREEN}Happy developing!${NC}"
}

# Main execution
main() {
    check_requirements
    create_structure
    create_plugin_file
    create_placeholder_files

    if [ "$SETUP_METHOD" = "docker" ]; then
        setup_docker
    else
        setup_local
    fi

    print_success
}

# Run main function
main
