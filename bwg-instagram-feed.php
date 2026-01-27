<?php
/**
 * Plugin Name: BWG Instagram Feed
 * Plugin URI: https://bostonwebgroup.com/plugins/instagram-feed
 * Description: Display Instagram feeds on your WordPress website with customizable layouts, styling, and both public and connected account support.
 * Version: 1.3.19
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
define( 'BWG_IGF_VERSION', '1.3.19' );
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

        // Display rate limit warning banner on plugin admin pages.
        add_action( 'admin_notices', array( $this, 'display_rate_limit_warning_banner' ) );

        // Register shortcode.
        add_shortcode( 'bwg_igf', array( $this, 'render_shortcode' ) );

        // Load Gutenberg block early so it can register on init hook.
        $this->load_gutenberg_block();
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $this->create_tables();
        $this->set_default_options();

        // Schedule image proxy cache cleanup cron job.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-image-proxy.php';
        BWG_IGF_Image_Proxy::schedule_cleanup();

        // Initialize API tracker (creates table and schedules cleanup).
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-api-tracker.php';
        BWG_IGF_API_Tracker::create_table();
        BWG_IGF_API_Tracker::init();

        // Schedule smart background cache refresh cron job (Feature #25).
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-cache-refresher.php';
        BWG_IGF_Cache_Refresher::init();
        BWG_IGF_Cache_Refresher::schedule();

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Unschedule image proxy cache cleanup cron job.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-image-proxy.php';
        BWG_IGF_Image_Proxy::unschedule_cleanup();

        // Unschedule API tracker cleanup cron job.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-api-tracker.php';
        BWG_IGF_API_Tracker::unschedule_cleanup();

        // Unschedule cache refresh cron job (Feature #25).
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-cache-refresher.php';
        BWG_IGF_Cache_Refresher::unschedule();

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
        // Load admin AJAX handlers.
        $this->load_admin_ajax();
    }

    /**
     * Load admin AJAX handlers.
     */
    private function load_admin_ajax() {
        // Load encryption helper.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-encryption.php';

        // Load Instagram credentials (built-in OAuth credentials).
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-credentials.php';

        // Load API tracker for rate limit monitoring.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-api-tracker.php';
        BWG_IGF_API_Tracker::init();

        // Load smart background cache refresher (Feature #25).
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-cache-refresher.php';
        BWG_IGF_Cache_Refresher::init();

        // Load Instagram API service.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-api.php';

        // Load AJAX handlers - needed for both admin pages and admin-ajax.php requests.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/admin/class-bwg-igf-admin-ajax.php';

        // Load frontend AJAX handlers - needed for async feed loading.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/frontend/class-bwg-igf-frontend-ajax.php';

        // Load GitHub updater for plugin updates.
        // Uses the Plugin Update Checker library (YahnisElsts) to check GitHub releases.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-github-updater.php';
        BWG_IGF_GitHub_Updater::get_instance();

        // Load Image Proxy REST API for bypassing CORS on Instagram images.
        require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-image-proxy.php';
    }

    /**
     * Load Gutenberg block.
     */
    private function load_gutenberg_block() {
        require_once BWG_IGF_PLUGIN_DIR . 'includes/blocks/class-bwg-igf-instagram-feed-block.php';
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
            'default_cache_duration'    => 3600,
            'delete_data_on_uninstall'  => false,
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( 'bwg_igf_' . $key ) ) {
                add_option( 'bwg_igf_' . $key, $value );
            }
        }

        // Clean up legacy options if they exist (from previous versions).
        // Instagram App credentials are now built into the plugin code.
        delete_option( 'bwg_igf_instagram_app_id' );
        delete_option( 'bwg_igf_instagram_app_secret' );
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

        // Enqueue WordPress color picker for styling options.
        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_style(
            'bwg-igf-admin',
            BWG_IGF_PLUGIN_URL . 'assets/css/admin.css',
            array( 'wp-color-picker' ),
            BWG_IGF_VERSION
        );

        wp_enqueue_script(
            'bwg-igf-admin',
            BWG_IGF_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-color-picker' ),
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
                    'confirmDelete'    => __( 'Are you sure you want to delete this feed?', 'bwg-instagram-feed' ),
                    'saving'           => __( 'Saving...', 'bwg-instagram-feed' ),
                    'saved'            => __( 'Saved!', 'bwg-instagram-feed' ),
                    'error'            => __( 'An error occurred. Please try again.', 'bwg-instagram-feed' ),
                    'feedNameRequired' => __( 'Feed name is required.', 'bwg-instagram-feed' ),
                    'feedDeleted'      => __( 'Feed deleted successfully!', 'bwg-instagram-feed' ),
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
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'bwg_igf_frontend_nonce' ),
                'proxyBaseUrl' => rest_url( BWG_IGF_Image_Proxy::API_NAMESPACE . BWG_IGF_Image_Proxy::API_ROUTE ),
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
     * Display rate limit warning banner on plugin admin pages.
     *
     * Feature #15: Warning displayed when approaching rate limits.
     * Shows a prominent warning banner when API usage is approaching rate limits (80% of quota used).
     */
    public function display_rate_limit_warning_banner() {
        // Only show on plugin admin pages.
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'bwg-igf' ) === false ) {
            return;
        }

        // Check if API tracker class is available.
        if ( ! class_exists( 'BWG_IGF_API_Tracker' ) ) {
            return;
        }

        global $wpdb;

        // Get all connected accounts.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin notice check, caching not needed.
        $connected_accounts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, username FROM %i WHERE status = %s',
                $wpdb->prefix . 'bwg_igf_accounts',
                'active'
            )
        );

        if ( empty( $connected_accounts ) ) {
            return;
        }

        $warning_accounts = array();
        $limited_accounts = array();

        foreach ( $connected_accounts as $account ) {
            $status = BWG_IGF_API_Tracker::get_rate_limit_status( $account->id );

            // Check if rate limited (most severe).
            if ( $status['is_limited'] ) {
                $limited_accounts[] = $account->username;
                continue;
            }

            // Check if approaching limits.
            // Instagram's rate limit is typically around 200 calls/hour.
            // 80% = 160 calls, so remaining <= 40 means approaching limit.
            // Also check if remaining is very low (e.g., <= 20) as a stricter threshold.
            if ( null !== $status['remaining'] ) {
                // If remaining is below 40 (80% of typical 200 quota used), show warning.
                if ( $status['remaining'] <= 40 ) {
                    $warning_accounts[] = array(
                        'username'  => $account->username,
                        'remaining' => $status['remaining'],
                    );
                }
            }
        }

        // Display rate limited banner (most severe).
        if ( ! empty( $limited_accounts ) ) {
            $usernames = array_map(
                function( $username ) {
                    return '@' . esc_html( $username );
                },
                $limited_accounts
            );
            ?>
            <div class="notice notice-error bwg-igf-rate-limit-banner is-dismissible">
                <p>
                    <strong><span class="dashicons dashicons-warning" style="color: #d63638; margin-right: 5px;"></span><?php esc_html_e( 'Instagram Rate Limit Reached', 'bwg-instagram-feed' ); ?></strong>
                </p>
                <p>
                    <?php
                    printf(
                        /* translators: %s: comma-separated list of Instagram usernames */
                        esc_html__( 'The following account(s) are currently rate limited by Instagram: %s', 'bwg-instagram-feed' ),
                        '<strong>' . implode( ', ', $usernames ) . '</strong>'
                    );
                    ?>
                </p>
                <p>
                    <?php esc_html_e( 'Your cached posts will continue to display. To reduce API usage:', 'bwg-instagram-feed' ); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e( 'Increase the cache duration in Settings (e.g., 6 hours or 24 hours)', 'bwg-instagram-feed' ); ?></li>
                    <li><?php esc_html_e( 'Reduce the number of feeds using this account', 'bwg-instagram-feed' ); ?></li>
                    <li><?php esc_html_e( 'Wait for the rate limit to reset (usually within an hour)', 'bwg-instagram-feed' ); ?></li>
                </ul>
            </div>
            <?php
            return; // Don't show warning if already rate limited.
        }

        // Display approaching limits warning.
        if ( ! empty( $warning_accounts ) ) {
            ?>
            <div class="notice notice-warning bwg-igf-rate-limit-banner is-dismissible">
                <p>
                    <strong><span class="dashicons dashicons-warning" style="color: #dba617; margin-right: 5px;"></span><?php esc_html_e( 'Approaching Instagram Rate Limits', 'bwg-instagram-feed' ); ?></strong>
                </p>
                <p>
                    <?php
                    foreach ( $warning_accounts as $account ) {
                        printf(
                            /* translators: 1: Instagram username, 2: number of remaining API calls */
                            esc_html__( 'Account @%1$s has only %2$s API calls remaining.', 'bwg-instagram-feed' ),
                            esc_html( $account['username'] ),
                            '<strong>' . esc_html( $account['remaining'] ) . '</strong>'
                        );
                        echo '<br>';
                    }
                    ?>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Suggestion:', 'bwg-instagram-feed' ); ?></strong>
                    <?php esc_html_e( 'Consider increasing your cache duration to reduce API calls.', 'bwg-instagram-feed' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-settings' ) ); ?>">
                        <?php esc_html_e( 'Go to Settings', 'bwg-instagram-feed' ); ?> →
                    </a>
                </p>
            </div>
            <?php
        }
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
