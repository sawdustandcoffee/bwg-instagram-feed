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

    // Delete database tables using prepared statements.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop on uninstall
    $wpdb->query(
        $wpdb->prepare(
            'DROP TABLE IF EXISTS %i',
            $wpdb->prefix . 'bwg_igf_feeds'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop on uninstall
    $wpdb->query(
        $wpdb->prepare(
            'DROP TABLE IF EXISTS %i',
            $wpdb->prefix . 'bwg_igf_accounts'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional table drop on uninstall
    $wpdb->query(
        $wpdb->prepare(
            'DROP TABLE IF EXISTS %i',
            $wpdb->prefix . 'bwg_igf_cache'
        )
    );

    // Delete options.
    // Note: instagram_app_id and instagram_app_secret are no longer stored in wp_options
    // as they are now built into the plugin code.
    $options = array(
        'bwg_igf_db_version',
        'bwg_igf_default_cache_duration',
        'bwg_igf_delete_data_on_uninstall',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Clear transients using prepared statements.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional transient cleanup on uninstall
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM %i WHERE option_name LIKE %s',
            $wpdb->options,
            '_transient_bwg_igf_%'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional transient cleanup on uninstall
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM %i WHERE option_name LIKE %s',
            $wpdb->options,
            '_transient_timeout_bwg_igf_%'
        )
    );
}
