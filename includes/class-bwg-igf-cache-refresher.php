<?php
/**
 * Smart Background Cache Refresher
 *
 * Feature #25: WP Cron should refresh cache intelligently - not during rate limits, with staggered timing.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache Refresher Class
 *
 * Handles intelligent background cache refresh for Instagram feeds.
 */
class BWG_IGF_Cache_Refresher {

	/**
	 * Cron hook name for cache refresh.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'bwg_igf_cache_refresh';

	/**
	 * Cron interval name for cache refresh (every 15 minutes).
	 *
	 * @var string
	 */
	const CRON_INTERVAL = 'bwg_igf_fifteen_minutes';

	/**
	 * Default stagger delay in seconds between feed refreshes.
	 * This prevents burst API calls.
	 *
	 * @var int
	 */
	const STAGGER_DELAY = 30;

	/**
	 * Maximum number of feeds to refresh per cron run.
	 * This limits API calls per run to prevent hitting rate limits.
	 *
	 * @var int
	 */
	const MAX_FEEDS_PER_RUN = 5;

	/**
	 * Time buffer before cache expires to trigger refresh (in seconds).
	 * Refresh cache 5 minutes before it expires to ensure continuity.
	 *
	 * @var int
	 */
	const REFRESH_BUFFER = 300;

	/**
	 * Initialize the cache refresher.
	 */
	public static function init() {
		// Register the custom cron interval.
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_interval' ) );

		// Register the cron hook.
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh_caches' ) );
	}

	/**
	 * Register custom cron interval for 15-minute runs.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public static function register_cron_interval( $schedules ) {
		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'bwg-instagram-feed' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the cache refresh cron job.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cache refresh cron job.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Check if the cache refresh cron is scheduled.
	 *
	 * @return bool True if scheduled.
	 */
	public static function is_scheduled() {
		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get the next scheduled run time.
	 *
	 * @return int|false Unix timestamp of next run, or false if not scheduled.
	 */
	public static function get_next_run() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Main cron callback - refresh caches intelligently.
	 *
	 * This method:
	 * 1. Skips if any account is rate limited
	 * 2. Gets feeds that need refresh (cache expiring soon)
	 * 3. Refreshes them with staggered timing
	 * 4. Limits the number of feeds refreshed per run
	 */
	public static function refresh_caches() {
		// Step 1: Check rate limit status.
		if ( self::is_rate_limited() ) {
			if ( class_exists( 'BWG_IGF_Logger' ) ) {
				BWG_IGF_Logger::warning( __( 'Cache refresh skipped - rate limited', 'bwg-instagram-feed' ) );
			}
			return;
		}

		// Step 2: Get feeds that need refresh.
		$feeds_to_refresh = self::get_feeds_needing_refresh();

		if ( empty( $feeds_to_refresh ) ) {
			// No feeds need refresh - nothing to do.
			return;
		}

		// Step 3: Limit the number of feeds to refresh per run.
		$feeds_to_refresh = array_slice( $feeds_to_refresh, 0, self::MAX_FEEDS_PER_RUN );

		if ( class_exists( 'BWG_IGF_Logger' ) ) {
			BWG_IGF_Logger::info(
				sprintf(
					/* translators: %d: number of feeds */
					__( 'Background cache refresh started for %d feeds', 'bwg-instagram-feed' ),
					count( $feeds_to_refresh )
				)
			);
		}

		// Step 4: Refresh feeds with staggered timing.
		$refreshed_count = 0;
		foreach ( $feeds_to_refresh as $feed ) {
			// Check rate limit again before each feed (in case we hit it during refresh).
			if ( self::is_rate_limited() ) {
				if ( class_exists( 'BWG_IGF_Logger' ) ) {
					BWG_IGF_Logger::warning(
						__( 'Cache refresh stopped - hit rate limit during refresh', 'bwg-instagram-feed' ),
						array( 'refreshed' => $refreshed_count )
					);
				}
				break;
			}

			// Refresh this feed's cache.
			$success = self::refresh_feed_cache( $feed );

			if ( $success ) {
				$refreshed_count++;
			}

			// Stagger the next refresh to avoid burst API calls.
			if ( $refreshed_count < count( $feeds_to_refresh ) ) {
				sleep( self::STAGGER_DELAY );
			}
		}

		if ( class_exists( 'BWG_IGF_Logger' ) ) {
			BWG_IGF_Logger::info(
				sprintf(
					/* translators: 1: refreshed count, 2: total feeds */
					__( 'Background cache refresh completed: %1$d/%2$d feeds refreshed', 'bwg-instagram-feed' ),
					$refreshed_count,
					count( $feeds_to_refresh )
				)
			);
		}
	}

	/**
	 * Check if any account is currently rate limited.
	 *
	 * @return bool True if rate limited.
	 */
	private static function is_rate_limited() {
		// Check if API Tracker class is available.
		if ( ! class_exists( 'BWG_IGF_API_Tracker' ) ) {
			return false;
		}

		// Check global rate limit status.
		return BWG_IGF_API_Tracker::is_any_account_rate_limited();
	}

	/**
	 * Get feeds that need their cache refreshed.
	 *
	 * Returns feeds where:
	 * - Feed status is 'active'
	 * - Cache is expiring within REFRESH_BUFFER seconds (or already expired)
	 *
	 * @return array Array of feed objects.
	 */
	public static function get_feeds_needing_refresh() {
		global $wpdb;

		$feeds_table = $wpdb->prefix . 'bwg_igf_feeds';
		$cache_table = $wpdb->prefix . 'bwg_igf_cache';

		// Get the time threshold for cache that's about to expire.
		$threshold_time = gmdate( 'Y-m-d H:i:s', time() + self::REFRESH_BUFFER );

		// Query for active feeds where cache is expiring soon or doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cron job, caching not needed.
		$feeds = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.*
				FROM {$feeds_table} f
				LEFT JOIN (
					SELECT feed_id, MAX(expires_at) as latest_expiry
					FROM {$cache_table}
					GROUP BY feed_id
				) c ON f.id = c.feed_id
				WHERE f.status = %s
				AND (c.latest_expiry IS NULL OR c.latest_expiry <= %s)
				ORDER BY c.latest_expiry ASC",
				'active',
				$threshold_time
			)
		);

