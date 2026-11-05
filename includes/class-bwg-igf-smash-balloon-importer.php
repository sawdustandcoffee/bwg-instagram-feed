<?php
/**
 * Smash Balloon Instagram Feed account importer.
 *
 * Reads connected accounts left behind by Smash Balloon's Instagram Feed
 * plugin (free or Pro) and copies the ones that are compatible with this
 * plugin's API (Instagram API with Instagram Login, graph.instagram.com) into
 * our accounts table, so a site switching plugins does not have to go through
 * the Instagram authorization flow again.
 *
 * Smash Balloon stores accounts in {prefix}sbi_sources. Tokens are encrypted
 * with AES-256-CTR using the site's LOGGED_IN_KEY / LOGGED_IN_SALT (or
 * SBI_ENCRYPTION_KEY / SBI_ENCRYPTION_SALT when defined), so any plugin on the
 * same site can decrypt them. Older tokens may instead be stored in plaintext
 * with a junk marker spliced in.
 *
 * Only tokens issued through Instagram Login (they start with "IG") work on
 * graph.instagram.com. Tokens issued through Facebook Login (start with "EA")
 * only work on graph.facebook.com and cannot be imported.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BWG_IGF_Smash_Balloon_Importer
 */
class BWG_IGF_Smash_Balloon_Importer {

    /**
     * Junk marker Smash Balloon splices into some stored tokens.
     */
    const SBI_TOKEN_MARKER = '634hgdf83hjdj2';

    /**
     * Fallback token lifetime when the source row has no usable expiry.
     * Long-lived Instagram tokens last 60 days; assume the conservative case.
     */
    const FALLBACK_LIFETIME = 30 * DAY_IN_SECONDS;

    /**
     * Candidate status: token is Instagram Login and can be imported.
     */
    const STATUS_IMPORTABLE = 'importable';

    /**
     * Candidate status: account is already in our accounts table.
     */
    const STATUS_ALREADY_CONNECTED = 'already_connected';

    /**
     * Candidate status: token came from Facebook Login; not usable here.
     */
    const STATUS_FACEBOOK_LOGIN = 'facebook_login';

    /**
     * Candidate status: token could not be decrypted or is empty.
     */
    const STATUS_UNREADABLE = 'unreadable';

    /**
     * Name of the Smash Balloon sources table.
     *
     * @return string
     */
    public static function get_source_table() {
        global $wpdb;
        return $wpdb->prefix . 'sbi_sources';
    }

