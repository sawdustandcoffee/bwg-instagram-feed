<?php
/**
 * API Call Tracker
 *
 * Tracks API calls per account for rate limit monitoring.
 * Stores timestamp, endpoint, response status, and rate limit info.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * API Tracker Class
 */
class BWG_IGF_API_Tracker {

    /**
     * Table name for API calls.
     *
     * @var string
     */
    private static $table_name;

    /**
     * Days to keep old entries before cleanup.
     *
     * @var int
     */
    const RETENTION_DAYS = 7;

    /**
     * Initial backoff delay in seconds (1 minute).
     * Feature #13: Exponential backoff on rate limit detection.
     *
     * @var int
     */
    const INITIAL_BACKOFF_SECONDS = 60;

    /**
     * Maximum backoff delay in seconds (1 hour).
     * Feature #13: Cap the maximum backoff time.
     *
     * @var int
     */
    const MAX_BACKOFF_SECONDS = 3600;

    /**
     * Backoff multiplier for exponential increase.
     * Feature #13: Each subsequent rate limit doubles the wait time.
     *
     * @var int
     */
    const BACKOFF_MULTIPLIER = 2;

    /**
     * Extended cache duration multiplier during rate limit periods.
     * Feature #21: Auto-extend cache duration during rate limit.
     * Original duration is multiplied by this value when rate limited.
     *
     * @var int
     */
    const RATE_LIMIT_CACHE_MULTIPLIER = 6;

    /**
     * Maximum extended cache duration in seconds (24 hours).
     * Feature #21: Cap the maximum extended cache duration.
     *
     * @var int
     */
    const MAX_EXTENDED_CACHE_DURATION = 86400;

    /**
     * Initialize the tracker.
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'bwg_igf_api_calls';

        // Schedule cleanup cron job.
        add_action( 'bwg_igf_api_tracker_cleanup', array( __CLASS__, 'cleanup_old_entries' ) );

        // Schedule the cleanup if not already scheduled.
        if ( ! wp_next_scheduled( 'bwg_igf_api_tracker_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'bwg_igf_api_tracker_cleanup' );
        }
    }

    /**
     * Get the table name.
     *
     * @return string The table name with prefix.
     */
    public static function get_table_name() {
        global $wpdb;
        if ( empty( self::$table_name ) ) {
            self::$table_name = $wpdb->prefix . 'bwg_igf_api_calls';
        }
        return self::$table_name;
    }

