<?php
/**
 * Error Messages helper for user-friendly error translations.
 *
 * @package Classifai\Helpers
 * @since 3.8.0
 */

namespace Classifai\Helpers;

use WP_Error;

/**
 * ErrorMessages class.
 *
 * Provides user-friendly translations for technical error messages
 * returned by AI service providers.
 *
 * @since 3.8.0
 */
class ErrorMessages {

	/**
	 * Map of error codes to user-friendly messages.
	 *
	 * @var array
	 */
	private static array $error_map = [];

	/**
	 * Initialize the error message mappings.
	 *
	 * @return void
	 */
	private static function init(): void {
		if ( ! empty( self::$error_map ) ) {
			return;
		}

		self::$error_map = [
			// Rate limiting errors.
			'rate_limit_exceeded'     => __( 'You have made too many requests. Please wait a moment and try again.', 'classifai' ),
			'too_many_requests'       => __( 'Too many requests. Please slow down and try again in a few seconds.', 'classifai' ),

			// Authentication errors.
			'invalid_api_key'         => __( 'Your API key appears to be invalid. Please check your settings.', 'classifai' ),
			'authentication_failed'   => __( 'Authentication failed. Please verify your API credentials.', 'classifai' ),
			'unauthorized'            => __( 'You are not authorized to use this service. Please check your API key.', 'classifai' ),
			'invalid_credentials'     => __( 'Invalid credentials provided. Please update your settings.', 'classifai' ),

			// Content errors.
			'content_too_long'        => __( 'The content is too long to process. Please try with shorter content.', 'classifai' ),
			'content_too_short'       => __( 'The content is too short to process. Please provide more content.', 'classifai' ),
			'empty_content'           => __( 'No content was provided. Please add some content before processing.', 'classifai' ),
			'invalid_content'         => __( 'The content format is not supported. Please check the content and try again.', 'classifai' ),

			// Service errors.
			'service_unavailable'     => __( 'The AI service is temporarily unavailable. Please try again later.', 'classifai' ),
			'timeout'                 => __( 'The request timed out. Please try again.', 'classifai' ),
			'network_error'           => __( 'A network error occurred. Please check your connection and try again.', 'classifai' ),
			'server_error'            => __( 'The AI service encountered an error. Please try again later.', 'classifai' ),

			// Quota errors.
			'insufficient_quota'      => __( 'API quota exceeded. Please check your account billing and usage limits.', 'classifai' ),
			'quota_exceeded'          => __( 'You have exceeded your API usage quota. Please upgrade your plan or wait for it to reset.', 'classifai' ),
			'billing_issue'           => __( 'There is a billing issue with your API account. Please check your payment settings.', 'classifai' ),

			// Configuration errors.
			'not_configured'          => __( 'This feature is not configured. Please complete the setup in settings.', 'classifai' ),
			'not_enabled'             => __( 'This feature is not enabled. Please enable it in the settings.', 'classifai' ),
			'invalid_settings'        => __( 'The feature settings are invalid. Please review and update your configuration.', 'classifai' ),
			'missing_provider'        => __( 'No AI provider is selected. Please choose a provider in the settings.', 'classifai' ),

			// Permission errors.
			'permission_denied'       => __( 'You do not have permission to use this feature.', 'classifai' ),
			'access_denied'           => __( 'Access to this feature has been denied. Please contact your administrator.', 'classifai' ),

			// Image-specific errors.
			'image_too_large'         => __( 'The image file is too large. Please use a smaller image.', 'classifai' ),
			'unsupported_image_type'  => __( 'This image format is not supported. Please use JPEG, PNG, or GIF.', 'classifai' ),
			'image_processing_failed' => __( 'Failed to process the image. Please try with a different image.', 'classifai' ),

			// Audio-specific errors.
			'audio_too_long'          => __( 'The audio file is too long. Please use a shorter audio clip.', 'classifai' ),
			'unsupported_audio_type'  => __( 'This audio format is not supported. Please use MP3, WAV, or OGG.', 'classifai' ),

			// Moderation errors.
			'content_flagged'         => __( 'The content was flagged by the moderation system and cannot be processed.', 'classifai' ),
			'policy_violation'        => __( 'The content violates usage policies and cannot be processed.', 'classifai' ),

			// Generic errors.
			'unknown_error'           => __( 'An unexpected error occurred. Please try again.', 'classifai' ),
			'classifai_api_error'     => __( 'An error occurred while communicating with the AI service.', 'classifai' ),
		];

		/**
		 * Filter the error message mappings.
		 *
		 * @since 3.8.0
		 * @hook classifai_error_messages
		 *
		 * @param array $error_map Error code to message mappings.
		 *
		 * @return array Filtered error mappings.
		 */
		self::$error_map = apply_filters( 'classifai_error_messages', self::$error_map );
	}