    /**
     * Whether Smash Balloon data is present on this site.
     *
     * @return bool
     */
    public static function is_available() {
        global $wpdb;
        $table = self::get_source_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * List Smash Balloon accounts with an import status for each.
     *
     * Tokens are never returned; only enough to render the list and decide.
     *
     * @return array[] Each: source_id, username, account_id, account_type, connect_type, expires, status, status_label.
     */
    public static function get_candidates() {
        global $wpdb;

        if ( ! self::is_available() ) {
            return array();
        }

        $table = self::get_source_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( "SELECT id, account_id, account_type, access_token, username, expires, connect_type FROM `{$table}` ORDER BY id ASC" );

        if ( empty( $rows ) ) {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing_ids = $wpdb->get_col( "SELECT instagram_user_id FROM {$wpdb->prefix}bwg_igf_accounts" );
        $existing_ids = array_map( 'strval', (array) $existing_ids );

        $candidates = array();
        foreach ( $rows as $row ) {
            $token  = self::extract_token( $row->access_token );
            $status = self::classify( $token, $row->account_id, $existing_ids );

            $candidates[] = array(
                'source_id'    => (int) $row->id,
                'username'     => (string) $row->username,
                'account_id'   => (string) $row->account_id,
                'account_type' => (string) $row->account_type,
                'connect_type' => (string) $row->connect_type,
                'expires'      => (string) $row->expires,
                'status'       => $status,
                'status_label' => self::status_label( $status ),
            );
        }

        return $candidates;
    }

    /**
     * Import the given Smash Balloon source rows into our accounts table.
     *
     * Each token is verified against Instagram (/me) before it is stored, so a
     * dead or revoked token is reported rather than imported.
     *
     * @param int[] $source_ids IDs from the sbi_sources table.
     * @return array {
     *     @type array $imported Usernames imported.
     *     @type array $skipped  username => reason for rows not imported.
     * }
     */
    public static function import( $source_ids ) {
        global $wpdb;

        $result = array(
            'imported' => array(),
            'skipped'  => array(),
        );

        $source_ids = array_filter( array_map( 'absint', (array) $source_ids ) );
        if ( empty( $source_ids ) || ! self::is_available() ) {
            return $result;
        }

        if ( ! class_exists( 'BWG_IGF_Encryption' ) ) {
            require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-encryption.php';
        }

        $table        = self::get_source_table();
        $placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, account_id, account_type, access_token, username, expires, connect_type FROM `{$table}` WHERE id IN ({$placeholders})", $source_ids ) );

        foreach ( $rows as $row ) {
            $label = '' !== $row->username ? $row->username : ( 'source #' . $row->id );
            $token = self::extract_token( $row->access_token );

            if ( '' === $token ) {
                $result['skipped'][ $label ] = __( 'Token could not be read.', 'bwg-instagram-feed' );
                continue;
            }

            if ( 0 === strpos( $token, 'EA' ) ) {
                $result['skipped'][ $label ] = __( 'Connected through Facebook Login; reconnect with the Connect button instead.', 'bwg-instagram-feed' );
                continue;
            }

            // Verify the token still works and get authoritative account details.
            $me = self::fetch_me( $token );
            if ( is_wp_error( $me ) ) {
                $result['skipped'][ $label ] = $me->get_error_message();
                self::log( 'warning', sprintf( 'Smash Balloon import: token for @%s rejected by Instagram: %s', $label, $me->get_error_message() ) );
                continue;
            }

            $instagram_user_id = ! empty( $me['user_id'] ) ? $me['user_id'] : ( ! empty( $me['id'] ) ? $me['id'] : $row->account_id );
            $instagram_user_id = (int) $instagram_user_id;
            $username          = ! empty( $me['username'] ) ? $me['username'] : $row->username;
            $account_type      = self::normalize_account_type( isset( $me['account_type'] ) ? $me['account_type'] : $row->account_type );

            $expires_at = self::resolve_expiry( $row->expires );

            $encrypted_token = BWG_IGF_Encryption::encrypt( $token );
            if ( empty( $encrypted_token ) ) {
                $result['skipped'][ $label ] = __( 'Could not encrypt the token for storage.', 'bwg-instagram-feed' );
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}bwg_igf_accounts WHERE instagram_user_id = %d",
                    $instagram_user_id
                )
            );

            if ( $existing ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $ok = $wpdb->update(
                    $wpdb->prefix . 'bwg_igf_accounts',
                    array(
                        'username'       => $username,
                        'access_token'   => $encrypted_token,
                        'token_type'     => 'bearer',
                        'expires_at'     => $expires_at,
                        'account_type'   => $account_type,
                        'last_refreshed' => current_time( 'mysql' ),
                        'status'         => 'active',
                    ),
                    array( 'instagram_user_id' => $instagram_user_id ),
                    array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                    array( '%d' )
                );
                $account_id = (int) $existing;
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $ok = $wpdb->insert(
                    $wpdb->prefix . 'bwg_igf_accounts',
                    array(
                        'instagram_user_id' => $instagram_user_id,
                        'username'          => $username,
                        'access_token'      => $encrypted_token,
                        'token_type'        => 'bearer',
                        'expires_at'        => $expires_at,
                        'account_type'      => $account_type,
                        'connected_at'      => current_time( 'mysql' ),
                        'status'            => 'active',
                    ),
                    array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );
                $account_id = (int) $wpdb->insert_id;
            }

            if ( false === $ok ) {
                $result['skipped'][ $label ] = __( 'Database write failed.', 'bwg-instagram-feed' );
                continue;
            }

            $result['imported'][] = $username;
            self::log(
                'info',
                sprintf( 'Imported Instagram account @%s from Smash Balloon (token expires %s).', $username, $expires_at ),
                array( 'account_id' => $account_id )
            );
        }

        return $result;
    }

