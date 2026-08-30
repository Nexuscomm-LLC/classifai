<?php
/**
 * Abstract class that defines the providers for a service.
 *
 * @package Classifai\Providers
 */

namespace Classifai\Providers;

use Classifai\Cache\ResponseCache;
use Classifai\Security\RateLimiter;
use Classifai\Tracking\UsageTracker;
use Classifai\Helpers\Debug;
use Classifai\Helpers\ErrorMessages;
use Classifai\Exceptions\APIException;
use WP_Error;

/**
 * Abstract Provider class.
 *
 * Base class for all AI service providers. Provides shared functionality
 * for caching, rate limiting, error handling, and usage tracking.
 *
 * @since 1.0.0
 */
abstract class Provider {

	/**
	 * The ID of the provider.
	 *
	 * To be set in the subclass.
	 *
	 * @var string
	 */
	const ID = '';

	/**
	 * Feature instance.
	 *
	 * @var \Classifai\Features\Feature
	 */
	protected $feature_instance = null;

	/**
	 * Prefix for the system prompt message.
	 *
	 * @var string
	 */
	protected $system_prompt = 'You will be provided with content delimited by triple quotes.';

	/**
	 * Prefix for the WooCommerce system prompt message.
	 *
	 * @var string
	 */
	protected $system_prompt_woo = 'You are an expert in e-commerce and WooCommerce SEO.';

	/**
	 * Request start time for duration tracking.
	 *
	 * @var float
	 */
	private float $request_start_time = 0;

	/**
	 * Format the result of most recent request.
	 *
	 * @param array|WP_Error $data Response data to format.
	 *
	 * @return string
	 */
	protected function get_formatted_latest_response( $data ): string {
		if ( ! $data ) {
			return __( 'N/A', 'classifai' );
		}

		if ( is_wp_error( $data ) ) {
			return $data->get_error_message();
		}

		return preg_replace( '/,"/', ', "', wp_json_encode( $data ) );
	}

	/**
	 * Get the product content.
	 *
	 * This is a helper function to get the product content in JSON format.
	 *
	 * @param int $product_id The product ID.
	 * @return string
	 */
	public function get_product_content( int $product_id ): string {
		$product = function_exists( 'wc_get_product' ) ? \wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			return '';
		}

		$product_data = [
			'title'       => $product->get_name(),
			'type'        => $product->get_type(),
			'sku'         => $product->get_sku(),
			'categories'  => function_exists( 'wc_get_product_category_list' ) ? wp_strip_all_tags( \wc_get_product_category_list( $product_id ) ) : null,
			'tags'        => function_exists( 'wc_get_product_tag_list' ) ? wp_strip_all_tags( \wc_get_product_tag_list( $product_id ) ) : null,
			'attributes'  => [],
			'price'       => $product->get_price(),
			'stock'       => $product->is_in_stock() ? 'In Stock' : 'Out of Stock',
			'short_desc'  => wp_strip_all_tags( $product->get_short_description() ),
			'description' => wp_strip_all_tags( $product->get_description() ),
		];

