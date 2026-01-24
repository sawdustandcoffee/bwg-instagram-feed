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
