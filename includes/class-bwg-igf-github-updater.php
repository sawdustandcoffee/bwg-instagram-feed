<?php
/**
 * GitHub Updater Class
 *
 * Handles checking for plugin updates from GitHub releases using the
 * Plugin Update Checker library by YahnisElsts.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load the Plugin Update Checker library.
require_once BWG_IGF_PLUGIN_DIR . 'includes/lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Class BWG_IGF_GitHub_Updater
 *
 * Integrates with WordPress plugin update system to check GitHub for new versions.
 * Uses the Plugin Update Checker library (v5.6) for reliable update detection.
 *
 * Supports optional private repository authentication via GitHub personal access token.
 * Set the token in wp_options as 'bwg_igf_github_token' to enable authentication.
 */
class BWG_IGF_GitHub_Updater {

    /**
     * Hardcoded GitHub repository URL.
     *
     * @var string
     */
    const GITHUB_REPO_URL = 'https://github.com/sawdustandcoffee/bwg-instagram-feed';

    /**
     * WordPress option key for GitHub access token.
     * Used for private repository authentication.
     *
     * @var string
     */
    const GITHUB_TOKEN_OPTION = 'bwg_igf_github_token';

    /**
     * WordPress option key for last update check timestamp.
     *
     * @var string
     */
    const LAST_CHECKED_OPTION = 'bwg_igf_last_update_check';

    /**
     * Class instance.
     *
     * @var BWG_IGF_GitHub_Updater
     */
    private static $instance = null;

    /**
     * Plugin Update Checker instance.
     *
     * @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker
     */
    private $update_checker = null;

    /**
     * Cache key for last checked timestamp.
     *
     * @var string
     */
    private $cache_key = 'bwg_igf_github_update_data';

    /**
     * Get singleton instance.
     *
     * @return BWG_IGF_GitHub_Updater
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
        $this->init_update_checker();
        $this->init_multisite_support();
    }

    /**
     * Initialize multisite support for update notices.
     *
     * On multisite with network-activated plugins, the standard plugin update
     * notice doesn't show on the Network Admin Plugins page. This method adds
     * the necessary hooks to display update notices properly.
     */
    private function init_multisite_support() {
        if ( ! is_multisite() ) {
            return;
        }

        // Add update notice row for network admin plugins page.
        add_action( 'after_plugin_row_' . plugin_basename( BWG_IGF_PLUGIN_FILE ), array( $this, 'show_network_update_notice' ), 10, 2 );

        // Also hook into the network plugins page to ensure transient is checked.
        add_action( 'network_admin_plugin_action_links_' . plugin_basename( BWG_IGF_PLUGIN_FILE ), array( $this, 'maybe_check_for_updates' ), 10, 1 );
    }

