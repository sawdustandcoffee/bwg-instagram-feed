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
 * Uses the Plugin Update Checker library (v5.5) for reliable update detection.
 */
class BWG_IGF_GitHub_Updater {

    /**
     * Hardcoded GitHub repository URL.
     *
     * @var string
     */
    const GITHUB_REPO_URL = 'https://github.com/sawdustandcoffee/bwg-instagram-feed';

    /**
     * Class instance.
     *
     * @var BWG_IGF_GitHub_Updater
     */
    private static $instance = null;

    /**
     * Plugin Update Checker instance.
     *
     * @var \YahnisElsts\PluginUpdateChecker\v5p5\Vcs\PluginUpdateChecker
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
            'configured'     => $this->is_configured(),
            'github_url'     => self::GITHUB_REPO_URL,
            'version'        => BWG_IGF_VERSION,
            'library'        => 'Plugin Update Checker v5.5',
            'release_assets' => true,
        );
    }

    /**
     * Force check for updates now.
     *
     * @return object|false Release data or false.
     */
    public function check_now() {
        $this->clear_cache();
        return $this->get_github_release( true );
    }

    /**
     * Get the update checker instance for external use.
     *
     * @return \YahnisElsts\PluginUpdateChecker\v5p5\Vcs\PluginUpdateChecker|null
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
}
