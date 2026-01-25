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
        <div class="bwg-igf-logo">
            <span class="bwg-igf-logo-icon dashicons dashicons-instagram"></span>
        </div>
        <div class="bwg-igf-branding">
            <h1><?php esc_html_e( 'Settings', 'bwg-instagram-feed' ); ?></h1>
            <span class="bwg-igf-brand-tagline"><?php esc_html_e( 'BWG Instagram Feed', 'bwg-instagram-feed' ); ?></span>
            <span class="bwg-igf-version"><?php /* translators: %s: plugin version number */ printf( esc_html__( 'Version %s', 'bwg-instagram-feed' ), esc_html( BWG_IGF_VERSION ) ); ?></span>
        </div>
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
            <h2><?php esc_html_e( 'GitHub Updates', 'bwg-instagram-feed' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Plugin updates are automatically retrieved from the official GitHub repository.', 'bwg-instagram-feed' ); ?>
            </p>

            <?php
            // Show current update status.
            if ( class_exists( 'BWG_IGF_GitHub_Updater' ) ) {
                $updater = BWG_IGF_GitHub_Updater::get_instance();
                $status = $updater->get_status();

                if ( $status['configured'] ) {
                    $release = $updater->get_github_release();
                    ?>
                    <div class="bwg-igf-github-status" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                        <p>
                            <strong><?php esc_html_e( 'Repository:', 'bwg-instagram-feed' ); ?></strong>
                            <a href="<?php echo esc_url( $status['github_url'] ?? 'https://github.com/sawdustandcoffee/bwg-instagram-feed' ); ?>" target="_blank" rel="noopener noreferrer">
                                sawdustandcoffee/bwg-instagram-feed
                            </a>
                        </p>
                        <p>
                            <strong><?php esc_html_e( 'Releases:', 'bwg-instagram-feed' ); ?></strong>
                            <a href="https://github.com/sawdustandcoffee/bwg-instagram-feed/releases" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e( 'View All Releases', 'bwg-instagram-feed' ); ?>
                            </a>
                            <span class="description" style="margin-left: 10px;"><?php esc_html_e( '(Release history, changelog, and previous versions)', 'bwg-instagram-feed' ); ?></span>
                        </p>
                        <p>
                            <strong><?php esc_html_e( 'Current Version:', 'bwg-instagram-feed' ); ?></strong>
                            <?php echo esc_html( $status['version'] ); ?>
                        </p>
                        <?php if ( ! empty( $status['library'] ) ) : ?>
                        <p>
                            <strong><?php esc_html_e( 'Update Library:', 'bwg-instagram-feed' ); ?></strong>
                            <?php echo esc_html( $status['library'] ); ?>
                        </p>
                        <?php endif; ?>
                        <?php if ( $release ) : ?>
                            <p>
                                <strong><?php esc_html_e( 'Latest Version:', 'bwg-instagram-feed' ); ?></strong>
                                <?php echo esc_html( $release->version ); ?>
                                <?php if ( version_compare( $release->version, $status['version'], '>' ) ) : ?>
                                    <span style="color: #d63638; font-weight: bold;">
                                        (<?php esc_html_e( 'Update available!', 'bwg-instagram-feed' ); ?>)
                                    </span>
                                <?php else : ?>
                                    <span style="color: #00a32a;">
                                        (<?php esc_html_e( 'Up to date', 'bwg-instagram-feed' ); ?>)
                                    </span>
                                <?php endif; ?>
                            </p>
                        <?php else : ?>
                            <p style="color: #d63638;">
                                <?php esc_html_e( 'Could not fetch release information from GitHub.', 'bwg-instagram-feed' ); ?>
                            </p>
                        <?php endif; ?>
                        <p>
                            <button type="button" class="button bwg-igf-check-updates" id="bwg-igf-check-updates">
                                <?php esc_html_e( 'Check for Updates Now', 'bwg-instagram-feed' ); ?>
                            </button>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>
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
