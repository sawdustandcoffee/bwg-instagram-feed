<?php
/**
 * Instagram Credentials Class
 *
 * Provides the Instagram App credentials used for OAuth.
 *
 * Credentials are resolved in the following order for both the App ID and the
 * App Secret:
 *   1. wp-config.php constant (BWG_IGF_INSTAGRAM_APP_ID / _APP_SECRET), if defined.
 *   2. The value stored in wp_options via the plugin Settings page.
 *   3. A built-in placeholder value (used to keep the Connect button disabled
 *      until real credentials are configured).
 *
 * SECURITY NOTE: The App Secret is stored encrypted at rest (AES-256-GCM via
 * BWG_IGF_Encryption) and is never rendered back in the admin UI. The App ID is
 * public and is stored/rendered in plaintext.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instagram Credentials class for OAuth credentials.
 */
class BWG_IGF_Instagram_Credentials {

	/**
	 * Option name that stores the Instagram App ID (plaintext, public value).
	 *
	 * @var string
	 */
	const OPTION_APP_ID = 'bwg_igf_instagram_app_id';

	/**
	 * Option name that stores the encrypted Instagram App Secret.
	 *
	 * @var string
	 */
	const OPTION_APP_SECRET = 'bwg_igf_instagram_app_secret';

	/**
	 * Placeholder App ID used when no real credential is configured.
	 *
	 * @var string
	 */
	const PLACEHOLDER_APP_ID = '1234567890123456';

	/**
	 * Placeholder App Secret used when no real credential is configured.
	 *
	 * @var string
	 */
	const PLACEHOLDER_APP_SECRET = 'abcdef1234567890abcdef1234567890';

	/**
	 * Get the Instagram App ID.
	 *
	 * Resolution order: wp-config constant, stored option, then placeholder.
	 * The App ID is not sensitive and can be exposed in OAuth URLs.
	 *
	 * @return string The Instagram App ID.
	 */
	public static function get_app_id() {
		// 1. Environment/constant override wins (useful for development/testing).
		if ( defined( 'BWG_IGF_INSTAGRAM_APP_ID' ) ) {
			return BWG_IGF_INSTAGRAM_APP_ID;
		}

		// 2. Stored option (plaintext - the App ID is a public value).
		$stored_app_id = get_option( self::OPTION_APP_ID, '' );
		if ( ! empty( $stored_app_id ) ) {
			return $stored_app_id;
		}

		// 3. Built-in placeholder - keeps the Connect button disabled.
		return self::PLACEHOLDER_APP_ID;
	}

	/**
	 * Get the Instagram App Secret.
	 *
	 * Resolution order: wp-config constant, stored (encrypted) option, then
	 * placeholder. The App Secret is sensitive and is never exposed in the UI.
	 * It's only used server-side during the OAuth token exchange.
	 *
	 * @return string The Instagram App Secret.
	 */
	public static function get_app_secret() {
		// 1. Environment/constant override wins (useful for development/testing).
		if ( defined( 'BWG_IGF_INSTAGRAM_APP_SECRET' ) ) {
			return BWG_IGF_INSTAGRAM_APP_SECRET;
		}

		// 2. Stored option - decrypt it. The secret is always stored encrypted.
		$stored_secret = get_option( self::OPTION_APP_SECRET, '' );
		if ( ! empty( $stored_secret ) ) {
			self::maybe_load_encryption();
			$decrypted = BWG_IGF_Encryption::decrypt( $stored_secret );

			// Guard against decrypt failure (e.g. changed salts, corrupt data):
			// fall through to the placeholder rather than returning a bad value.
			if ( ! empty( $decrypted ) ) {
				return $decrypted;
			}
		}

		// 3. Built-in placeholder - keeps the Connect button disabled.
		return self::PLACEHOLDER_APP_SECRET;
	}

	/**
	 * Ensure the encryption helper class is available before use.
	 *
	 * It is normally loaded in the plugin's load_admin_ajax(), but require it
	 * defensively here so the credentials class is self-contained.
	 *
	 * @return void
	 */
	private static function maybe_load_encryption() {
		if ( ! class_exists( 'BWG_IGF_Encryption' ) ) {
			require_once BWG_IGF_PLUGIN_DIR . 'includes/class-bwg-igf-encryption.php';
		}
	}

	/**
	 * Check whether the credentials are currently sourced from wp-config constants.
	 *
	 * When either constant is defined it overrides any stored DB value, so the
	 * Settings UI should reflect that the stored fields are ignored.
	 *
	 * @return bool True if either credential constant is defined.
	 */
	public static function is_credential_source_constant() {
		return defined( 'BWG_IGF_INSTAGRAM_APP_ID' ) || defined( 'BWG_IGF_INSTAGRAM_APP_SECRET' );
	}

	/**
	 * Check whether real credentials are stored in the database.
	 *
	 * @return bool True when both the App ID and App Secret options are non-empty.
	 */
	public static function has_stored_credentials() {
		$stored_app_id     = get_option( self::OPTION_APP_ID, '' );
		$stored_app_secret = get_option( self::OPTION_APP_SECRET, '' );

		return ! empty( $stored_app_id ) && ! empty( $stored_app_secret );
	}

	/**
	 * Check if credentials are configured.
	 *
	 * @return bool True if both App ID and App Secret are available.
	 */
	public static function has_credentials() {
		$app_id = self::get_app_id();
		$app_secret = self::get_app_secret();

		return ! empty( $app_id ) && ! empty( $app_secret );
	}

	/**
	 * Get the OAuth redirect URI.
	 *
	 * @return string The OAuth callback URL.
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin.php?page=bwg-igf-accounts&oauth_callback=1' );
	}

	/**
	 * Get the OAuth authorization URL.
	 *
	 * @return string The full Instagram OAuth authorization URL.
	 */
	public static function get_oauth_url() {
		$app_id = self::get_app_id();
		$redirect_uri = self::get_redirect_uri();
		$scope = implode( ',', self::get_scopes() );

		return sprintf(
			'https://www.instagram.com/oauth/authorize?client_id=%s&redirect_uri=%s&scope=%s&response_type=code',
			rawurlencode( $app_id ),
			rawurlencode( $redirect_uri ),
			rawurlencode( $scope )
		);
	}

	/**
	 * Get required OAuth scopes.
	 *
	 * @return array List of required Instagram permission scopes.
	 */
	public static function get_scopes() {
		return array(
			'instagram_business_basic', // Read-only access to profile info and media (Instagram API with Instagram Login)
		);
	}

	/**
	 * Check if credentials appear to be the placeholder/development values.
	 *
	 * This is useful for showing warnings in development mode.
	 *
	 * @return bool True if using placeholder credentials.
	 */
	public static function is_using_placeholder_credentials() {
		$app_id = self::get_app_id();
		$app_secret = self::get_app_secret();

		// Check if these are the placeholder values
		return self::PLACEHOLDER_APP_ID === $app_id || self::PLACEHOLDER_APP_SECRET === $app_secret;
	}
}
