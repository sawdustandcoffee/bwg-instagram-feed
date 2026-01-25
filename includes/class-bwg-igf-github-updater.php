<?php
/**
 * GitHub Updater Class
 *
 * Handles checking for plugin updates from GitHub releases.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BWG_IGF_GitHub_Updater
 *
 * Integrates with WordPress plugin update system to check GitHub for new versions.
 */
class BWG_IGF_GitHub_Updater {

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Plugin file path.
     *
     * @var string
     */
    private $plugin_file;

    /**
     * GitHub repository owner.
     *
     * @var string
     */
    private $github_owner;

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $github_repo;

    /**
     * GitHub API URL for releases.
     *
     * @var string
     */
    private $github_api_url;

    /**
     * Current plugin version.
     *
     * @var string
     */
    private $current_version;

    /**
     * Cached GitHub data.
     *
     * @var object|null
     */
    private $github_response = null;

    /**
     * Transient key for caching.
     *
     * @var string
     */
    private $cache_key = 'bwg_igf_github_update_data';

    /**
     * Cache duration in seconds (12 hours).
     *
     * @var int
     */
    private $cache_duration = 43200;

    /**
     * Class instance.
     *
     * @var BWG_IGF_GitHub_Updater
     */
    private static $instance = null;

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
     * Hardcoded GitHub repository URL.
     *
     * @var string
     */
    const GITHUB_REPO_URL = 'https://github.com/sawdustandcoffee/bwg-instagram-feed';

    /**
     * Constructor.
     */
    private function __construct() {
        $this->plugin_file    = BWG_IGF_PLUGIN_BASENAME;
        $this->plugin_slug    = dirname( $this->plugin_file );
        $this->current_version = BWG_IGF_VERSION;

        // Use hardcoded GitHub repository URL.
        $this->parse_github_url( self::GITHUB_REPO_URL );

        // Only hook if we have valid GitHub settings.
        if ( $this->is_configured() ) {
            $this->init_hooks();
        }
    }