	/**
	 * Get a user-friendly error message for an error code.
	 *
	 * @param string $error_code The error code.
	 * @return string The user-friendly message.
	 */
	public static function get( string $error_code ): string {
		self::init();

		return self::$error_map[ $error_code ] ?? self::$error_map['unknown_error'];
	}

	/**
	 * Translate a WP_Error to have a user-friendly message.
	 *
	 * @param WP_Error $error The WP_Error object.
	 * @return WP_Error The translated WP_Error.
	 */
	public static function translate_wp_error( WP_Error $error ): WP_Error {
		self::init();

		$code            = $error->get_error_code();
		$original_message = $error->get_error_message();

		// Check if we have a translation for this code.
		if ( isset( self::$error_map[ $code ] ) ) {
			$translated_message = self::$error_map[ $code ];
		} else {
			// Try to find a matching pattern.
			$translated_message = self::find_pattern_match( $code, $original_message );
		}

		// Create a new WP_Error with the translated message.
		$data = $error->get_error_data();

		// Store the original message in data for debugging.
		if ( ! is_array( $data ) ) {
			$data = [ 'original_data' => $data ];
		}
		$data['original_message'] = $original_message;

		return new WP_Error( $code, $translated_message, $data );
	}

	/**
	 * Find a matching pattern for the error.
	 *
	 * @param string $code    The error code.
	 * @param string $message The original message.
	 * @return string The translated message.
	 */
	private static function find_pattern_match( string $code, string $message ): string {
		$patterns = [
			'/rate.?limit/i'            => 'rate_limit_exceeded',
			'/too.?many.?requests/i'    => 'too_many_requests',
			'/invalid.?(api)?.?key/i'   => 'invalid_api_key',
			'/unauthorized/i'           => 'unauthorized',
			'/authentication/i'         => 'authentication_failed',
			'/quota/i'                  => 'quota_exceeded',
			'/billing/i'                => 'billing_issue',
			'/timeout/i'                => 'timeout',
			'/network/i'                => 'network_error',
			'/content.?too.?long/i'     => 'content_too_long',
			'/image.?too.?large/i'      => 'image_too_large',
			'/service.?unavailable/i'   => 'service_unavailable',
			'/server.?error/i'          => 'server_error',
			'/permission/i'             => 'permission_denied',
			'/flagged/i'                => 'content_flagged',
			'/policy/i'                 => 'policy_violation',
		];

		$search_text = $code . ' ' . $message;

		foreach ( $patterns as $pattern => $mapped_code ) {
			if ( preg_match( $pattern, $search_text ) ) {
				return self::$error_map[ $mapped_code ];
			}
		}

		return self::$error_map['unknown_error'];
	}

	/**
	 * Get all available error codes.
	 *
	 * @return array Array of error codes.
	 */
	public static function get_error_codes(): array {
		self::init();

		return array_keys( self::$error_map );
	}

	/**
	 * Check if an error code has a translation.
	 *
	 * @param string $error_code The error code.
	 * @return bool True if a translation exists.
	 */
	public static function has_translation( string $error_code ): bool {
		self::init();

		return isset( self::$error_map[ $error_code ] );
	}

	/**
	 * Add a custom error message.
	 *
	 * @param string $error_code The error code.
	 * @param string $message    The user-friendly message.
	 * @return void
	 */
	public static function add( string $error_code, string $message ): void {
		self::init();

		self::$error_map[ $error_code ] = $message;
	}

	/**
	 * Get error message with context.
	 *
	 * @param string $error_code The error code.
	 * @param array  $context    Context variables for sprintf.
	 * @return string The formatted message.
	 */
	public static function get_with_context( string $error_code, array $context = [] ): string {
		$message = self::get( $error_code );

		if ( ! empty( $context ) ) {
			$message = vsprintf( $message, $context );
		}

		return $message;
	}
}
