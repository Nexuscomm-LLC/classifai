<?php
/**
 * API Exception class for centralized error handling.
 *
 * @package Classifai\Exceptions
 * @since 3.8.0
 */

namespace Classifai\Exceptions;

use WP_Error;

/**
 * APIException class.
 *
 * Provides a standardized way to handle API errors across all providers.
 * This exception can be converted to WP_Error for WordPress compatibility.
 *
 * @since 3.8.0
 */
class APIException extends \Exception {

	/**
	 * The provider ID that generated this exception.
	 *
	 * @var string
	 */
	protected string $provider = '';

	/**
	 * The API endpoint that was called.
	 *
	 * @var string
	 */
	protected string $endpoint = '';

	/**
	 * Additional context about the error.
	 *
	 * @var array
	 */
	protected array $context = [];

	/**
	 * Error code for WordPress.
	 *
	 * @var string
	 */
	protected string $error_code = 'classifai_api_error';

	/**
	 * HTTP status code if applicable.
	 *
	 * @var int
	 */
	protected int $http_status = 0;

	/**
	 * Constructor.
	 *
	 * @param string          $message   The error message.
	 * @param string          $provider  The provider ID.
	 * @param string          $endpoint  The API endpoint.
	 * @param array           $context   Additional context.
	 * @param int             $code      The error code.
	 * @param \Throwable|null $previous  Previous exception.
	 */
	public function __construct(
		string $message,
		string $provider = '',
		string $endpoint = '',
		array $context = [],
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );

		$this->provider = $provider;
		$this->endpoint = $endpoint;
		$this->context  = $context;
	}

	/**
	 * Set the error code for WP_Error.
	 *
	 * @param string $code The error code.
	 * @return self
	 */
	public function set_error_code( string $code ): self {
		$this->error_code = $code;
		return $this;
	}

	/**
	 * Set the HTTP status code.
	 *
	 * @param int $status The HTTP status code.
	 * @return self
	 */
	public function set_http_status( int $status ): self {
		$this->http_status = $status;
		return $this;
	}

	/**
	 * Get the provider ID.
	 *
	 * @return string
	 */
	public function get_provider(): string {
		return $this->provider;
	}

	/**
	 * Get the endpoint.
	 *
	 * @return string
	 */
	public function get_endpoint(): string {
		return $this->endpoint;
	}

	/**
	 * Get the context.
	 *
	 * @return array
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Convert the exception to a WP_Error object.
	 *
	 * @return WP_Error
	 */
	public function to_wp_error(): WP_Error {
		$data = [
			'provider'    => $this->provider,
			'endpoint'    => $this->endpoint,
			'context'     => $this->context,
			'http_status' => $this->http_status,
		];

		return new WP_Error( $this->error_code, $this->getMessage(), $data );
	}

	/**
	 * Create an APIException from a WP_Error.
	 *
	 * @param WP_Error $error    The WP_Error object.
	 * @param string   $provider The provider ID.
	 * @param string   $endpoint The endpoint.
	 * @return self
	 */
	public static function from_wp_error( WP_Error $error, string $provider = '', string $endpoint = '' ): self {
		$data = $error->get_error_data();

		$exception = new self(
			$error->get_error_message(),
			$provider ?: ( $data['provider'] ?? '' ),
			$endpoint ?: ( $data['endpoint'] ?? '' ),
			$data['context'] ?? []
		);

		$exception->set_error_code( $error->get_error_code() );

		if ( isset( $data['http_status'] ) ) {
			$exception->set_http_status( (int) $data['http_status'] );
		}

		return $exception;
	}

	/**
	 * Create an exception for rate limit errors.
	 *
	 * @param string $provider The provider ID.
	 * @param int    $retry_after Seconds until rate limit resets.
	 * @return self
	 */
	public static function rate_limit_exceeded( string $provider, int $retry_after = 0 ): self {
		$exception = new self(
			__( 'API rate limit exceeded. Please try again later.', 'classifai' ),
			$provider,
			'',
			[ 'retry_after' => $retry_after ]
		);

		return $exception->set_error_code( 'rate_limit_exceeded' )->set_http_status( 429 );
	}

	/**
	 * Create an exception for authentication errors.
	 *
	 * @param string $provider The provider ID.
	 * @return self
	 */
	public static function authentication_failed( string $provider ): self {
		$exception = new self(
			__( 'API authentication failed. Please check your API credentials.', 'classifai' ),
			$provider
		);

		return $exception->set_error_code( 'authentication_failed' )->set_http_status( 401 );
	}

	/**
	 * Create an exception for invalid API key.
	 *
	 * @param string $provider The provider ID.
	 * @return self
	 */
	public static function invalid_api_key( string $provider ): self {
		$exception = new self(
			__( 'Invalid API key. Please verify your API key in the settings.', 'classifai' ),
			$provider
		);

		return $exception->set_error_code( 'invalid_api_key' )->set_http_status( 401 );
	}

	/**
	 * Create an exception for content too long.
	 *
	 * @param string $provider  The provider ID.
	 * @param int    $max_length Maximum allowed length.
	 * @return self
	 */
	public static function content_too_long( string $provider, int $max_length = 0 ): self {
		$exception = new self(
			__( 'The content is too long to process. Please try with shorter content.', 'classifai' ),
			$provider,
			'',
			[ 'max_length' => $max_length ]
		);

		return $exception->set_error_code( 'content_too_long' )->set_http_status( 400 );
	}

	/**
	 * Create an exception for service unavailable.
	 *
	 * @param string $provider The provider ID.
	 * @return self
	 */
	public static function service_unavailable( string $provider ): self {
		$exception = new self(
			__( 'The AI service is temporarily unavailable. Please try again later.', 'classifai' ),
			$provider
		);

		return $exception->set_error_code( 'service_unavailable' )->set_http_status( 503 );
	}

	/**
	 * Create an exception for insufficient quota.
	 *
	 * @param string $provider The provider ID.
	 * @return self
	 */
	public static function insufficient_quota( string $provider ): self {
		$exception = new self(
			__( 'API quota exceeded. Please check your account billing and usage limits.', 'classifai' ),
			$provider
		);

		return $exception->set_error_code( 'insufficient_quota' )->set_http_status( 402 );
	}

	/**
	 * Log the exception.
	 *
	 * @return void
	 */
	public function log(): void {
		if ( defined( 'CLASSIFAI_DEBUG' ) && CLASSIFAI_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[ClassifAI] %s Error: %s | Provider: %s | Endpoint: %s | Context: %s',
					$this->error_code,
					$this->getMessage(),
					$this->provider,
					$this->endpoint,
					wp_json_encode( $this->context )
				)
			);
		}
	}
}
