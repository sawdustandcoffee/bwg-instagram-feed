<?php
/**
 * BWG Instagram Feed Block.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BWG_IGF_Instagram_Feed_Block
 *
 * Handles Gutenberg block registration and rendering.
 */
class BWG_IGF_Instagram_Feed_Block {

    /**
     * Instance of this class.
     *
     * @var BWG_IGF_Instagram_Feed_Block
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return BWG_IGF_Instagram_Feed_Block
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
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
    }

    /**
     * Register the Gutenberg block.
     */
    public function register_block() {
        // Skip if register_block_type doesn't exist (WP < 5.0).
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            'bwg-igf/instagram-feed',
            array(
                'editor_script'   => 'bwg-igf-block-editor',
                'editor_style'    => 'bwg-igf-block-editor-style',
                'render_callback' => array( $this, 'render_block' ),
                'attributes'      => array(
                    'feedId' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                ),
                'supports'        => array(
                    'align'  => array( 'wide', 'full' ),
                    'anchor' => true,
                ),
            )
        );
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueue_block_editor_assets() {
        // Block editor script.
        wp_enqueue_script(
            'bwg-igf-block-editor',
            BWG_IGF_PLUGIN_URL . 'assets/js/block-editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
            BWG_IGF_VERSION,
            true
        );

        // Block editor styles.
        wp_enqueue_style(
            'bwg-igf-block-editor-style',
            BWG_IGF_PLUGIN_URL . 'assets/css/block-editor.css',
            array( 'wp-edit-blocks' ),
            BWG_IGF_VERSION
        );

        // Get available feeds for the block editor.
        $feeds = $this->get_feeds_for_editor();

        wp_localize_script(
            'bwg-igf-block-editor',
            'bwgIgfBlockData',
            array(
                'feeds'       => $feeds,
                'pluginUrl'   => BWG_IGF_PLUGIN_URL,
                'previewUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'bwg_igf_block_nonce' ),
                'i18n'        => array(
                    'blockTitle'       => __( 'BWG Instagram Feed', 'bwg-instagram-feed' ),
                    'blockDescription' => __( 'Display an Instagram feed on your page.', 'bwg-instagram-feed' ),
                    'selectFeed'       => __( 'Select a Feed', 'bwg-instagram-feed' ),
                    'noFeedsMessage'   => __( 'No feeds found. Please create a feed first.', 'bwg-instagram-feed' ),
                    'createFeedLink'   => __( 'Create a Feed', 'bwg-instagram-feed' ),
                    'feedLabel'        => __( 'Feed', 'bwg-instagram-feed' ),
                    'previewLabel'     => __( 'Preview', 'bwg-instagram-feed' ),
                ),
                'adminUrl'    => admin_url( 'admin.php?page=bwg-igf-feeds&action=new' ),
            )
        );
    }

    /**
     * Get feeds for the block editor dropdown.
     *
     * @return array Array of feeds.
     */
    private function get_feeds_for_editor() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bwg_igf_feeds';

        // Check if table exists first.
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( ! $table_exists ) {
            return array();
        }

        $results = $wpdb->get_results(
            "SELECT id, name, slug FROM {$table_name} WHERE status = 'active' ORDER BY name ASC",
            ARRAY_A
        );

        if ( ! $results ) {
            return array();
        }

        $feeds = array();
        foreach ( $results as $feed ) {
            $feeds[] = array(
                'value' => (string) $feed['id'],
                'label' => $feed['name'],
                'slug'  => $feed['slug'],
            );
        }

        return $feeds;
    }

    /**
     * Render the block on the frontend.
     *
     * @param array $attributes Block attributes.
     * @return string Rendered block HTML.
     */
    public function render_block( $attributes ) {
        $feed_id = isset( $attributes['feedId'] ) ? absint( $attributes['feedId'] ) : 0;

        if ( empty( $feed_id ) ) {
            return '';
        }

        // Use the existing shortcode rendering.
        return do_shortcode( '[bwg_igf id="' . $feed_id . '"]' );
    }
}

// Initialize the block.
BWG_IGF_Instagram_Feed_Block::get_instance();
