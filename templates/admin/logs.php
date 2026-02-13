<?php
/**
 * Admin Logs Template
 *
 * Displays a searchable/filterable table of plugin logs.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security check: Verify user has permission to view logs.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Unauthorized access', 'bwg-instagram-feed' ) );
}

// Get filter values from request.
$current_level      = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
$current_feed_id    = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;
$current_date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$current_date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$current_search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$current_page       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page           = 50;

// Get feeds for filter dropdown with object caching to reduce database queries.
global $wpdb;
$feeds = wp_cache_get( 'bwg_igf_feeds_list' );
if ( false === $feeds ) {
	$feeds_table = $wpdb->prefix . 'bwg_igf_feeds';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$feeds = $wpdb->get_results( "SELECT id, name FROM {$feeds_table} ORDER BY name ASC" );
	// Cache for 1 hour (3600 seconds).
	wp_cache_set( 'bwg_igf_feeds_list', $feeds, '', 3600 );
}

// Get logs with current filters.
$logs_result = BWG_IGF_Logger::get_logs(
	array(
		'level'     => $current_level,
		'feed_id'   => $current_feed_id,
		'date_from' => $current_date_from,
		'date_to'   => $current_date_to,
		'search'    => $current_search,
		'per_page'  => $per_page,
		'page'      => $current_page,
	)
);

$logs       = $logs_result['logs'];
$total_logs = $logs_result['total'];
$total_pages = $logs_result['pages'];

// Get log statistics.
$stats = BWG_IGF_Logger::get_stats();
?>
<div class="wrap bwg-igf-logs-page">
	<div class="bwg-igf-header">
		<div class="bwg-igf-logo">
			<span class="bwg-igf-logo-icon dashicons dashicons-instagram"></span>
		</div>
		<div class="bwg-igf-branding">
			<h1><?php esc_html_e( 'Logs', 'bwg-instagram-feed' ); ?></h1>
			<span class="bwg-igf-brand-tagline"><?php esc_html_e( 'BWG Instagram Feed', 'bwg-instagram-feed' ); ?></span>
			<span class="bwg-igf-version"><?php /* translators: %s: plugin version number */ printf( esc_html__( 'Version %s', 'bwg-instagram-feed' ), esc_html( BWG_IGF_VERSION ) ); ?></span>
		</div>
	</div>

	<!-- Log Statistics -->
	<div class="bwg-igf-logs-stats">
		<div class="bwg-igf-stat-box">
			<span class="bwg-igf-stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
			<span class="bwg-igf-stat-label"><?php esc_html_e( 'Total Entries', 'bwg-instagram-feed' ); ?></span>
		</div>
		<div class="bwg-igf-stat-box bwg-igf-stat-error">
			<span class="bwg-igf-stat-number"><?php echo esc_html( $stats['errors_24h'] ); ?></span>
			<span class="bwg-igf-stat-label"><?php esc_html_e( 'Errors (24h)', 'bwg-instagram-feed' ); ?></span>
		</div>
		<div class="bwg-igf-stat-box bwg-igf-stat-warning">
			<span class="bwg-igf-stat-number"><?php echo esc_html( $stats['warnings_24h'] ); ?></span>
			<span class="bwg-igf-stat-label"><?php esc_html_e( 'Warnings (24h)', 'bwg-instagram-feed' ); ?></span>
		</div>
		<div class="bwg-igf-stat-box bwg-igf-stat-info">
			<span class="bwg-igf-stat-number"><?php echo esc_html( $stats['info_24h'] ); ?></span>
			<span class="bwg-igf-stat-label"><?php esc_html_e( 'Info (24h)', 'bwg-instagram-feed' ); ?></span>
		</div>
	</div>

	<!-- Filter Form -->
	<div class="bwg-igf-logs-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="bwg-igf-logs">

			<div class="bwg-igf-filter-row">
				<!-- Level Filter -->
				<div class="bwg-igf-filter-item">
					<label for="bwg-igf-filter-level"><?php esc_html_e( 'Level:', 'bwg-instagram-feed' ); ?></label>
					<select name="level" id="bwg-igf-filter-level">
						<option value=""><?php esc_html_e( 'All Levels', 'bwg-instagram-feed' ); ?></option>
						<option value="error" <?php selected( $current_level, 'error' ); ?>><?php esc_html_e( 'Error', 'bwg-instagram-feed' ); ?></option>
						<option value="warning" <?php selected( $current_level, 'warning' ); ?>><?php esc_html_e( 'Warning', 'bwg-instagram-feed' ); ?></option>
						<option value="info" <?php selected( $current_level, 'info' ); ?>><?php esc_html_e( 'Info', 'bwg-instagram-feed' ); ?></option>
					</select>
				</div>

				<!-- Feed Filter -->
				<div class="bwg-igf-filter-item">
					<label for="bwg-igf-filter-feed"><?php esc_html_e( 'Feed:', 'bwg-instagram-feed' ); ?></label>
					<select name="feed_id" id="bwg-igf-filter-feed">
						<option value=""><?php esc_html_e( 'All Feeds', 'bwg-instagram-feed' ); ?></option>
						<?php foreach ( $feeds as $feed ) : ?>
							<option value="<?php echo esc_attr( $feed->id ); ?>" <?php selected( $current_feed_id, $feed->id ); ?>>
								<?php echo esc_html( $feed->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Date From -->
				<div class="bwg-igf-filter-item">
					<label for="bwg-igf-filter-date-from"><?php esc_html_e( 'From:', 'bwg-instagram-feed' ); ?></label>
					<input type="date" name="date_from" id="bwg-igf-filter-date-from" value="<?php echo esc_attr( $current_date_from ); ?>">
				</div>

				<!-- Date To -->
				<div class="bwg-igf-filter-item">
					<label for="bwg-igf-filter-date-to"><?php esc_html_e( 'To:', 'bwg-instagram-feed' ); ?></label>
					<input type="date" name="date_to" id="bwg-igf-filter-date-to" value="<?php echo esc_attr( $current_date_to ); ?>">
				</div>

				<!-- Search -->
				<div class="bwg-igf-filter-item bwg-igf-filter-search">
					<label for="bwg-igf-filter-search"><?php esc_html_e( 'Search:', 'bwg-instagram-feed' ); ?></label>
					<input type="text" name="s" id="bwg-igf-filter-search" value="<?php echo esc_attr( $current_search ); ?>" placeholder="<?php esc_attr_e( 'Search messages...', 'bwg-instagram-feed' ); ?>">
				</div>

				<!-- Filter Button -->
				<div class="bwg-igf-filter-item bwg-igf-filter-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'bwg-instagram-feed' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-logs' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bwg-instagram-feed' ); ?></a>
				</div>
			</div>
		</form>
	</div>

	<!-- Actions Bar -->
	<div class="bwg-igf-logs-actions">
		<div class="bwg-igf-logs-actions-left">
			<span class="bwg-igf-logs-count">
				<?php
				/* translators: %d: number of log entries */
				printf( esc_html__( '%d entries found', 'bwg-instagram-feed' ), $total_logs );
				?>
			</span>
		</div>
		<div class="bwg-igf-logs-actions-right">
			<label class="bwg-igf-auto-refresh-label">
				<input type="checkbox" id="bwg-igf-auto-refresh" class="bwg-igf-auto-refresh-checkbox">
				<?php esc_html_e( 'Auto-refresh (30s)', 'bwg-instagram-feed' ); ?>
			</label>
			<button type="button" class="button bwg-igf-refresh-logs">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'bwg-instagram-feed' ); ?>
			</button>
			<button type="button" class="button bwg-igf-clear-logs">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear All Logs', 'bwg-instagram-feed' ); ?>
			</button>
		</div>
	</div>

	<!-- Logs Table -->
	<div class="bwg-igf-logs-table-wrapper">
		<table class="wp-list-table widefat fixed striped bwg-igf-logs-table">
			<thead>
				<tr>
					<th class="bwg-igf-col-timestamp"><?php esc_html_e( 'Timestamp', 'bwg-instagram-feed' ); ?></th>
					<th class="bwg-igf-col-level"><?php esc_html_e( 'Level', 'bwg-instagram-feed' ); ?></th>
					<th class="bwg-igf-col-feed"><?php esc_html_e( 'Feed', 'bwg-instagram-feed' ); ?></th>
					<th class="bwg-igf-col-message"><?php esc_html_e( 'Message', 'bwg-instagram-feed' ); ?></th>
					<th class="bwg-igf-col-context"><?php esc_html_e( 'Details', 'bwg-instagram-feed' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr class="no-items">
						<td colspan="5" class="bwg-igf-no-logs">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'No log entries found.', 'bwg-instagram-feed' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr class="bwg-igf-log-row bwg-igf-log-<?php echo esc_attr( $log['level'] ); ?>">
							<td class="bwg-igf-col-timestamp">
								<span class="bwg-igf-log-date">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $log['timestamp'] ) ) ); ?>
								</span>
								<span class="bwg-igf-log-time">
									<?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $log['timestamp'] ) ) ); ?>
								</span>
							</td>
							<td class="bwg-igf-col-level">
								<span class="bwg-igf-log-level bwg-igf-log-level-<?php echo esc_attr( $log['level'] ); ?>">
									<?php
									switch ( $log['level'] ) {
										case 'error':
											echo '<span class="dashicons dashicons-dismiss"></span>';
											esc_html_e( 'Error', 'bwg-instagram-feed' );
											break;
										case 'warning':
											echo '<span class="dashicons dashicons-warning"></span>';
											esc_html_e( 'Warning', 'bwg-instagram-feed' );
											break;
										default:
											echo '<span class="dashicons dashicons-info"></span>';
											esc_html_e( 'Info', 'bwg-instagram-feed' );
											break;
									}
									?>
								</span>
							</td>
							<td class="bwg-igf-col-feed">
								<?php if ( ! empty( $log['feed_name'] ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=bwg-igf-feeds&action=edit&feed_id=' . $log['feed_id'] ) ); ?>">
										<?php echo esc_html( $log['feed_name'] ); ?>
									</a>
								<?php elseif ( ! empty( $log['feed_id'] ) ) : ?>
									<?php
									/* translators: %d: feed ID */
									printf( esc_html__( 'Feed #%d', 'bwg-instagram-feed' ), absint( $log['feed_id'] ) );
									?>
								<?php else : ?>
									<span class="bwg-igf-log-na"><?php esc_html_e( '-', 'bwg-instagram-feed' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bwg-igf-col-message">
								<span class="bwg-igf-log-message"><?php echo esc_html( $log['message'] ); ?></span>
							</td>
							<td class="bwg-igf-col-context">
								<?php if ( ! empty( $log['context'] ) && is_array( $log['context'] ) ) : ?>
									<button type="button" class="button button-small bwg-igf-toggle-context" data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
										<span class="dashicons dashicons-arrow-down-alt2"></span>
									</button>
									<div class="bwg-igf-log-context" id="bwg-igf-context-<?php echo esc_attr( $log['id'] ); ?>" style="display: none;">
										<pre><?php echo esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT ) ); ?></pre>
									</div>
								<?php else : ?>
									<span class="bwg-igf-log-na">-</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="bwg-igf-logs-pagination">
			<?php
			$pagination_base = add_query_arg(
				array(
					'page'      => 'bwg-igf-logs',
					'level'     => $current_level,
					'feed_id'   => $current_feed_id,
					'date_from' => $current_date_from,
					'date_to'   => $current_date_to,
					's'         => $current_search,
				),
				admin_url( 'admin.php' )
			);

			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => $pagination_base . '&paged=%#%',
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => '&laquo; ' . __( 'Previous', 'bwg-instagram-feed' ),
						'next_text' => __( 'Next', 'bwg-instagram-feed' ) . ' &raquo;',
					)
				)
			);
			?>
		</div>
	<?php endif; ?>
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	var autoRefreshInterval = null;

	// Toggle context details.
	$(document).on('click', '.bwg-igf-toggle-context', function() {
		var logId = $(this).data('log-id');
		var $context = $('#bwg-igf-context-' + logId);
		var $icon = $(this).find('.dashicons');

		$context.slideToggle(200);
		$icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
	});

	// Clear all logs.
	$(document).on('click', '.bwg-igf-clear-logs', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs? This action cannot be undone.', 'bwg-instagram-feed' ) ); ?>')) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true);

		$.ajax({
			url: bwgIgfAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bwg_igf_clear_logs',
				nonce: bwgIgfAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'Failed to clear logs.', 'bwg-instagram-feed' ) ); ?>');
					$button.prop('disabled', false);
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'bwg-instagram-feed' ) ); ?>');
				$button.prop('disabled', false);
			}
		});
	});

	// Refresh logs.
	$(document).on('click', '.bwg-igf-refresh-logs', function() {
		location.reload();
	});

	// Auto-refresh toggle.
	$(document).on('change', '#bwg-igf-auto-refresh', function() {
		if ($(this).is(':checked')) {
			autoRefreshInterval = setInterval(function() {
				location.reload();
			}, 30000); // 30 seconds
		} else {
			if (autoRefreshInterval) {
				clearInterval(autoRefreshInterval);
				autoRefreshInterval = null;
			}
		}
	});

})(jQuery);
</script>
