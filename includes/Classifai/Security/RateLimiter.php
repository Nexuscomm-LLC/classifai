<?php
/**
 * Rate Limiter class for REST endpoint protection.
 *
 * @package Classifai\Security
 * @since 3.8.0
 */

namespace Classifai\Security;

use WP_Error;

/**
 * RateLimiter class.
 *
 * Provides rate limiting functionality to protect REST endpoints
 * from abuse and control API usage.
 *
 * @since 3.8.0
 */
class RateLimiter {

	/**
	 * Cache prefix for rate limit data.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'classifai_rate_limit_';

	/**
	 * Default rate limit (requests per window).
	 *
	 * @var int
	 */
	const DEFAULT_LIMIT = 60;

	/**
	 * Default time window in seconds (1 minute).
	 *
	 * @var int
	 */
	const DEFAULT_WINDOW = 60;

	/**
	 * Feature-specific rate limits.
	 *
	 * @var array
	 */
	private static array $feature_limits = [
		'feature_title_generation'           => [ 'limit' => 30, 'window' => 60 ],
		'feature_excerpt_generation'         => [ 'limit' => 30, 'window' => 60 ],
		'feature_content_generation'         => [ 'limit' => 20, 'window' => 60 ],
		'feature_image_generation'           => [ 'limit' => 10, 'window' => 60 ],
		'feature_classification'             => [ 'limit' => 50, 'window' => 60 ],
		'feature_descriptive_text_generator' => [ 'limit' => 30, 'window' => 60 ],
		'feature_text_to_speech'             => [ 'limit' => 20, 'window' => 60 ],
	];

	/**
	 * Check if the rate limit has been exceeded.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return bool True if rate limit is exceeded.
	 */
	public static function is_limited( int $user_id, string $feature ): bool {
		$limits    = self::get_limits( $feature );
		$key       = self::get_key( $user_id, $feature );
		$rate_data = self::get_rate_data( $key );

		return $rate_data['count'] >= $limits['limit'];
	}

	/**
	 * Check rate limit and return WP_Error if exceeded.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return true|WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check( int $user_id, string $feature ) {
		if ( ! self::is_enabled() ) {
			return true;
		}

		if ( self::is_limited( $user_id, $feature ) ) {
			$limits    = self::get_limits( $feature );
			$key       = self::get_key( $user_id, $feature );
			$rate_data = self::get_rate_data( $key );

			$retry_after = max( 0, $rate_data['reset'] - time() );

			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: seconds until rate limit resets */
					__( 'Rate limit exceeded. Please try again in %d seconds.', 'classifai' ),
					$retry_after
				),
				[
					'status'      => 429,
					'retry_after' => $retry_after,
					'limit'       => $limits['limit'],
					'remaining'   => 0,
				]
			);
		}

		return true;
	}

	/**
	 * Increment the rate limit counter.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return array Updated rate data.
	 */
	public static function increment( int $user_id, string $feature ): array {
		$limits    = self::get_limits( $feature );
		$key       = self::get_key( $user_id, $feature );
		$rate_data = self::get_rate_data( $key );

		// Reset if window has expired.
		if ( time() >= $rate_data['reset'] ) {
			$rate_data = [
				'count' => 0,
				'reset' => time() + $limits['window'],
			];
		}

		++$rate_data['count'];

		// Store the updated rate data.
		set_transient( $key, $rate_data, $limits['window'] );

		return $rate_data;
	}

	/**
	 * Get the remaining requests for a user/feature.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return int Remaining requests.
	 */
	public static function get_remaining( int $user_id, string $feature ): int {
		$limits    = self::get_limits( $feature );
		$key       = self::get_key( $user_id, $feature );
		$rate_data = self::get_rate_data( $key );

		return max( 0, $limits['limit'] - $rate_data['count'] );
	}

	/**
	 * Get the rate limit headers for a response.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return array Headers to add to the response.
	 */
	public static function get_headers( int $user_id, string $feature ): array {
		$limits    = self::get_limits( $feature );
		$key       = self::get_key( $user_id, $feature );
		$rate_data = self::get_rate_data( $key );

		return [
			'X-RateLimit-Limit'     => $limits['limit'],
			'X-RateLimit-Remaining' => max( 0, $limits['limit'] - $rate_data['count'] ),
			'X-RateLimit-Reset'     => $rate_data['reset'],
		];
	}

	/**
	 * Reset the rate limit for a user/feature.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return bool True if reset was successful.
	 */
	public static function reset( int $user_id, string $feature ): bool {
		$key = self::get_key( $user_id, $feature );

		return delete_transient( $key );
	}

	/**
	 * Reset all rate limits for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return int Number of rate limits reset.
	 */
	public static function reset_user( int $user_id ): int {
		global $wpdb;

		$prefix = '_transient_' . self::CACHE_PREFIX . $user_id . '_';
		$count  = 0;

		$transients = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			if ( delete_transient( $key ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get the rate limit configuration for a feature.
	 *
	 * @param string $feature The feature ID.
	 * @return array Rate limit configuration.
	 */
	private static function get_limits( string $feature ): array {
		$default = [
			'limit'  => self::DEFAULT_LIMIT,
			'window' => self::DEFAULT_WINDOW,
		];

		$limits = self::$feature_limits[ $feature ] ?? $default;

		/**
		 * Filter the rate limits for a feature.
		 *
		 * @since 3.8.0
		 * @hook classifai_rate_limits
		 *
		 * @param array  $limits  Rate limit configuration.
		 * @param string $feature The feature ID.
		 *
		 * @return array Filtered rate limits.
		 */
		return apply_filters( 'classifai_rate_limits', $limits, $feature );
	}

	/**
	 * Get the cache key for rate limiting.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $feature The feature ID.
	 * @return string The cache key.
	 */
	private static function get_key( int $user_id, string $feature ): string {
		return self::CACHE_PREFIX . $user_id . '_' . $feature;
	}

	/**
	 * Get the rate data from cache.
	 *
	 * @param string $key The cache key.
	 * @return array Rate data with count and reset time.
	 */
	private static function get_rate_data( string $key ): array {
		$data = get_transient( $key );

		if ( false === $data || ! is_array( $data ) ) {
			return [
				'count' => 0,
				'reset' => time() + self::DEFAULT_WINDOW,
			];
		}

		return $data;
	}

	/**
	 * Check if rate limiting is enabled.
	 *
	 * @return bool True if rate limiting is enabled.
	 */
	public static function is_enabled(): bool {
		/**
		 * Filter whether rate limiting is enabled.
		 *
		 * @since 3.8.0
		 * @hook classifai_rate_limiting_enabled
		 *
		 * @param bool $enabled Whether rate limiting is enabled. Default true.
		 *
		 * @return bool Filtered value.
		 */
		return apply_filters( 'classifai_rate_limiting_enabled', true );
	}

	/**
	 * Set custom rate limits for a feature.
	 *
	 * @param string $feature The feature ID.
	 * @param int    $limit   Maximum requests per window.
	 * @param int    $window  Time window in seconds.
	 * @return void
	 */
	public static function set_feature_limit( string $feature, int $limit, int $window ): void {
		self::$feature_limits[ $feature ] = [
			'limit'  => $limit,
			'window' => $window,
		];
	}
}