    /**
     * Create the API calls table.
     *
     * @return bool True if table created successfully.
     */
    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            endpoint varchar(255) NOT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            response_code int(11) NOT NULL DEFAULT 0,
            rate_limit_remaining int(11) DEFAULT NULL,
            rate_limit_reset datetime DEFAULT NULL,
            error_code varchar(100) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY account_id (account_id),
            KEY timestamp (timestamp),
            KEY endpoint (endpoint(50))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Check if table was created.
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ) );

        return ( $table_exists === $table_name );
    }

    /**
     * Log an API call.
     *
     * @param int    $account_id         Account ID (0 for public API calls).
     * @param string $endpoint           The API endpoint called.
     * @param int    $response_code      HTTP response code.
     * @param int    $rate_limit_remaining Optional. Remaining rate limit if available.
     * @param string $rate_limit_reset   Optional. Rate limit reset time if available.
     * @param string $error_code         Optional. Error code if the call failed.
     * @return int|false The insert ID on success, false on failure.
     */
    public static function log_call( $account_id, $endpoint, $response_code, $rate_limit_remaining = null, $rate_limit_reset = null, $error_code = null ) {
        global $wpdb;

        $table_name = self::get_table_name();

        // Ensure table exists.
        if ( ! self::table_exists() ) {
            self::create_table();
        }

        $data = array(
            'account_id'    => absint( $account_id ),
            'endpoint'      => sanitize_text_field( $endpoint ),
            'timestamp'     => current_time( 'mysql' ),
            'response_code' => absint( $response_code ),
        );

        $format = array( '%d', '%s', '%s', '%d' );

        if ( null !== $rate_limit_remaining ) {
            $data['rate_limit_remaining'] = absint( $rate_limit_remaining );
            $format[] = '%d';
        }

        if ( null !== $rate_limit_reset ) {
            $data['rate_limit_reset'] = sanitize_text_field( $rate_limit_reset );
            $format[] = '%s';
        }

        if ( null !== $error_code ) {
            $data['error_code'] = sanitize_text_field( $error_code );
            $format[] = '%s';
        }

        $result = $wpdb->insert( $table_name, $data, $format );

        return ( false !== $result ) ? $wpdb->insert_id : false;
    }

    /**
     * Get API call history for an account.
     *
     * @param int $account_id Account ID.
     * @param int $limit      Maximum number of entries to return.
     * @return array Array of API call records.
     */
    public static function get_history( $account_id, $limit = 100 ) {
        global $wpdb;

        $table_name = self::get_table_name();

        if ( ! self::table_exists() ) {
            return array();
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE account_id = %d ORDER BY timestamp DESC LIMIT %d",
                $account_id,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get the most recent API call for an account.
     *
     * @param int $account_id Account ID.
     * @return array|null The most recent API call record or null.
     */
    public static function get_latest( $account_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        if ( ! self::table_exists() ) {
            return null;
        }

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE account_id = %d ORDER BY timestamp DESC LIMIT 1",
                $account_id
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
     * Get API call count for an account within a time period.
     *
     * @param int    $account_id Account ID.
     * @param string $since      Time period (e.g., '1 hour', '24 hours').
     * @return int Number of API calls.
     */
    public static function get_call_count( $account_id, $since = '1 hour' ) {
        global $wpdb;

        $table_name = self::get_table_name();

        if ( ! self::table_exists() ) {
            return 0;
        }

        $since_time = date( 'Y-m-d H:i:s', strtotime( '-' . $since ) );

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE account_id = %d AND timestamp >= %s",
                $account_id,
                $since_time
            )
        );

        return absint( $count );
    }

    /**
     * Check if an account is currently rate limited.
     *
     * @param int $account_id Account ID.
     * @return array Array with 'limited' (bool) and 'reset_at' (string|null).
     */
    public static function is_rate_limited( $account_id ) {
        global $wpdb;

        $table_name = self::get_table_name();
        $result = array(
            'limited'  => false,
            'reset_at' => null,
        );

        if ( ! self::table_exists() ) {
            return $result;
        }

        // Check for recent rate limit errors (429 or error codes 4, 17, 32).
        $latest = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                WHERE account_id = %d
                AND (response_code = 429 OR error_code IN ('rate_limited', '4', '17', '32'))
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY timestamp DESC
                LIMIT 1",
                $account_id
            ),
            ARRAY_A
        );

        if ( $latest ) {
            $result['limited'] = true;
            $result['reset_at'] = $latest['rate_limit_reset'] ?? null;
        }

        return $result;
    }

    /**
     * Get the latest rate limit info for an account.
     *
     * @param int $account_id Account ID.
     * @return array Array with rate limit status info.
     */
    public static function get_rate_limit_status( $account_id ) {
        global $wpdb;

        $table_name = self::get_table_name();

        $status = array(
            'remaining'      => null,
            'reset_at'       => null,
            'is_limited'     => false,
            'last_call'      => null,
            'calls_last_hour' => 0,
        );

        if ( ! self::table_exists() ) {
            return $status;
        }

        // Get the latest call with rate limit info.
        $latest = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                WHERE account_id = %d
                AND rate_limit_remaining IS NOT NULL
                ORDER BY timestamp DESC
                LIMIT 1",
                $account_id
            ),
            ARRAY_A
        );

        if ( $latest ) {
            $status['remaining'] = intval( $latest['rate_limit_remaining'] );
            $status['reset_at'] = $latest['rate_limit_reset'];
            $status['last_call'] = $latest['timestamp'];
        }

        // Check if rate limited.
        $rate_limit_check = self::is_rate_limited( $account_id );
        $status['is_limited'] = $rate_limit_check['limited'];

        // Get call count for last hour.
        $status['calls_last_hour'] = self::get_call_count( $account_id, '1 hour' );

        return $status;
    }

    /**
     * Cleanup old entries.
     *
     * Removes API call records older than RETENTION_DAYS.
     *
     * @return int Number of rows deleted.
     */
    public static function cleanup_old_entries() {
        global $wpdb;

        $table_name = self::get_table_name();

        if ( ! self::table_exists() ) {
            return 0;
        }

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                $cutoff_date
            )
        );

        if ( false === $deleted ) {
            error_log( 'BWG Instagram Feed: Failed to cleanup old API call entries' );
            return 0;
        }

        return $deleted;
    }

    /**
     * Check if the API calls table exists.
     *
     * @return bool True if table exists.
     */
    public static function table_exists() {
        global $wpdb;

        $table_name = self::get_table_name();

        $result = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ) );

        return ( $result === $table_name );
    }

    /**
     * Unschedule the cleanup cron job.
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled( 'bwg_igf_api_tracker_cleanup' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'bwg_igf_api_tracker_cleanup' );
        }
    }

    /**
     * Drop the API calls table.
     *
     * Used during uninstall when delete_data_on_uninstall is enabled.
     */
    public static function drop_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Get the backoff state for an account.
     *
     * Feature #13: Exponential backoff on rate limit detection.
     * Returns the current backoff state including when the next retry is allowed.
     *
     * @param int $account_id Account ID.
     * @return array Array with backoff state: 'in_backoff', 'retry_after', 'current_delay', 'retry_count'.
     */
    public static function get_backoff_state( $account_id ) {
        $transient_key = 'bwg_igf_backoff_' . absint( $account_id );
        $state = get_transient( $transient_key );

        if ( false === $state || ! is_array( $state ) ) {
            return array(
                'in_backoff'    => false,
                'retry_after'   => null,
                'current_delay' => 0,
                'retry_count'   => 0,
            );
        }

        // Check if the backoff period has expired.
        $now = time();
        if ( isset( $state['retry_after'] ) && $now >= $state['retry_after'] ) {
            // Backoff period expired, but don't clear yet - let the next API call do that.
            // This allows us to detect recovery.
            return array(
                'in_backoff'    => false, // Can retry now.
                'retry_after'   => $state['retry_after'],
                'current_delay' => $state['current_delay'] ?? self::INITIAL_BACKOFF_SECONDS,
                'retry_count'   => $state['retry_count'] ?? 1,
            );
        }

        return array(
            'in_backoff'    => true,
            'retry_after'   => $state['retry_after'],
            'current_delay' => $state['current_delay'] ?? self::INITIAL_BACKOFF_SECONDS,
            'retry_count'   => $state['retry_count'] ?? 1,
        );
    }

    /**
     * Check if an account should wait before making API calls.
     *
     * Feature #13: Returns true if the account is in a backoff period and should not make API calls.
     *
     * @param int $account_id Account ID.
     * @return bool True if in backoff period, false if ready to retry.
     */
    public static function should_backoff( $account_id ) {
        $state = self::get_backoff_state( $account_id );
        return $state['in_backoff'];
    }

    /**
     * Get the number of seconds until retry is allowed.
     *
     * Feature #13: Returns remaining seconds in backoff, or 0 if ready to retry.
     *
     * @param int $account_id Account ID.
     * @return int Seconds until retry allowed.
     */
    public static function get_backoff_remaining( $account_id ) {
        $state = self::get_backoff_state( $account_id );

        if ( ! $state['in_backoff'] || empty( $state['retry_after'] ) ) {
            return 0;
        }

        $remaining = $state['retry_after'] - time();
        return max( 0, $remaining );
    }

    /**
     * Record a rate limit hit and update backoff state.
     *
     * Feature #13: Implements exponential backoff - each rate limit doubles the wait time.
     *
     * @param int $account_id Account ID.
     * @return array The new backoff state.
     */
    public static function record_rate_limit( $account_id ) {
        $account_id = absint( $account_id );
        $transient_key = 'bwg_igf_backoff_' . $account_id;

        // Get current state to calculate next delay.
        $current_state = get_transient( $transient_key );

        if ( false === $current_state || ! is_array( $current_state ) ) {
            // First rate limit - use initial backoff delay.
            $new_delay = self::INITIAL_BACKOFF_SECONDS;
            $retry_count = 1;
        } else {
            // Exponential backoff - double the previous delay.
            $previous_delay = $current_state['current_delay'] ?? self::INITIAL_BACKOFF_SECONDS;
            $new_delay = min( $previous_delay * self::BACKOFF_MULTIPLIER, self::MAX_BACKOFF_SECONDS );
            $retry_count = ( $current_state['retry_count'] ?? 0 ) + 1;
        }

        $retry_after = time() + $new_delay;

        $new_state = array(
            'account_id'    => $account_id,
            'current_delay' => $new_delay,
            'retry_after'   => $retry_after,
            'retry_count'   => $retry_count,
            'recorded_at'   => time(),
        );

        // Store with expiration slightly longer than the backoff to allow for detection.
        $transient_expiration = $new_delay + 300; // Add 5 minutes buffer.
        set_transient( $transient_key, $new_state, $transient_expiration );

        return array(
            'in_backoff'    => true,
            'retry_after'   => $retry_after,
            'current_delay' => $new_delay,
            'retry_count'   => $retry_count,
        );
    }

    /**
     * Clear backoff state after successful API call (recovery).
     *
     * Feature #13: Resets backoff when rate limits have cleared.
     * Feature #21: Also clears cache extension state when all accounts recover.
     *
     * @param int $account_id Account ID.
     * @return bool True if state was cleared.
     */
    public static function clear_backoff( $account_id ) {
        $transient_key = 'bwg_igf_backoff_' . absint( $account_id );
        $result = delete_transient( $transient_key );

        // Feature #21: Check if any account is still rate limited.
        // If not, clear the cache extension state.
        if ( ! self::is_any_account_rate_limited() ) {
            self::clear_cache_extension_state();
        }

        return $result;
    }

    /**
     * Get a human-readable backoff status message.
     *
     * Feature #13: Returns a message for display in admin UI.
     *
     * @param int $account_id Account ID.
     * @return string Status message or empty string if not in backoff.
     */
    public static function get_backoff_message( $account_id ) {
        $state = self::get_backoff_state( $account_id );

        if ( ! $state['in_backoff'] ) {
            return '';
        }

        $remaining = self::get_backoff_remaining( $account_id );

        if ( $remaining <= 0 ) {
            return '';
        }

        // Format the remaining time.
        if ( $remaining >= 3600 ) {
            $time_str = sprintf(
                /* translators: %d: number of minutes */
                _n( '%d hour', '%d hours', floor( $remaining / 3600 ), 'bwg-instagram-feed' ),
                floor( $remaining / 3600 )
            );
        } elseif ( $remaining >= 60 ) {
            $time_str = sprintf(
                /* translators: %d: number of minutes */
                _n( '%d minute', '%d minutes', floor( $remaining / 60 ), 'bwg-instagram-feed' ),
                floor( $remaining / 60 )
            );
        } else {
            $time_str = sprintf(
                /* translators: %d: number of seconds */
                _n( '%d second', '%d seconds', $remaining, 'bwg-instagram-feed' ),
                $remaining
            );
        }

        return sprintf(
            /* translators: %s: time remaining */
            __( 'Rate limited. Retry in %s.', 'bwg-instagram-feed' ),
            $time_str
        );
    }

    /**
     * Get detailed backoff info for debugging/display.
     *
     * Feature #13: Returns comprehensive backoff information.
     *
     * @param int $account_id Account ID.
     * @return array Detailed backoff information.
     */
    public static function get_backoff_info( $account_id ) {
        $state = self::get_backoff_state( $account_id );

        return array(
            'in_backoff'      => $state['in_backoff'],
            'retry_after'     => $state['retry_after'] ? date( 'Y-m-d H:i:s', $state['retry_after'] ) : null,
            'current_delay'   => $state['current_delay'],
            'retry_count'     => $state['retry_count'],
            'remaining_secs'  => self::get_backoff_remaining( $account_id ),
            'message'         => self::get_backoff_message( $account_id ),
            'max_delay'       => self::MAX_BACKOFF_SECONDS,
            'initial_delay'   => self::INITIAL_BACKOFF_SECONDS,
        );
    }

    /**
     * Parse rate limit headers from HTTP response.
     *
     * @param array $response WP_HTTP response array.
     * @return array Array with 'remaining' and 'reset' keys.
     */
    public static function parse_rate_limit_headers( $response ) {
        $result = array(
            'remaining' => null,
            'reset'     => null,
        );

        if ( is_wp_error( $response ) ) {
            return $result;
        }

        $headers = wp_remote_retrieve_headers( $response );

        // Check for common rate limit headers.
        $header_names = array(
            'remaining' => array(
                'x-ratelimit-remaining',
                'x-rate-limit-remaining',
                'ratelimit-remaining',
            ),
            'reset' => array(
                'x-ratelimit-reset',
                'x-rate-limit-reset',
                'ratelimit-reset',
            ),
        );

        foreach ( $header_names['remaining'] as $header ) {
            if ( isset( $headers[ $header ] ) ) {
                $result['remaining'] = intval( $headers[ $header ] );
                break;
            }
        }

        foreach ( $header_names['reset'] as $header ) {
            if ( isset( $headers[ $header ] ) ) {
                $reset_value = $headers[ $header ];
                // Handle both Unix timestamp and datetime string.
                if ( is_numeric( $reset_value ) ) {
                    $result['reset'] = date( 'Y-m-d H:i:s', intval( $reset_value ) );
                } else {
                    $result['reset'] = sanitize_text_field( $reset_value );
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Get the effective cache duration, extended during rate limit periods.
     *
     * Feature #21: Auto-extend cache duration during rate limit.
     * When rate limited, this returns the original duration multiplied by RATE_LIMIT_CACHE_MULTIPLIER,
     * capped at MAX_EXTENDED_CACHE_DURATION.
     *
     * @param int $original_duration Original cache duration in seconds.
     * @param int $account_id        Account ID to check for rate limiting.
     * @return int Effective cache duration in seconds.
     */
    public static function get_effective_cache_duration( $original_duration, $account_id = 0 ) {
        // Check if any account is currently rate limited.
        $is_rate_limited = false;

        if ( $account_id > 0 ) {
            // Check specific account.
            $rate_limit_check = self::is_rate_limited( $account_id );
            $is_rate_limited = $rate_limit_check['limited'] || self::should_backoff( $account_id );
        } else {
            // Check global rate limit state (any account).
            $is_rate_limited = self::is_any_account_rate_limited();
        }

        if ( ! $is_rate_limited ) {
            return intval( $original_duration );
        }

        // Calculate extended duration.
        $extended_duration = $original_duration * self::RATE_LIMIT_CACHE_MULTIPLIER;

        // Cap at maximum extended duration.
        $extended_duration = min( $extended_duration, self::MAX_EXTENDED_CACHE_DURATION );

        // Store the extension state for tracking.
        self::set_cache_extension_state( true, $original_duration, $extended_duration );

        return intval( $extended_duration );
    }

    /**
     * Check if any connected account is currently rate limited.
     *
     * Feature #21: Used to determine if cache should be extended.
     *
     * @return bool True if any account is rate limited.
     */
    public static function is_any_account_rate_limited() {
        global $wpdb;

        // Check for any backoff transients.
        $transient_prefix = '_transient_bwg_igf_backoff_';
        $has_backoff = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( $transient_prefix ) . '%'
            )
        );

        if ( intval( $has_backoff ) > 0 ) {
            return true;
        }

        // Also check the API calls table for recent rate limit errors.
        $table_name = self::get_table_name();

        if ( ! self::table_exists() ) {
            return false;
        }

        $rate_limited = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name
            WHERE (response_code = 429 OR error_code IN ('rate_limited', '4', '17', '32'))
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        return intval( $rate_limited ) > 0;
    }

    /**
     * Set the cache extension state for tracking.
     *
     * Feature #21: Stores information about cache extension for admin display.
     *
     * @param bool $is_extended       Whether cache is currently extended.
     * @param int  $original_duration Original cache duration in seconds.
     * @param int  $extended_duration Extended cache duration in seconds.
     */
    public static function set_cache_extension_state( $is_extended, $original_duration = 0, $extended_duration = 0 ) {
        $state = array(
            'is_extended'       => $is_extended,
            'original_duration' => $original_duration,
            'extended_duration' => $extended_duration,
            'extended_at'       => time(),
        );

        // Store for 2 hours (longer than typical rate limit period).
        set_transient( 'bwg_igf_cache_extension_state', $state, 7200 );
    }

    /**
     * Get the cache extension state.
     *
     * Feature #21: Returns information about current cache extension status.
     *
     * @return array Cache extension state.
     */
    public static function get_cache_extension_state() {
        $state = get_transient( 'bwg_igf_cache_extension_state' );

        if ( false === $state || ! is_array( $state ) ) {
            return array(
                'is_extended'       => false,
                'original_duration' => 0,
                'extended_duration' => 0,
                'extended_at'       => null,
            );
        }

        // Check if the rate limit has cleared.
        if ( $state['is_extended'] && ! self::is_any_account_rate_limited() ) {
            // Rate limit has cleared, mark as no longer extended.
            self::clear_cache_extension_state();
            return array(
                'is_extended'       => false,
                'original_duration' => $state['original_duration'],
                'extended_duration' => 0,
                'extended_at'       => null,
            );
        }

        return $state;
    }

    /**
     * Clear the cache extension state.
     *
     * Feature #21: Called when rate limits have cleared.
     */
    public static function clear_cache_extension_state() {
        delete_transient( 'bwg_igf_cache_extension_state' );
    }

    /**
     * Get human-readable cache extension info.
     *
     * Feature #21: Returns a formatted message about cache extension status.
     *
     * @return string Status message or empty string if not extended.
     */
    public static function get_cache_extension_message() {
        $state = self::get_cache_extension_state();

        if ( ! $state['is_extended'] ) {
            return '';
        }

        // Format durations.
        $original_formatted = self::format_duration( $state['original_duration'] );
        $extended_formatted = self::format_duration( $state['extended_duration'] );

        return sprintf(
            /* translators: 1: original duration, 2: extended duration */
            __( 'Cache duration temporarily extended from %1$s to %2$s due to rate limiting.', 'bwg-instagram-feed' ),
            $original_formatted,
            $extended_formatted
        );
    }

    /**
     * Format a duration in seconds to a human-readable string.
     *
     * @param int $seconds Duration in seconds.
     * @return string Formatted duration string.
     */
    private static function format_duration( $seconds ) {
        if ( $seconds >= 86400 ) {
            $hours = floor( $seconds / 86400 );
            return sprintf(
                /* translators: %d: number of days */
                _n( '%d day', '%d days', $hours, 'bwg-instagram-feed' ),
                $hours
            );
        } elseif ( $seconds >= 3600 ) {
            $hours = floor( $seconds / 3600 );
            return sprintf(
                /* translators: %d: number of hours */
                _n( '%d hour', '%d hours', $hours, 'bwg-instagram-feed' ),
                $hours
            );
        } elseif ( $seconds >= 60 ) {
            $minutes = floor( $seconds / 60 );
            return sprintf(
                /* translators: %d: number of minutes */
                _n( '%d minute', '%d minutes', $minutes, 'bwg-instagram-feed' ),
                $minutes
            );
        } else {
            return sprintf(
                /* translators: %d: number of seconds */
                _n( '%d second', '%d seconds', $seconds, 'bwg-instagram-feed' ),
                $seconds
            );
        }
    }
}
