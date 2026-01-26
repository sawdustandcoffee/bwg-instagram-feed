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
}