    /**
     * Decrypt / clean a stored Smash Balloon token into a usable plaintext token.
     *
     * Order of attempts mirrors Smash Balloon's own sbi_maybe_clean(): decrypt
     * with the site key, fall back to the raw value, then strip the junk marker
     * or undo the dot-encoded obfuscation used by some older versions.
     *
     * @param string $stored Value from sbi_sources.access_token.
     * @return string Plaintext token, or '' when unreadable.
     */
    public static function extract_token( $stored ) {
        $stored = trim( (string) $stored );
        if ( '' === $stored ) {
            return '';
        }

        $value     = $stored;
        $decrypted = self::sbi_decrypt( $stored );
        if ( false !== $decrypted && '' !== $decrypted ) {
            $value = $decrypted;
        }

        if ( substr_count( $value, '.' ) < 3 ) {
            return trim( str_replace( self::SBI_TOKEN_MARKER, '', $value ) );
        }

        $parts = explode( '.', trim( $value ) );
        if ( count( $parts ) < 4 ) {
            return trim( str_replace( self::SBI_TOKEN_MARKER, '', $value ) );
        }

        return $parts[0] . '.' . base64_decode( $parts[1] ) . '.' . base64_decode( $parts[2] . $parts[3] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
    }

    /**
     * Decrypt using Smash Balloon's scheme (AES-256-CTR, iv-prefixed, salted, base64).
     *
     * @param string $raw_value Stored value.
     * @return string|false Plaintext, or false if the value is not Smash Balloon ciphertext.
     */
    private static function sbi_decrypt( $raw_value ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return false;
        }

        $key  = defined( 'SBI_ENCRYPTION_KEY' ) && '' !== SBI_ENCRYPTION_KEY ? SBI_ENCRYPTION_KEY : ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ? LOGGED_IN_KEY : 'das-ist-kein-geheimer-schluessel' );
        $salt = defined( 'SBI_ENCRYPTION_SALT' ) && '' !== SBI_ENCRYPTION_SALT ? SBI_ENCRYPTION_SALT : ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ? LOGGED_IN_SALT : 'das-ist-kein-geheimes-salz' );

        $decoded = base64_decode( $raw_value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        if ( false === $decoded ) {
            return false;
        }

        $method = 'aes-256-ctr';
        $ivlen  = openssl_cipher_iv_length( $method );
        if ( strlen( $decoded ) <= $ivlen ) {
            return false;
        }

        $iv         = substr( $decoded, 0, $ivlen );
        $ciphertext = substr( $decoded, $ivlen );

        $value = openssl_decrypt( $ciphertext, $method, $key, 0, $iv );
        if ( ! $value || substr( $value, -strlen( $salt ) ) !== $salt ) {
            return false;
        }

        return substr( $value, 0, -strlen( $salt ) );
    }

    /**
     * Decide the import status for a candidate.
     *
     * @param string   $token        Plaintext token ('' if unreadable).
     * @param string   $account_id   Smash Balloon account_id.
     * @param string[] $existing_ids Instagram user IDs already in our table.
     * @return string One of the STATUS_* constants.
     */
    private static function classify( $token, $account_id, $existing_ids ) {
        if ( '' === $token ) {
            return self::STATUS_UNREADABLE;
        }
        if ( 0 === strpos( $token, 'EA' ) ) {
            return self::STATUS_FACEBOOK_LOGIN;
        }
        if ( in_array( (string) $account_id, $existing_ids, true ) ) {
            return self::STATUS_ALREADY_CONNECTED;
        }
        return self::STATUS_IMPORTABLE;
    }

    /**
     * Human-readable label for a status.
     *
     * @param string $status Status constant.
     * @return string
     */
    public static function status_label( $status ) {
        switch ( $status ) {
            case self::STATUS_IMPORTABLE:
                return __( 'Ready to import', 'bwg-instagram-feed' );
            case self::STATUS_ALREADY_CONNECTED:
                return __( 'Already connected', 'bwg-instagram-feed' );
            case self::STATUS_FACEBOOK_LOGIN:
                return __( 'Facebook Login token; reconnect instead', 'bwg-instagram-feed' );
            case self::STATUS_UNREADABLE:
            default:
                return __( 'Token unreadable', 'bwg-instagram-feed' );
        }
    }

    /**
     * Call /me with a token to confirm it works and read account details.
     *
     * @param string $token Plaintext access token.
     * @return array|WP_Error Decoded response on success.
     */
    private static function fetch_me( $token ) {
        $url = sprintf(
            'https://graph.instagram.com/%s/me?fields=id,user_id,username,account_type&access_token=%s',
            BWG_IGF_GRAPH_API_VERSION,
            rawurlencode( $token )
        );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['username'] ) ) {
            $message = __( 'Instagram did not accept this token.', 'bwg-instagram-feed' );
            if ( isset( $data['error']['message'] ) ) {
                $message = (string) $data['error']['message'];
            }
            return new WP_Error( 'bwg_igf_sbi_token_rejected', $message );
        }

        return $data;
    }

    /**
     * Map an Instagram or Smash Balloon account type onto our ENUM.
     *
     * @param string $type Raw type.
     * @return string basic|business|creator
     */
    private static function normalize_account_type( $type ) {
        $type = strtolower( (string) $type );
        if ( 'media_creator' === $type ) {
            $type = 'creator';
        }
        if ( ! in_array( $type, array( 'basic', 'business', 'creator' ), true ) ) {
            $type = 'business';
        }
        return $type;
    }

    /**
     * Work out an expires_at value from the Smash Balloon row.
     *
     * Smash Balloon's background refresh keeps its expiry roughly accurate.
     * When the stored value is missing, zero, or already past, fall back to a
     * conservative window; our own refresher renews the token before it ends.
     *
     * @param string $sbi_expires Value of sbi_sources.expires.
     * @return string MySQL datetime (GMT).
     */
    private static function resolve_expiry( $sbi_expires ) {
        $ts = $sbi_expires ? strtotime( $sbi_expires ) : false;
        if ( ! $ts || $ts <= time() + DAY_IN_SECONDS ) {
            $ts = time() + self::FALLBACK_LIFETIME;
        }
        return gmdate( 'Y-m-d H:i:s', $ts );
    }

    /**
     * Log through BWG_IGF_Logger when available.
     *
     * @param string $level   info|warning|error.
     * @param string $message Message.
     * @param array  $context Context.
     */
    private static function log( $level, $message, $context = array() ) {
        if ( ! class_exists( 'BWG_IGF_Logger' ) ) {
            return;
        }
        BWG_IGF_Logger::log( $level, $message, $context );
    }
}