		// Fetch attributes.
		foreach ( $product->get_attributes() as $attribute_name => $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$terms                        = function_exists( 'wc_get_product_terms' ) ? \wc_get_product_terms( $product_id, $attribute_name, [ 'fields' => 'names' ] ) : [];
				$product_data['attributes'][] = $attribute_name . ': ' . implode( ', ', $terms );
			} else {
				$options                      = is_array( $attribute->get_options() ) ? $attribute->get_options() : [];
				$product_data['attributes'][] = $attribute_name . ': ' . implode( ', ', $options );
			}
		}

		return wp_json_encode( $product_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT );
	}

	/**
	 * Get the request messages.
	 *
	 * This is a helper function to get the request messages based on the post type.
	 *
	 * @param int    $post_id         The post ID.
	 * @param string $prompt          The prompt message.
	 * @param string $message_content The message content.
	 * @return array
	 */
	public function get_request_messages( int $post_id, string $prompt, string $message_content = '' ): array {
		$messages = [];

		// WooCommerce Product Handling.
		if (
			'product' === get_post_type( $post_id ) &&
			function_exists( 'wc_get_product' ) &&
			\wc_get_product( $post_id )
		) {
			$messages = [
				[
					'role'    => 'system',
					'content' => $this->system_prompt_woo . ' ' . $prompt,
				],
				[
					'role'    => 'user',
					'content' => sprintf( 'Product data: """%s"""', $message_content ),
				],
			];
		} else {
			// Fallback for regular WordPress posts, or when WooCommerce is not active.
			$messages = [
				[
					'role'    => 'system',
					'content' => $this->system_prompt . ' ' . $prompt,
				],
				[
					'role'    => 'user',
					'content' => '"""' . $message_content . '"""',
				],
			];
		}

		return $messages;
	}

	/**
	 * Adds an API key field.
	 *
	 * @param array $args API key field arguments.
	 */
	public function add_api_key_field( array $args = [] ) {
		$default_settings = $this->feature_instance->get_settings();
		$default_settings = $default_settings[ static::ID ];
		$id               = $args['id'] ?? 'api_key';

		add_settings_field(
			$id,
			$args['label'] ?? esc_html__( 'API key', 'classifai' ),
			[ $this->feature_instance, 'render_input' ],
			$this->feature_instance->get_option_name(),
			$this->feature_instance->get_option_name() . '_section',
			[
				'option_index'  => static::ID,
				'label_for'     => $id,
				'input_type'    => 'password',
				'default_value' => $default_settings[ $id ],
				'class'         => 'classifai-provider-field hidden provider-scope-' . static::ID, // Important to add this.
			]
		);
	}

	/**
	 * Check rate limit for the current user and feature.
	 *
	 * @param string $feature_id The feature ID.
	 * @return true|WP_Error True if allowed, WP_Error if rate limited.
	 */
	protected function check_rate_limit( string $feature_id ) {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return true; // Skip rate limiting for anonymous users.
		}

		$result = RateLimiter::check( $user_id, $feature_id );

		if ( is_wp_error( $result ) ) {
			Debug::warning( 'Rate limit exceeded', [
				'user_id'  => $user_id,
				'feature'  => $feature_id,
				'provider' => static::ID,
			] );
		}

		return $result;
	}

	/**
	 * Increment the rate limit counter.
	 *
	 * @param string $feature_id The feature ID.
	 * @return array Updated rate data.
	 */
	protected function increment_rate_limit( string $feature_id ): array {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return [];
		}

		return RateLimiter::increment( $user_id, $feature_id );
	}

	/**
	 * Get cached response if available.
	 *
	 * @param string $feature_id The feature ID.
	 * @param mixed  ...$args    Arguments to generate cache hash.
	 * @return mixed|false Cached response or false if not found.
	 */
	protected function get_cached_response( string $feature_id, ...$args ) {
		if ( ! ResponseCache::is_enabled() ) {
			return false;
		}

		$hash   = ResponseCache::generate_hash( static::ID, ...$args );
		$cached = ResponseCache::get( $feature_id, $hash );

		if ( false !== $cached ) {
			Debug::info( 'Cache hit', [
				'feature'  => $feature_id,
				'provider' => static::ID,
			] );
		}

		return $cached;
	}

	/**
	 * Cache a response.
	 *
	 * @param string $feature_id The feature ID.
	 * @param mixed  $response   The response to cache.
	 * @param int    $ttl        Cache TTL in seconds.
	 * @param mixed  ...$args    Arguments to generate cache hash.
	 * @return bool True if cached successfully.
	 */
	protected function cache_response( string $feature_id, $response, int $ttl = 3600, ...$args ): bool {
		if ( ! ResponseCache::is_enabled() || is_wp_error( $response ) ) {
			return false;
		}

		$hash = ResponseCache::generate_hash( static::ID, ...$args );

		return ResponseCache::set( $feature_id, $hash, $response, $ttl );
	}

	/**
	 * Log API usage for tracking.
	 *
	 * @param string $feature_id    The feature ID.
	 * @param array  $usage_data    {
	 *     Usage data.
	 *
	 *     @type int    $input_tokens  Number of input tokens.
	 *     @type int    $output_tokens Number of output tokens.
	 *     @type string $model         The model used.
	 *     @type bool   $success       Whether the request succeeded.
	 *     @type int    $post_id       The post ID if applicable.
	 * }
	 * @return void
	 */
	protected function log_usage( string $feature_id, array $usage_data = [] ): void {
		UsageTracker::log_request( static::ID, $feature_id, $usage_data );
	}

	/**
	 * Start timing an API request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $request  The request data.
	 * @return void
	 */
	protected function start_request_timer( string $endpoint, array $request = [] ): void {
		$this->request_start_time = microtime( true );
		Debug::log_api_request( static::ID, $endpoint, $request );
	}

	/**
	 * End timing an API request and log the response.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param mixed  $response The response.
	 * @return float The request duration in seconds.
	 */
	protected function end_request_timer( string $endpoint, $response ): float {
		$duration = microtime( true ) - $this->request_start_time;
		Debug::log_api_response( static::ID, $endpoint, $response, $duration );

		return $duration;
	}

	/**
	 * Handle an API error consistently.
	 *
	 * @param WP_Error $error    The WP_Error object.
	 * @param string   $endpoint The API endpoint that was called.
	 * @return WP_Error Translated WP_Error with user-friendly message.
	 */
	protected function handle_api_error( WP_Error $error, string $endpoint = '' ): WP_Error {
		// Log the error.
		Debug::error( 'API Error', [
			'provider' => static::ID,
			'endpoint' => $endpoint,
			'code'     => $error->get_error_code(),
			'message'  => $error->get_error_message(),
		] );

		// Create an exception for logging.
		$exception = APIException::from_wp_error( $error, static::ID, $endpoint );
		$exception->log();

		// Return user-friendly error.
		return ErrorMessages::translate_wp_error( $error );
	}

	/**
	 * Create a standardized API error.
	 *
	 * @param string $code    The error code.
	 * @param string $message The error message.
	 * @param array  $data    Additional error data.
	 * @return WP_Error The WP_Error object.
	 */
	protected function create_api_error( string $code, string $message, array $data = [] ): WP_Error {
		$data['provider'] = static::ID;

		$error = new WP_Error( $code, $message, $data );

		return $this->handle_api_error( $error );
	}

	/**
	 * Make an HTTP request with built-in error handling.
	 *
	 * @param string $url     The URL to request.
	 * @param array  $args    Request arguments for wp_remote_request().
	 * @param string $feature The feature ID for tracking.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	protected function make_request( string $url, array $args = [], string $feature = '' ) {
		$this->start_request_timer( $url, $args );

		$response = wp_remote_request( $url, $args );

		$this->end_request_timer( $url, $response );

		if ( is_wp_error( $response ) ) {
			return $this->handle_api_error( $response, $url );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Handle HTTP errors.
		if ( $status_code >= 400 ) {
			$body    = wp_remote_retrieve_body( $response );
			$message = $this->parse_error_message( $body, $status_code );

			return $this->create_api_error(
				$this->get_error_code_from_status( $status_code ),
				$message,
				[
					'http_status' => $status_code,
					'body'        => $body,
				]
			);
		}

		return $response;
	}

	/**
	 * Parse an error message from an API response body.
	 *
	 * @param string $body        The response body.
	 * @param int    $status_code The HTTP status code.
	 * @return string The error message.
	 */
	protected function parse_error_message( string $body, int $status_code ): string {
		$decoded = json_decode( $body, true );

		if ( is_array( $decoded ) ) {
			// Common error message fields across providers.
			$message = $decoded['error']['message']
				?? $decoded['error']
				?? $decoded['message']
				?? $decoded['detail']
				?? '';

			if ( ! empty( $message ) ) {
				return is_string( $message ) ? $message : wp_json_encode( $message );
			}
		}

		// Default message based on status code.
		$messages = [
			400 => __( 'Bad request. Please check your input.', 'classifai' ),
			401 => __( 'Authentication failed. Please check your API credentials.', 'classifai' ),
			403 => __( 'Access forbidden. You may not have permission for this action.', 'classifai' ),
			404 => __( 'Resource not found.', 'classifai' ),
			429 => __( 'Too many requests. Please try again later.', 'classifai' ),
			500 => __( 'Server error. The API service may be temporarily unavailable.', 'classifai' ),
			502 => __( 'Bad gateway. The API service may be temporarily unavailable.', 'classifai' ),
			503 => __( 'Service unavailable. Please try again later.', 'classifai' ),
		];

		return $messages[ $status_code ] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'Request failed with status code %d.', 'classifai' ),
			$status_code
		);
	}

	/**
	 * Get an error code from an HTTP status code.
	 *
	 * @param int $status_code The HTTP status code.
	 * @return string The error code.
	 */
	protected function get_error_code_from_status( int $status_code ): string {
		$codes = [
			400 => 'bad_request',
			401 => 'authentication_failed',
			403 => 'access_forbidden',
			404 => 'not_found',
			429 => 'rate_limit_exceeded',
			500 => 'server_error',
			502 => 'bad_gateway',
			503 => 'service_unavailable',
		];

		return $codes[ $status_code ] ?? 'http_error_' . $status_code;
	}

	/**
	 * Validate that required settings are configured.
	 *
	 * @param array $required_fields Array of required field names.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	protected function validate_settings( array $required_fields = [ 'api_key' ] ) {
		$settings = $this->feature_instance ? $this->feature_instance->get_settings() : [];
		$provider_settings = $settings[ static::ID ] ?? [];

		$missing = [];

		foreach ( $required_fields as $field ) {
			if ( empty( $provider_settings[ $field ] ) ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'missing_settings',
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Missing required settings: %s', 'classifai' ),
					implode( ', ', $missing )
				)
			);
		}

		return true;
	}

	/**
	 * Get a provider setting value.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed The setting value.
	 */
	protected function get_setting( string $key, $default = '' ) {
		$settings = $this->feature_instance ? $this->feature_instance->get_settings() : [];
		$provider_settings = $settings[ static::ID ] ?? [];

		return $provider_settings[ $key ] ?? $default;
	}
}
