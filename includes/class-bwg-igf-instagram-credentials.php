<?php
/**
 * Instagram Credentials Class
 *
 * Provides built-in Instagram App credentials for OAuth.
 * These are hardcoded in the plugin to simplify the user experience -
 * users don't need to create their own Meta Developer App.
 *
 * SECURITY NOTE: These credentials are stored in code rather than wp_options
 * to prevent accidental exposure through database dumps or the admin UI.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instagram Credentials class for built-in OAuth credentials.
 */
class BWG_IGF_Instagram_Credentials {

	/**
	 * Get the Instagram App ID.
	 *
	 * The App ID is not sensitive and can be exposed in OAuth URLs.
	 *
	 * @return string The Instagram App ID.
	 */
	public static function get_app_id() {
		// Check for environment override first (useful for development/testing)
		if ( defined( 'BWG_IGF_INSTAGRAM_APP_ID' ) ) {
			return BWG_IGF_INSTAGRAM_APP_ID;
		}

		// Built-in Instagram App ID for BWG Instagram Feed plugin
		// This is the public App ID - safe to expose
		return '1234567890123456';
	}

	/**
	 * Get the Instagram App Secret.
	 *
	 * The App Secret is sensitive and should never be exposed in the UI.
	 * It's only used server-side during the OAuth token exchange.
	 *
	 * @return string The Instagram App Secret.
	 */
	public static function get_app_secret() {
		// Check for environment override first (useful for development/testing)
		if ( defined( 'BWG_IGF_INSTAGRAM_APP_SECRET' ) ) {
			return BWG_IGF_INSTAGRAM_APP_SECRET;
		}

		// Built-in Instagram App Secret for BWG Instagram Feed plugin
		// This is stored in code to prevent exposure through database dumps
		return 'abcdef1234567890abcdef1234567890';
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
		$scope = 'user_profile,user_media';

		return sprintf(
			'https://api.instagram.com/oauth/authorize?client_id=%s&redirect_uri=%s&scope=%s&response_type=code',
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
			'user_profile', // Access basic profile info (username, account type)
			'user_media',   // Access user's media (posts)
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
		return $app_id === '1234567890123456' || $app_secret === 'abcdef1234567890abcdef1234567890';
	}
}