    /**
     * Show update notice on network admin plugins page.
     *
     * @param string $file   Plugin basename.
     * @param array  $plugin Plugin data.
     */
    public function show_network_update_notice( $file, $plugin ) {
        if ( ! is_network_admin() ) {
            return;
        }

        // Get update information from transient.
        $update_plugins = get_site_transient( 'update_plugins' );

        $plugin_file = plugin_basename( BWG_IGF_PLUGIN_FILE );

        // Check if there's an update available for this plugin.
        if ( ! isset( $update_plugins->response[ $plugin_file ] ) ) {
            return;
        }

        $update = $update_plugins->response[ $plugin_file ];
        $current_version = BWG_IGF_VERSION;

        // Only show if the available version is actually newer.
        if ( version_compare( $update->new_version, $current_version, '<=' ) ) {
            return;
        }

        // Get the number of columns in the plugins table.
        $wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
        $colspan = $wp_list_table->get_column_count();

        // Build the update message.
        $details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_file . '&section=changelog&TB_iframe=true&width=772&height=931' );
        $update_url  = wp_nonce_url(
            self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $plugin_file ),
            'upgrade-plugin_' . $plugin_file
        );

        echo '<tr class="plugin-update-tr active" id="' . esc_attr( $plugin_file . '-update' ) . '" data-slug="' . esc_attr( dirname( $plugin_file ) ) . '" data-plugin="' . esc_attr( $plugin_file ) . '">';
        echo '<td colspan="' . esc_attr( $colspan ) . '" class="plugin-update colspanchange">';
        echo '<div class="update-message notice inline notice-warning notice-alt"><p>';

        printf(
            /* translators: 1: Plugin name, 2: Version number, 3: Update URL, 4: Additional info. */
            __( 'There is a new version of %1$s available. <a href="%2$s" class="update-link" aria-label="Update %1$s now">Update now to version %3$s</a>.', 'bwg-instagram-feed' ),
            '<strong>' . esc_html( $plugin['Name'] ) . '</strong>',
            esc_url( $update_url ),
            esc_html( $update->new_version )
        );

        echo '</p></div>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Maybe trigger an update check when viewing network plugins page.
     *
     * This ensures the update transient is populated when an admin views
     * the network plugins page.
     *
     * @param array $actions Plugin action links.
     * @return array
     */
    public function maybe_check_for_updates( $actions ) {
        // Only check if the transient is empty or expired.
        $update_plugins = get_site_transient( 'update_plugins' );
        $plugin_file = plugin_basename( BWG_IGF_PLUGIN_FILE );

        if ( empty( $update_plugins ) || ! isset( $update_plugins->checked[ $plugin_file ] ) ) {
            // Trigger an update check.
            if ( $this->update_checker ) {
                $this->update_checker->checkForUpdates();
            }
        }

        return $actions;
    }

    /**
     * Initialize the Plugin Update Checker.
     */
    private function init_update_checker() {
        // Check if this slug is already registered (prevents duplicate registration error).
        $slug_check_filter = 'puc_is_slug_in_use-bwg-instagram-feed';
        if ( apply_filters( $slug_check_filter, false ) ) {
            // Slug already in use, skip initialization.
            return;
        }

        // Build the update checker using the PucFactory.
        $this->update_checker = PucFactory::buildUpdateChecker(
            self::GITHUB_REPO_URL,
            BWG_IGF_PLUGIN_FILE,
            'bwg-instagram-feed'
        );

        // Set the branch that contains the stable release.
        $this->update_checker->setBranch( 'main' );

        // Enable release assets - this tells PUC to download the .zip file
        // attached to GitHub releases instead of the source code zip.
        $this->update_checker->getVcsApi()->enableReleaseAssets();

        // Configure authentication for private repository support (if token is provided).
        $this->configure_authentication();
    }

    /**
     * Configure GitHub authentication for private repository access.
     *
     * If a GitHub personal access token is stored in WordPress options,
     * this method will configure the Plugin Update Checker to use it
     * for API requests and downloads.
     *
     * To enable private repository authentication:
     * 1. Create a GitHub personal access token with 'repo' scope
     * 2. Store it using: update_option('bwg_igf_github_token', 'your_token_here');
     *
     * Note: For public repositories, authentication is optional but can help
     * avoid API rate limits.
     */
    private function configure_authentication() {
        if ( ! $this->update_checker ) {
            return;
        }

        // Get the GitHub token from WordPress options.
        $github_token = $this->get_github_token();

        if ( ! empty( $github_token ) ) {
            // Set the authentication token on the VCS API.
            // This enables access to private repositories and higher API rate limits.
            $this->update_checker->getVcsApi()->setAuthentication( $github_token );
        }
    }

    /**
     * Get the GitHub access token from WordPress options.
     *
     * @return string The GitHub token, or empty string if not set.
     */
    public function get_github_token() {
        return get_option( self::GITHUB_TOKEN_OPTION, '' );
    }

    /**
     * Set the GitHub access token.
     *
     * @param string $token The GitHub personal access token.
     * @return bool True on success, false on failure.
     */
    public function set_github_token( $token ) {
        if ( empty( $token ) ) {
            delete_option( self::GITHUB_TOKEN_OPTION );
            return true;
        }

        $result = update_option( self::GITHUB_TOKEN_OPTION, sanitize_text_field( $token ) );

        // Reconfigure authentication if the update checker is already initialized.
        if ( $this->update_checker && ! empty( $token ) ) {
            $this->update_checker->getVcsApi()->setAuthentication( $token );
        }

        return $result;
    }

    /**
     * Check if authentication is enabled.
     *
     * @return bool True if a GitHub token is configured.
     */
    public function is_authentication_enabled() {
        return ! empty( $this->get_github_token() );
    }

    /**
     * Get the last update check timestamp.
     *
     * @return int|false Unix timestamp of last check, or false if never checked.
     */
    public function get_last_checked() {
        return get_option( self::LAST_CHECKED_OPTION, false );
    }

    /**
     * Set the last update check timestamp.
     *
     * @param int|null $timestamp Unix timestamp, or null to use current time.
     * @return bool True on success, false on failure.
     */
    public function set_last_checked( $timestamp = null ) {
        if ( null === $timestamp ) {
            $timestamp = time();
        }
        return update_option( self::LAST_CHECKED_OPTION, absint( $timestamp ) );
    }

    /**
     * Get the formatted last checked timestamp for display.
     *
     * @return string Formatted timestamp or "Never" if never checked.
     */
    public function get_last_checked_formatted() {
        $timestamp = $this->get_last_checked();
        if ( ! $timestamp ) {
            return __( 'Never', 'bwg-instagram-feed' );
        }
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }

    /**
     * Check if GitHub updater is properly configured.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured() {
        return null !== $this->update_checker;
    }

    /**
     * Get release data from GitHub.
     *
     * @param bool $force Force fresh fetch, ignore cache.
     * @return object|false Release data or false on failure.
     */
    public function get_github_release( $force = false ) {
        if ( ! $this->update_checker ) {
            return false;
        }

        // Force a check if requested.
        if ( $force ) {
            $this->update_checker->checkForUpdates();
        }

        // Get the update state.
        $state = $this->update_checker->getUpdateState();
        $update = $state->getUpdate();

        if ( ! $update ) {
            return false;
        }

        // Parse the release data.
        $release = new stdClass();
        $release->version     = $update->version;
        $release->name        = 'BWG Instagram Feed v' . $update->version;
        $release->body        = '';
        $release->published   = '';
        $release->html_url    = self::GITHUB_REPO_URL . '/releases';
        $release->download_url = $update->download_url;

        return $release;
    }

    /**
     * Clear the update cache.
     */
    public function clear_cache() {
        if ( $this->update_checker ) {
            $this->update_checker->checkForUpdates();
        }
        delete_transient( $this->cache_key );
    }

    /**
     * Get the current configuration status.
     *
     * @return array Configuration status.
     */
    public function get_status() {
        return array(
            'configured'             => $this->is_configured(),
            'github_url'             => self::GITHUB_REPO_URL,
            'version'                => BWG_IGF_VERSION,
            'library'                => 'Plugin Update Checker v5.6',
            'release_assets'         => true,
            'authentication_enabled' => $this->is_authentication_enabled(),
            'private_repo_support'   => true, // Feature #184: Always indicate support is available.
            'last_checked'           => $this->get_last_checked(),
            'last_checked_formatted' => $this->get_last_checked_formatted(),
        );
    }

    /**
     * Force check for updates now.
     *
     * @return object|false Release data or false.
     */
    public function check_now() {
        $this->clear_cache();

        // For multisite, also clear the site-wide update transient to force refresh.
        if ( is_multisite() ) {
            delete_site_transient( 'update_plugins' );
        }

        $release = $this->get_github_release( true );

        // Record the timestamp of this check (Feature #191).
        $this->set_last_checked();

        // Force WordPress to recheck plugin updates.
        wp_clean_plugins_cache( true );

        return $release;
    }

    /**
     * Get the update checker instance for external use.
     *
     * @return \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker|null
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
}
