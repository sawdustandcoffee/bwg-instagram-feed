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
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin list needs fresh data
    $feeds = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM %i ORDER BY created_at DESC',
            $wpdb->prefix . 'bwg_igf_feeds'
        )
    );
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
        <!-- Search box for filtering feeds (Feature #117) -->
        <div class="bwg-igf-feeds-search" style="margin-bottom: 15px;">
            <label for="bwg-igf-feed-search" class="screen-reader-text"><?php esc_html_e( 'Search Feeds', 'bwg-instagram-feed' ); ?></label>
            <input type="search" id="bwg-igf-feed-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search feeds by name...', 'bwg-instagram-feed' ); ?>" autocomplete="off">
            <span id="bwg-igf-feed-search-count" style="margin-left: 10px; color: #666;"></span>
        </div>
        <table class="wp-list-table widefat fixed striped" id="bwg-igf-feeds-table">
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