    /**
     * Parse GitHub repository URL to extract owner and repo.
     *
     * @param string $url GitHub repository URL.
     */
    private function parse_github_url( $url ) {
        if ( empty( $url ) ) {
            $this->github_owner = '';
            $this->github_repo  = '';
            return;
        }

        // Support various GitHub URL formats.
        // https://github.com/owner/repo
        // https://github.com/owner/repo.git
        // git@github.com:owner/repo.git
        $patterns = array(
            '#https?://github\.com/([^/]+)/([^/\.]+)(?:\.git)?#i',
            '#git@github\.com:([^/]+)/([^/\.]+)(?:\.git)?#i',
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $matches ) ) {
                $this->github_owner = $matches[1];
                $this->github_repo  = $matches[2];
                $this->github_api_url = sprintf(
                    'https://api.github.com/repos/%s/%s/releases/latest',
                    $this->github_owner,
                    $this->github_repo
                );
                return;
            }
        }

        // Fallback: try to use URL as-is if it looks like owner/repo format.
        if ( preg_match( '#^([^/]+)/([^/]+)$#', $url, $matches ) ) {
            $this->github_owner = $matches[1];
            $this->github_repo  = $matches[2];
            $this->github_api_url = sprintf(
                'https://api.github.com/repos/%s/%s/releases/latest',
                $this->github_owner,
                $this->github_repo
            );
        }
    }

    /**
     * Check if GitHub updater is properly configured.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured() {
        return ! empty( $this->github_owner ) && ! empty( $this->github_repo );
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Hook into the plugin update check.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

        // Hook into plugin information.
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );

        // After plugin installed, ensure proper activation.
        add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
    }

    /**
     * Get release data from GitHub.
     *
     * @param bool $force Force fresh fetch, ignore cache.
     * @return object|false Release data or false on failure.
     */
    public function get_github_release( $force = false ) {
        // Check cache first.
        if ( ! $force ) {
            $cached = get_transient( $this->cache_key );
            if ( false !== $cached ) {
                $this->github_response = $cached;
                return $cached;
            }
        }

        // Fetch from GitHub API.
        $response = wp_remote_get(
            $this->github_api_url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'BWG-Instagram-Feed/' . $this->current_version,
                ),
            )
        );

        // Check for errors.
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( empty( $data ) || ! isset( $data->tag_name ) ) {
            return false;
        }

        // Parse the release data.
        $release = new stdClass();
        $release->version     = ltrim( $data->tag_name, 'v' );
        $release->name        = isset( $data->name ) ? $data->name : $data->tag_name;
        $release->body        = isset( $data->body ) ? $data->body : '';
        $release->published   = isset( $data->published_at ) ? $data->published_at : '';
        $release->html_url    = isset( $data->html_url ) ? $data->html_url : '';

        // Get download URL (prefer zipball, fallback to first asset).
        $release->download_url = isset( $data->zipball_url ) ? $data->zipball_url : '';

        // Check for asset with .zip extension.
        if ( ! empty( $data->assets ) && is_array( $data->assets ) ) {
            foreach ( $data->assets as $asset ) {
                if ( isset( $asset->browser_download_url ) && preg_match( '/\.zip$/', $asset->browser_download_url ) ) {
                    $release->download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // Cache the result.
        set_transient( $this->cache_key, $release, $this->cache_duration );
        $this->github_response = $release;

        return $release;
    }

    /**
     * Check for plugin updates.
     *
     * @param object $transient Update transient object.
     * @return object Modified transient.
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get GitHub release data.
        $release = $this->get_github_release();

        if ( false === $release ) {
            return $transient;
        }

        // Compare versions.
        if ( version_compare( $release->version, $this->current_version, '>' ) ) {
            $plugin_data = array(
                'id'            => $this->plugin_file,
                'slug'          => $this->plugin_slug,
                'plugin'        => $this->plugin_file,
                'new_version'   => $release->version,
                'url'           => $release->html_url,
                'package'       => $release->download_url,
                'icons'         => array(),
                'banners'       => array(),
                'banners_rtl'   => array(),
                'tested'        => '',
                'requires_php'  => '7.4',
                'compatibility' => new stdClass(),
            );

            $transient->response[ $this->plugin_file ] = (object) $plugin_data;
        } else {
            // No update available, add to no_update.
            $plugin_data = array(
                'id'            => $this->plugin_file,
                'slug'          => $this->plugin_slug,
                'plugin'        => $this->plugin_file,
                'new_version'   => $this->current_version,
                'url'           => '',
                'package'       => '',
            );

            $transient->no_update[ $this->plugin_file ] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * Provide plugin information for the "View Details" popup.
     *
     * @param false|object|array $result The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin info or false.
     */
    public function plugin_info( $result, $action, $args ) {
        // Only handle plugin information requests.
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        // Only handle our plugin.
        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        // Get GitHub release data.
        $release = $this->get_github_release();

        if ( false === $release ) {
            return $result;
        }

        // Build plugin info object.
        $plugin_info = new stdClass();
        $plugin_info->name          = 'BWG Instagram Feed';
        $plugin_info->slug          = $this->plugin_slug;
        $plugin_info->version       = $release->version;
        $plugin_info->author        = '<a href="https://bostonwebgroup.com">Boston Web Group</a>';
        $plugin_info->homepage      = 'https://bostonwebgroup.com/plugins/instagram-feed';
        $plugin_info->requires      = '5.8';
        $plugin_info->tested        = get_bloginfo( 'version' );
        $plugin_info->requires_php  = '7.4';
        $plugin_info->downloaded    = 0;
        $plugin_info->last_updated  = $release->published;
        $plugin_info->download_link = $release->download_url;

        // Convert Markdown changelog to HTML (basic conversion).
        $plugin_info->sections = array(
            'description' => __( 'Display Instagram feeds on your WordPress website with customizable layouts, styling, and both public and connected account support.', 'bwg-instagram-feed' ),
            'changelog'   => $this->markdown_to_html( $release->body ),
        );

        return $plugin_info;
    }

    /**
     * Handle post-install to ensure proper directory naming.
     *
     * @param bool  $response   Installation response.
     * @param array $hook_extra Extra information about the update.
     * @param array $result     Installation result data.
     * @return array Modified result.
     */
    public function post_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Only handle our plugin.
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
            return $result;
        }

        // Get the installed location and correct plugin directory.
        $install_directory = $result['destination'];
        $proper_directory  = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

        // If the directory name is different (GitHub zipball uses owner-repo-hash format).
        if ( $install_directory !== $proper_directory ) {
            $wp_filesystem->move( $install_directory, $proper_directory );
            $result['destination'] = $proper_directory;
        }

        // Activate the plugin.
        $activate = activate_plugin( $this->plugin_file );

        return $result;
    }

    /**
     * Convert basic Markdown to HTML.
     *
     * @param string $markdown Markdown text.
     * @return string HTML.
     */
    private function markdown_to_html( $markdown ) {
        if ( empty( $markdown ) ) {
            return '';
        }

        // Convert headers.
        $markdown = preg_replace( '/^### (.*)$/m', '<h4>$1</h4>', $markdown );
        $markdown = preg_replace( '/^## (.*)$/m', '<h3>$1</h3>', $markdown );
        $markdown = preg_replace( '/^# (.*)$/m', '<h2>$1</h2>', $markdown );

        // Convert bold and italic.
        $markdown = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $markdown );
        $markdown = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $markdown );

        // Convert unordered lists.
        $markdown = preg_replace( '/^\* (.*)$/m', '<li>$1</li>', $markdown );
        $markdown = preg_replace( '/^- (.*)$/m', '<li>$1</li>', $markdown );

        // Wrap consecutive list items in ul.
        $markdown = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $markdown );

        // Convert line breaks.
        $markdown = nl2br( $markdown );

        // Clean up multiple br tags.
        $markdown = preg_replace( '/(<br\s*\/?>\s*){2,}/', '<br><br>', $markdown );

        return $markdown;
    }

    /**
     * Clear the update cache.
     */
    public function clear_cache() {
        delete_transient( $this->cache_key );
        $this->github_response = null;
    }

    /**
     * Get the current configuration status.
     *
     * @return array Configuration status.
     */
    public function get_status() {
        return array(
            'configured'  => $this->is_configured(),
            'owner'       => $this->github_owner,
            'repo'        => $this->github_repo,
            'api_url'     => $this->github_api_url,
            'version'     => $this->current_version,
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
}
