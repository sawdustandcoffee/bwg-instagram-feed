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
    update_option( 'bwg_igf_show_stale_data_indicator', isset( $_POST['show_stale_data_indicator'] ) ? 1 : 0 );

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bwg-instagram-feed' ) . '</p></div>';
}

$default_cache = get_option( 'bwg_igf_default_cache_duration', 3600 );
$delete_data = get_option( 'bwg_igf_delete_data_on_uninstall', 0 );
$show_stale_indicator = get_option( 'bwg_igf_show_stale_data_indicator', 0 );
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
                        <label for="default_cache_duration"><?php esc_html_e( 'Default Refresh Interval', 'bwg-instagram-feed' ); ?></label>
                    </th>
                    <td>
                        <select name="default_cache_duration" id="default_cache_duration">
                            <option value="1800" <?php selected( $default_cache, 1800 ); ?>><?php esc_html_e( 'Every 30 Minutes', 'bwg-instagram-feed' ); ?></option>
                            <option value="3600" <?php selected( $default_cache, 3600 ); ?>><?php esc_html_e( 'Every Hour', 'bwg-instagram-feed' ); ?></option>
                            <option value="21600" <?php selected( $default_cache, 21600 ); ?>><?php esc_html_e( 'Every 6 Hours', 'bwg-instagram-feed' ); ?></option>
                            <option value="43200" <?php selected( $default_cache, 43200 ); ?>><?php esc_html_e( 'Every 12 Hours', 'bwg-instagram-feed' ); ?></option>
                            <option value="86400" <?php selected( $default_cache, 86400 ); ?>><?php esc_html_e( 'Once a Day', 'bwg-instagram-feed' ); ?></option>
                            <option value="172800" <?php selected( $default_cache, 172800 ); ?>><?php esc_html_e( 'Every 2 Days', 'bwg-instagram-feed' ); ?></option>
                            <option value="259200" <?php selected( $default_cache, 259200 ); ?>><?php esc_html_e( 'Every 3 Days', 'bwg-instagram-feed' ); ?></option>
                            <option value="604800" <?php selected( $default_cache, 604800 ); ?>><?php esc_html_e( 'Once a Week', 'bwg-instagram-feed' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'How often to check Instagram for new posts. Feeds will always display cached content, even if the refresh fails.', 'bwg-instagram-feed' ); ?></p>
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
                <tr>
                    <th scope="row">
                        <label for="show_stale_data_indicator"><?php esc_html_e( 'Show Stale Data Indicator', 'bwg-instagram-feed' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_stale_data_indicator" id="show_stale_data_indicator" value="1" <?php checked( $show_stale_indicator, 1 ); ?>>
                            <?php esc_html_e( 'Show a subtle indicator when displaying cached/stale data', 'bwg-instagram-feed' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'When enabled, a small indicator will appear on feeds showing that the data may not be the most current.', 'bwg-instagram-feed' ); ?></p>
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
                $update_checker = $updater->get_update_checker();

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
                            <strong><?php esc_html_e( 'Last Checked:', 'bwg-instagram-feed' ); ?></strong>
                            <span id="bwg-igf-last-checked"><?php echo esc_html( $status['last_checked_formatted'] ?? __( 'Never', 'bwg-instagram-feed' ) ); ?></span>
                        </p>
                        <p>
                            <button type="button" class="button bwg-igf-check-updates" id="bwg-igf-check-updates">
                                <?php esc_html_e( 'Check for Updates Now', 'bwg-instagram-feed' ); ?>
                            </button>
                            <span id="bwg-igf-check-updates-status" style="margin-left: 10px;"></span>
                        </p>
                    </div>

                    <!-- Debug Information -->
                    <div class="bwg-igf-debug-info" style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #856404;"><?php esc_html_e( 'Update Debug Information', 'bwg-instagram-feed' ); ?></h3>
                        <?php
                        // Force a fresh check
                        if ( $update_checker ) {
                            $update_checker->checkForUpdates();
                            $state = $update_checker->getUpdateState();
                            $update = $state ? $state->getUpdate() : null;

                            echo '<table style="width: 100%; border-collapse: collapse;">';
                            echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Multisite:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . ( is_multisite() ? 'Yes' : 'No' ) . '</td></tr>';
                            echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Network Active:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . ( is_plugin_active_for_network( BWG_IGF_PLUGIN_BASENAME ) ? 'Yes' : 'No' ) . '</td></tr>';
                            echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Is Network Admin:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . ( is_network_admin() ? 'Yes' : 'No' ) . '</td></tr>';
                            echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Plugin File:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . esc_html( BWG_IGF_PLUGIN_FILE ) . '</td></tr>';
                            echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Plugin Basename:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . esc_html( BWG_IGF_PLUGIN_BASENAME ) . '</td></tr>';
                            echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Installed Version:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . esc_html( BWG_IGF_VERSION ) . '</td></tr>';

                            if ( $update ) {
                                echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Available Version:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd; color: green;">' . esc_html( $update->version ) . '</td></tr>';
                                echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Download URL:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd; word-break: break-all;">' . esc_html( $update->download_url ?? 'N/A' ) . '</td></tr>';
                            } else {
                                echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Available Version:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd; color: red;">No update found or same version</td></tr>';
                            }

                            // Check GitHub API directly
                            $api_url = 'https://api.github.com/repos/sawdustandcoffee/bwg-instagram-feed/releases/latest';
                            $response = wp_remote_get( $api_url, array(
                                'timeout' => 10,
                                'headers' => array( 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ),
                            ) );

                            if ( is_wp_error( $response ) ) {
                                echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>GitHub API:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd; color: red;">Error: ' . esc_html( $response->get_error_message() ) . '</td></tr>';
                            } else {
                                $code = wp_remote_retrieve_response_code( $response );
                                $body = wp_remote_retrieve_body( $response );
                                $data = json_decode( $body );

                                echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>GitHub API Status:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . esc_html( $code ) . '</td></tr>';

                                if ( $code === 200 && $data ) {
                                    echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>GitHub Latest Tag:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">' . esc_html( $data->tag_name ?? 'N/A' ) . '</td></tr>';

                                    $assets = $data->assets ?? array();
                                    if ( ! empty( $assets ) ) {
                                        echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Release Assets:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd;">';
                                        foreach ( $assets as $asset ) {
                                            echo esc_html( $asset->name ) . ' (' . esc_html( size_format( $asset->size ) ) . ')<br>';
                                        }
                                        echo '</td></tr>';
                                    } else {
                                        echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>Release Assets:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd; color: red;">NO ASSETS FOUND - This is likely the problem!</td></tr>';
                                    }
                                } elseif ( $code === 404 ) {
                                    echo '<tr><td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong>GitHub Error:</strong></td><td style="padding: 5px; border-bottom: 1px solid #ddd; color: red;">No releases found on GitHub</td></tr>';
                                }
                            }

                            echo '</table>';
                        }
                        ?>
                    </div>
                    <?php
                } else {
                    ?>
                    <div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">
                        <strong><?php esc_html_e( 'Update Checker Not Configured!', 'bwg-instagram-feed' ); ?></strong>
                        <p><?php esc_html_e( 'The GitHub update checker failed to initialize. This may be due to a conflict with another plugin.', 'bwg-instagram-feed' ); ?></p>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">
                    <strong><?php esc_html_e( 'Updater Class Not Found!', 'bwg-instagram-feed' ); ?></strong>
                    <p><?php esc_html_e( 'The BWG_IGF_GitHub_Updater class is not loaded. The updater file may be missing.', 'bwg-instagram-feed' ); ?></p>
                </div>
                <?php
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
