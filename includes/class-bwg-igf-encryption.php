<?php
/**
 * Encryption Helper Class
 *
 * Provides secure encryption and decryption for sensitive data like OAuth tokens.
 * Uses AES-256-GCM encryption with a site-specific key derived from WordPress salts.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encryption class for secure token storage.
 */
class BWG_IGF_Encryption {

	/**
	 * Cipher method for encryption.
	 *
	 * @var string
	 */
	const CIPHER_METHOD = 'aes-256-gcm';

	/**
	 * Key identifier prefix for stored tokens.
	 *
	 * @var string
	 */
	const ENCRYPTED_PREFIX = 'bwg_enc_v1:';

	/**
	 * Get the encryption key.
	 *
	 * Uses WordPress AUTH_KEY and SECURE_AUTH_KEY salts to derive a unique key.
	 *
	 * @return string The encryption key.
	 */
	private static function get_encryption_key() {
		// Use WordPress salts to create a unique encryption key.
		$key_material = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'bwg-igf-default-key-change-me';
		$key_material .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'bwg-igf-secure-key-change-me';

		// Hash the key material to get a consistent 32-byte key for AES-256.
		return hash( 'sha256', $key_material, true );
	}

	/**
	 * Check if encryption is available.
	 *
	 * @return bool True if OpenSSL extension is available, false otherwise.
	 */
	public static function is_encryption_available() {
		return extension_loaded( 'openssl' ) && in_array( self::CIPHER_METHOD, openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypt a value.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string|false The encrypted value (base64 encoded with prefix) or false on failure.
	 */
	public static function encrypt( $plaintext ) {
		if ( empty( $plaintext ) ) {
			return false;
		}

		if ( ! self::is_encryption_available() ) {
			// Fallback: If encryption is not available, return base64 encoded value.
			// This is NOT secure but prevents data loss on systems without OpenSSL.
			return self::ENCRYPTED_PREFIX . 'fallback:' . base64_encode( $plaintext );
		}

		$key = self::get_encryption_key();

		// Generate a random IV (Initialization Vector).
		$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		if ( false === $iv ) {
			return false;
		}

		// Encrypt the data.
		$tag = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			16 // Tag length for GCM.
		);

		if ( false === $ciphertext ) {
			return false;
		}

		// Combine IV + tag + ciphertext and base64 encode.
		$encrypted = base64_encode( $iv . $tag . $ciphertext );

		// Add prefix to identify encrypted values.
		return self::ENCRYPTED_PREFIX . $encrypted;
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $encrypted_value The encrypted value (with prefix).
	 * @return string|false The decrypted value or false on failure.
	 */
	public static function decrypt( $encrypted_value ) {
		if ( empty( $encrypted_value ) ) {
			return false;
		}

		// Check for our encryption prefix.
		if ( strpos( $encrypted_value, self::ENCRYPTED_PREFIX ) !== 0 ) {
			// Not encrypted with our method - return as-is (for backwards compatibility).
			return $encrypted_value;
		}

		// Remove prefix.
		$encrypted_data = substr( $encrypted_value, strlen( self::ENCRYPTED_PREFIX ) );

		// Check for fallback encoding (no encryption available).
		if ( strpos( $encrypted_data, 'fallback:' ) === 0 ) {
			return base64_decode( substr( $encrypted_data, 9 ) );
		}

		if ( ! self::is_encryption_available() ) {
			return false;
		}

		$key = self::get_encryption_key();

		// Decode the base64 string.
		$decoded = base64_decode( $encrypted_data );

		if ( false === $decoded ) {
			return false;
		}

		// Extract IV, tag, and ciphertext.
		$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
		$tag_length = 16; // GCM tag length.

		if ( strlen( $decoded ) < ( $iv_length + $tag_length ) ) {
			return false;
		}

		$iv = substr( $decoded, 0, $iv_length );
		$tag = substr( $decoded, $iv_length, $tag_length );
		$ciphertext = substr( $decoded, $iv_length + $tag_length );

		// Decrypt the data.
		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return $plaintext;
	}

	/**
	 * Check if a value is encrypted.
	 *
	 * @param string $value The value to check.
	 * @return bool True if the value appears to be encrypted, false otherwise.
	 */
	public static function is_encrypted( $value ) {
		return is_string( $value ) && strpos( $value, self::ENCRYPTED_PREFIX ) === 0;
	}

	/**
	 * Verify that a value is encrypted and not plaintext.
	 *
	 * This can be used to validate that tokens are stored encrypted.
	 *
	 * @param string $value The value to verify.
	 * @return array Verification result with 'is_encrypted' and 'encryption_method' keys.
	 */
	public static function verify_encrypted( $value ) {
		$result = array(
			'is_encrypted'      => false,
			'encryption_method' => 'none',
			'is_plaintext'      => false,
		);

		if ( empty( $value ) ) {
			return $result;
		}

		// Check if it has our encryption prefix.
		if ( self::is_encrypted( $value ) ) {
			$result['is_encrypted'] = true;

			// Determine encryption method.
			$encrypted_data = substr( $value, strlen( self::ENCRYPTED_PREFIX ) );
			if ( strpos( $encrypted_data, 'fallback:' ) === 0 ) {
				$result['encryption_method'] = 'base64_fallback';
			} else {
				$result['encryption_method'] = 'aes-256-gcm';
			}
		} else {
			// Check if it looks like a raw Instagram token (starts with "IG" and is alphanumeric).
			if ( preg_match( '/^IG[a-zA-Z0-9_-]{100,}$/', $value ) ) {
				$result['is_plaintext'] = true;
			}
		}

		return $result;
	}
}