		return $feeds ?: array();
	}

	/**
	 * Refresh the cache for a single feed.
	 *
	 * @param object $feed Feed object from database.
	 * @return bool True on success, false on failure.
	 */
	private static function refresh_feed_cache( $feed ) {
		// Load the fetcher class if needed.
		if ( ! class_exists( 'BWG_IGF_Instagram_Fetcher' ) ) {
			require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-fetcher.php';
		}

		// Load the API class if needed (for connected feeds).
		if ( ! class_exists( 'BWG_IGF_Instagram_API' ) ) {
			require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-instagram-api.php';
		}

		$posts = array();

		// Fetch data based on feed type.
		if ( 'connected' === $feed->feed_type && ! empty( $feed->connected_account_id ) ) {
			// Connected feed - use Instagram Graph API.
			$posts = self::fetch_connected_feed( $feed );
		} elseif ( 'public' === $feed->feed_type && ! empty( $feed->instagram_usernames ) ) {
			// Public feed - fetch from public profile.
			$posts = self::fetch_public_feed( $feed );
		}

		// Store the cache if we got posts.
		if ( ! empty( $posts ) ) {
			$account_id = ! empty( $feed->connected_account_id ) ? $feed->connected_account_id : 0;
			BWG_IGF_Instagram_Fetcher::store_cache( $feed->id, $posts, $feed->cache_duration, $account_id );
			return true;
		}

		return false;
	}

	/**
	 * Fetch posts for a connected Instagram account feed.
	 *
	 * @param object $feed Feed object.
	 * @return array Array of posts.
	 */
	private static function fetch_connected_feed( $feed ) {
		global $wpdb;

		// Get the connected account.
		$accounts_table = $wpdb->prefix . 'bwg_igf_accounts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$account = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$accounts_table} WHERE id = %d AND status = %s",
				$feed->connected_account_id,
				'active'
			)
		);

		if ( ! $account ) {
			return array();
		}

		// Use the Instagram API to fetch posts.
		$api = new BWG_IGF_Instagram_API();
		$result = $api->fetch_connected_posts( $account, $feed->post_count );

		if ( is_wp_error( $result ) ) {
			if ( class_exists( 'BWG_IGF_Logger' ) ) {
				BWG_IGF_Logger::error(
					sprintf(
						/* translators: %s: error message */
						__( 'Failed to fetch connected feed: %s', 'bwg-instagram-feed' ),
						$result->get_error_message()
					),
					array(
						'feed_id'    => $feed->id,
						'account_id' => $feed->connected_account_id,
						'error_code' => $result->get_error_code(),
					)
				);
			}
			return array();
		}

		return $result;
	}

	/**
	 * Fetch posts for a public Instagram feed.
	 *
	 * @param object $feed Feed object.
	 * @return array Array of posts.
	 */
	private static function fetch_public_feed( $feed ) {
		// Use the fetcher class to get public posts.
		$posts = BWG_IGF_Instagram_Fetcher::fetch_and_cache( $feed );
		return $posts ?: array();
	}

	/**
	 * Get cache refresh statistics for admin display.
	 *
	 * @return array Statistics array.
	 */
	public static function get_stats() {
		global $wpdb;

		$feeds_table = $wpdb->prefix . 'bwg_igf_feeds';
		$cache_table = $wpdb->prefix . 'bwg_igf_cache';

		// Total active feeds.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_feeds = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$feeds_table} WHERE status = %s",
				'active'
			)
		);

		// Feeds with valid cache.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$feeds_with_cache = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT f.id)
				FROM {$feeds_table} f
				INNER JOIN {$cache_table} c ON f.id = c.feed_id
				WHERE f.status = %s AND c.expires_at > NOW()",
				'active'
			)
		);

		// Feeds needing refresh.
		$feeds_needing_refresh = count( self::get_feeds_needing_refresh() );

		return array(
			'is_scheduled'          => self::is_scheduled(),
			'next_run'              => self::get_next_run(),
			'total_active_feeds'    => intval( $total_feeds ),
			'feeds_with_cache'      => intval( $feeds_with_cache ),
			'feeds_needing_refresh' => $feeds_needing_refresh,
			'is_rate_limited'       => self::is_rate_limited(),
			'max_feeds_per_run'     => self::MAX_FEEDS_PER_RUN,
			'stagger_delay'         => self::STAGGER_DELAY,
		);
	}

	/**
	 * Manually trigger a cache refresh (for testing or admin action).
	 *
	 * @return array Results of the refresh operation.
	 */
	public static function trigger_manual_refresh() {
		$start_time = microtime( true );

		// Capture any errors.
		$feeds_before = count( self::get_feeds_needing_refresh() );

		// Run the refresh.
		self::refresh_caches();

		$feeds_after = count( self::get_feeds_needing_refresh() );
		$duration = microtime( true ) - $start_time;

		return array(
			'feeds_refreshed'   => $feeds_before - $feeds_after,
			'feeds_remaining'   => $feeds_after,
			'duration_seconds'  => round( $duration, 2 ),
			'was_rate_limited'  => self::is_rate_limited(),
		);
	}
}
