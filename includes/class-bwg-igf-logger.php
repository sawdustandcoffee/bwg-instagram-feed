<?php
/**
 * Admin Logging Dashboard - Logger Class
 *
 * Provides centralized logging functionality for the BWG Instagram Feed plugin.
 * Logs are stored in the database and can be viewed in the admin dashboard.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger Class
 *
 * Singleton class for logging throughout the plugin.
 * Logs events, API calls, errors, and warnings to a database table.
 */
class BWG_IGF_Logger {

	/**
	 * Singleton instance.
	 *
	 * @var BWG_IGF_Logger
	 */
	private static $instance = null;

	/**
	 * Table name for logs.
	 *
	 * @var string
	 */
	private static $table_name;

	/**
	 * Log level constants.
	 */
	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * Days to retain log entries.
	 *
	 * @var int
	 */
	const RETENTION_DAYS = 30;

	/**
	 * Maximum log entries to keep (prevents database bloat).
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 10000;

	/**
	 * Cron hook name for log cleanup.
	 *
	 * @var string
	 */
	const CLEANUP_CRON_HOOK = 'bwg_igf_logs_cleanup';

	/**
	 * Get singleton instance.
	 *
	 * @return BWG_IGF_Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern).
	 */
	private function __construct() {
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'bwg_igf_logs';

		// Schedule cleanup cron job.
		add_action( self::CLEANUP_CRON_HOOK, array( __CLASS__, 'cleanup_old_entries' ) );
	}

	/**
	 * Get the table name.
	 *
	 * @return string The table name with prefix.
	 */
	public static function get_table_name() {
		global $wpdb;
		if ( empty( self::$table_name ) ) {
			self::$table_name = $wpdb->prefix . 'bwg_igf_logs';
		}
		return self::$table_name;
	}

	/**
	 * Create the logs database table.
	 *
	 * @return bool True if table created successfully.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			level enum('info','warning','error') NOT NULL DEFAULT 'info',
			message text NOT NULL,
			feed_id bigint(20) UNSIGNED DEFAULT NULL,
			account_id bigint(20) UNSIGNED DEFAULT NULL,
			context longtext DEFAULT NULL,
			PRIMARY KEY (id),
			KEY timestamp (timestamp),
			KEY level (level),
			KEY feed_id (feed_id),
			KEY account_id (account_id),
			KEY feed_timestamp (feed_id, timestamp),
			KEY level_timestamp (level, timestamp)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return ( $table_exists === $table_name );
	}

	/**
	 * Check if the logs table exists.
	 *
	 * @return bool True if table exists.
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return ( $result === $table_name );
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level: 'info', 'warning', or 'error'.
	 * @param string $message The log message.
	 * @param array  $context Optional. Additional context data (feed_id, account_id, etc.).
	 * @return int|false The insert ID on success, false on failure.
	 */
	public static function log( $level, $message, $context = array() ) {
		global $wpdb;

		// Validate log level.
		$valid_levels = array( self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR );
		if ( ! in_array( $level, $valid_levels, true ) ) {
			$level = self::LEVEL_INFO;
		}

		// Ensure table exists.
		if ( ! self::table_exists() ) {
			self::create_table();
		}

		$table_name = self::get_table_name();

		// Extract special context fields.
		$feed_id    = isset( $context['feed_id'] ) ? absint( $context['feed_id'] ) : null;
		$account_id = isset( $context['account_id'] ) ? absint( $context['account_id'] ) : null;

		// Remove special fields from context before JSON encoding.
		unset( $context['feed_id'], $context['account_id'] );

		// Sanitize context values before encoding to prevent storing malicious data.
		if ( ! empty( $context ) ) {
			$context = array_map(
				function ( $value ) {
					if ( is_scalar( $value ) ) {
						return sanitize_text_field( (string) $value );
					}
					return $value;
				},
				$context
			);
		}

		// Prepare context JSON.
		$context_json = ! empty( $context ) ? wp_json_encode( $context ) : null;

		$data = array(
			'timestamp'  => current_time( 'mysql' ),
			'level'      => $level,
			'message'    => sanitize_text_field( $message ),
			'feed_id'    => $feed_id,
			'account_id' => $account_id,
			'context'    => $context_json,
		);

		$format = array( '%s', '%s', '%s', '%d', '%d', '%s' );

		// Handle nullable fields.
		if ( null === $feed_id ) {
			$data['feed_id'] = null;
		}
		if ( null === $account_id ) {
			$data['account_id'] = null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table_name, $data, $format );

		return ( false !== $result ) ? $wpdb->insert_id : false;
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Optional. Additional context data.
	 * @return int|false The insert ID on success, false on failure.
	 */
	public static function info( $message, $context = array() ) {
		return self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Optional. Additional context data.
	 * @return int|false The insert ID on success, false on failure.
	 */
	public static function warning( $message, $context = array() ) {
		return self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Optional. Additional context data.
	 * @return int|false The insert ID on success, false on failure.
	 */
	public static function error( $message, $context = array() ) {
		return self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Get logs with optional filtering.
	 *
	 * @param array $args {
	 *     Optional. Arguments for filtering logs.
	 *
	 *     @type string $level      Filter by log level.
	 *     @type int    $feed_id    Filter by feed ID.
	 *     @type int    $account_id Filter by account ID.
	 *     @type string $date_from  Filter by date (from).
	 *     @type string $date_to    Filter by date (to).
	 *     @type string $search     Search in message.
	 *     @type int    $per_page   Number of results per page.
	 *     @type int    $page       Page number (1-indexed).
	 *     @type string $orderby    Column to order by.
	 *     @type string $order      Order direction (ASC or DESC).
	 * }
	 * @return array Array with 'logs', 'total', and 'pages'.
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'level'      => '',
			'feed_id'    => 0,
			'account_id' => 0,
			'date_from'  => '',
			'date_to'    => '',
			'search'     => '',
			'per_page'   => 50,
			'page'       => 1,
			'orderby'    => 'timestamp',
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$result = array(
			'logs'  => array(),
			'total' => 0,
			'pages' => 0,
		);

		if ( ! self::table_exists() ) {
			return $result;
		}

		$table_name   = self::get_table_name();
		$feeds_table  = $wpdb->prefix . 'bwg_igf_feeds';
		$where_clauses = array();
		$where_values  = array();

		// Level filter.
		if ( ! empty( $args['level'] ) ) {
			$where_clauses[] = 'l.level = %s';
			$where_values[]  = sanitize_text_field( $args['level'] );
		}

		// Feed ID filter.
		if ( ! empty( $args['feed_id'] ) ) {
			$where_clauses[] = 'l.feed_id = %d';
			$where_values[]  = absint( $args['feed_id'] );
		}

		// Account ID filter.
		if ( ! empty( $args['account_id'] ) ) {
			$where_clauses[] = 'l.account_id = %d';
			$where_values[]  = absint( $args['account_id'] );
		}

		// Date from filter.
		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'l.timestamp >= %s';
			$where_values[]  = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		// Date to filter.
		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'l.timestamp <= %s';
			$where_values[]  = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = 'l.message LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		// Build WHERE clause.
		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Validate orderby.
		$allowed_orderby = array( 'timestamp', 'level', 'feed_id', 'id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'timestamp';

		// Validate order.
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Calculate pagination.
		$per_page = absint( $args['per_page'] );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Get total count.
		$count_query = "SELECT COUNT(*) FROM $table_name l $where_sql";
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$total = $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$total = $wpdb->get_var( $count_query );
		}

		$result['total'] = absint( $total );
		$result['pages'] = ceil( $result['total'] / $per_page );

		// Get logs with feed name join.
		$query = "SELECT l.*, f.name as feed_name
			FROM $table_name l
			LEFT JOIN $feeds_table f ON l.feed_id = f.id
			$where_sql
			ORDER BY l.$orderby $order
			LIMIT %d OFFSET %d";

		$query_values   = array_merge( $where_values, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$logs = $wpdb->get_results( $wpdb->prepare( $query, $query_values ), ARRAY_A );

		// Decode context JSON.
		if ( $logs ) {
			foreach ( $logs as &$log ) {
				if ( ! empty( $log['context'] ) ) {
					$log['context'] = json_decode( $log['context'], true );
				} else {
					$log['context'] = array();
				}
			}
		}

		$result['logs'] = $logs ?: array();

		return $result;
	}

	/**
	 * Get log statistics for dashboard display.
	 *
	 * @return array Array with log statistics.
	 */
	public static function get_stats() {
		global $wpdb;

		$stats = array(
			'total'          => 0,
			'errors_24h'     => 0,
			'warnings_24h'   => 0,
			'info_24h'       => 0,
			'oldest_log'     => null,
			'newest_log'     => null,
		);

		if ( ! self::table_exists() ) {
			return $stats;
		}

		$table_name = self::get_table_name();

		// Total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['total'] = absint( $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" ) );

		// Counts by level in last 24 hours.
		$yesterday = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['errors_24h'] = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE level = %s AND timestamp >= %s",
					'error',
					$yesterday
				)
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['warnings_24h'] = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE level = %s AND timestamp >= %s",
					'warning',
					$yesterday
				)
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['info_24h'] = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE level = %s AND timestamp >= %s",
					'info',
					$yesterday
				)
			)
		);

