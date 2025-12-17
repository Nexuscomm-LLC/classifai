<?php
/**
 * Encryption class for securing sensitive data.
 *
 * @package Classifai\Security
 * @since 3.8.0
 */

namespace Classifai\Security;

/**
 * Encryption class.
 *
 * Provides encryption and decryption functionality for sensitive data
 * such as API keys. Uses AES-256-CBC encryption.
 *
 * @since 3.8.0
 */
class Encryption {

	/**
	 * Encryption method.
	 *
	 * @var string
	 */
	const METHOD = 'aes-256-cbc';

	/**
	 * Option name for storing the encryption key.
	 *
	 * @var string
	 */
	const KEY_OPTION = 'classifai_encryption_key';

	/**
	 * Get the encryption key.
	 *
	 * Uses a custom constant if defined, otherwise falls back to
	 * a generated key stored in the database, or finally uses
	 * WordPress salts.
	 *
	 * @return string The encryption key.
	 */
	private static function get_key(): string {
		// First priority: custom constant.
		if ( defined( 'CLASSIFAI_ENCRYPTION_KEY' ) && CLASSIFAI_ENCRYPTION_KEY ) {
			return CLASSIFAI_ENCRYPTION_KEY;
		}

		// Second priority: stored key in database.
		$stored_key = get_option( self::KEY_OPTION );
		if ( $stored_key ) {
			return $stored_key;
		}

		// Generate and store a new key.
		$new_key = self::generate_key();
		update_option( self::KEY_OPTION, $new_key, false );

		return $new_key;
	}

	/**
	 * Generate a cryptographically secure encryption key.
	 *
	 * @return string The generated key.
	 */
	private static function generate_key(): string {
		// Use WordPress salts as additional entropy.
		$salt = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );

		// Generate random bytes and combine with salt.
		$random = bin2hex( random_bytes( 32 ) );

		return hash( 'sha256', $random . $salt );
	}

	/**
	 * Encrypt a value.
	 *
	 * @param string $value The value to encrypt.
	 * @return string|false The encrypted value (base64 encoded) or false on failure.
	 */
	public static function encrypt( string $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		$key = self::get_key();

		// Generate a random IV.
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		// Encrypt the value.
		$encrypted = openssl_encrypt(
			$value,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $encrypted ) {
			return false;
		}

		// Combine IV and encrypted data, then base64 encode.
		$combined = $iv . $encrypted;

		return base64_encode( $combined ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $encrypted The encrypted value (base64 encoded).
	 * @return string|false The decrypted value or false on failure.
	 */
	public static function decrypt( string $encrypted ) {
		if ( empty( $encrypted ) ) {
			return $encrypted;
		}

		// Check if the value is base64 encoded (encrypted).
		$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			// Value is not encrypted, return as-is.
			return $encrypted;
		}

		$key = self::get_key();

		// Extract IV and encrypted data.
		$iv_length = openssl_cipher_iv_length( self::METHOD );

		// Validate the decoded string has enough length.
		if ( strlen( $decoded ) <= $iv_length ) {
			// Likely not encrypted, return original.
			return $encrypted;
		}

		$iv             = substr( $decoded, 0, $iv_length );
		$encrypted_data = substr( $decoded, $iv_length );

		// Decrypt the value.
		$decrypted = openssl_decrypt(
			$encrypted_data,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $decrypted ) {
			// Decryption failed, might be plaintext.
			return $encrypted;
		}

		return $decrypted;
	}

	/**
	 * Check if a value is encrypted.
	 *
	 * @param string $value The value to check.
	 * @return bool True if the value appears to be encrypted.
	 */
	public static function is_encrypted( string $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		// Check if base64 encoded.
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return false;
		}

		// Check if the decoded value has the expected structure.
		$iv_length = openssl_cipher_iv_length( self::METHOD );

		return strlen( $decoded ) > $iv_length;
	}

	/**
	 * Rotate the encryption key.
	 *
	 * Re-encrypts all stored API keys with a new key.
	 *
	 * @return bool True if key rotation was successful.
	 */
	public static function rotate_key(): bool {
		// Get all feature settings that might contain API keys.
		$features = [
			'classifai_feature_title_generation',
			'classifai_feature_excerpt_generation',
			'classifai_feature_content_generation',
			'classifai_feature_classification',
			'classifai_feature_text_to_speech',
			'classifai_feature_image_generation',
			'classifai_feature_descriptive_text_generator',
		];

		$old_key = self::get_key();

		// Generate a new key.
		$new_key = self::generate_key();

		// Re-encrypt all API keys.
		foreach ( $features as $feature_option ) {
			$settings = get_option( $feature_option, [] );

			if ( empty( $settings ) ) {
				continue;
			}

			$updated = false;

			// Look for API keys in provider settings.
			foreach ( $settings as $key => $value ) {
				if ( is_array( $value ) && isset( $value['api_key'] ) ) {
					// Decrypt with old key.
					$decrypted = self::decrypt_with_key( $value['api_key'], $old_key );

					if ( $decrypted ) {
						// Encrypt with new key.
						$settings[ $key ]['api_key'] = self::encrypt_with_key( $decrypted, $new_key );
						$updated                     = true;
					}
				}
			}

			if ( $updated ) {
				update_option( $feature_option, $settings );
			}
		}

		// Store the new key.
		update_option( self::KEY_OPTION, $new_key, false );

		return true;
	}

	/**
	 * Encrypt with a specific key.
	 *
	 * @param string $value The value to encrypt.
	 * @param string $key   The encryption key.
	 * @return string|false The encrypted value or false on failure.
	 */
	private static function encrypt_with_key( string $value, string $key ) {
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt(
			$value,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $encrypted ) {
			return false;
		}

		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt with a specific key.
	 *
	 * @param string $encrypted The encrypted value.
	 * @param string $key       The encryption key.
	 * @return string|false The decrypted value or false on failure.
	 */
	private static function decrypt_with_key( string $encrypted, string $key ) {
		$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return false;
		}

		$iv_length = openssl_cipher_iv_length( self::METHOD );

		if ( strlen( $decoded ) <= $iv_length ) {
			return false;
		}

		$iv             = substr( $decoded, 0, $iv_length );
		$encrypted_data = substr( $decoded, $iv_length );

		return openssl_decrypt(
			$encrypted_data,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
	}

	/**
	 * Check if encryption is available.
	 *
	 * @return bool True if OpenSSL is available.
	 */
	public static function is_available(): bool {
		return function_exists( 'openssl_encrypt' ) &&
			function_exists( 'openssl_decrypt' ) &&
			in_array( self::METHOD, openssl_get_cipher_methods(), true );
	}
}