		// Oldest and newest log timestamps.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['oldest_log'] = $wpdb->get_var( "SELECT MIN(timestamp) FROM $table_name" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats['newest_log'] = $wpdb->get_var( "SELECT MAX(timestamp) FROM $table_name" );

		return $stats;
	}

	/**
	 * Clear all logs.
	 *
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public static function clear_all() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $wpdb->query(
			$wpdb->prepare( 'TRUNCATE TABLE %i', $table_name )
		);

		return $result;
	}

	/**
	 * Cleanup old log entries.
	 *
	 * Removes entries older than RETENTION_DAYS and enforces MAX_ENTRIES limit.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_entries() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = self::get_table_name();
		$deleted    = 0;

		// Delete entries older than RETENTION_DAYS.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted_by_age = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE timestamp < %s",
				$cutoff_date
			)
		);

		if ( false !== $deleted_by_age ) {
			$deleted += $deleted_by_age;
		}

		// Enforce MAX_ENTRIES limit (delete oldest if over limit).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_count = absint( $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" ) );

		if ( $total_count > self::MAX_ENTRIES ) {
			$to_delete = $total_count - self::MAX_ENTRIES;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted_by_limit = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table_name ORDER BY timestamp ASC LIMIT %d",
					$to_delete
				)
			);

			if ( false !== $deleted_by_limit ) {
				$deleted += $deleted_by_limit;
			}
		}

		return $deleted;
	}

	/**
	 * Schedule the cleanup cron job.
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CLEANUP_CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cleanup cron job.
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CLEANUP_CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_CRON_HOOK );
		}
	}

	/**
	 * Drop the logs table.
	 *
	 * Used during uninstall when delete_data_on_uninstall is enabled.
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}

	/**
	 * Initialize the logger.
	 *
	 * Called during plugin initialization.
	 */
	public static function init() {
		// Get singleton instance to trigger constructor.
		self::get_instance();

		// Schedule cleanup if not already scheduled.
		self::schedule_cleanup();
	}
}
