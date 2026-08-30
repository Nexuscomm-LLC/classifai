<?php
/**
 * Response Cache class for caching API responses.
 *
 * @package Classifai\Cache
 * @since 3.8.0
 */

namespace Classifai\Cache;

/**
 * ResponseCache class.
 *
 * Provides caching functionality for API responses to reduce
 * external API calls and improve performance.
 *
 * @since 3.8.0
 */
class ResponseCache {

	/**
	 * Cache prefix for all ClassifAI cache keys.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'classifai_cache_';

	/**
	 * Default cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const DEFAULT_TTL = 3600;

	/**
	 * Maximum cache TTL in seconds (24 hours).
	 *
	 * @var int
	 */
	const MAX_TTL = 86400;

	/**
	 * Get a cached response.
	 *
	 * @param string $feature The feature ID (e.g., 'title_generation').
	 * @param string $hash    A hash representing the request parameters.
	 * @return mixed|false The cached data or false if not found.
	 */
	public static function get( string $feature, string $hash ) {
		$key    = self::build_key( $feature, $hash );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			/**
			 * Fires when a cache hit occurs.
			 *
			 * @since 3.8.0
			 * @hook classifai_cache_hit
			 *
			 * @param string $feature The feature ID.
			 * @param string $hash    The request hash.
			 */
			do_action( 'classifai_cache_hit', $feature, $hash );
		}

		return $cached;
	}

	/**
	 * Set a cached response.
	 *
	 * @param string $feature The feature ID.
	 * @param string $hash    A hash representing the request parameters.
	 * @param mixed  $data    The data to cache.
	 * @param int    $ttl     Time to live in seconds. Default 3600 (1 hour).
	 * @return bool True if the cache was set successfully.
	 */
	public static function set( string $feature, string $hash, $data, int $ttl = self::DEFAULT_TTL ): bool {
		// Enforce maximum TTL.
		$ttl = min( $ttl, self::MAX_TTL );

		/**
		 * Filter the cache TTL.
		 *
		 * @since 3.8.0
		 * @hook classifai_cache_ttl
		 *
		 * @param int    $ttl     The cache TTL in seconds.
		 * @param string $feature The feature ID.
		 *
		 * @return int Filtered TTL.
		 */
		$ttl = apply_filters( 'classifai_cache_ttl', $ttl, $feature );

		$key = self::build_key( $feature, $hash );

		return set_transient( $key, $data, $ttl );
	}

	/**
	 * Delete a cached response.
	 *
	 * @param string $feature The feature ID.
	 * @param string $hash    A hash representing the request parameters.
	 * @return bool True if the cache was deleted successfully.
	 */
	public static function delete( string $feature, string $hash ): bool {
		$key = self::build_key( $feature, $hash );

		return delete_transient( $key );
	}

	/**
	 * Clear all cache for a specific feature.
	 *
	 * @param string $feature The feature ID.
	 * @return int Number of cache entries deleted.
	 */
	public static function clear_feature( string $feature ): int {
		global $wpdb;

		$prefix = '_transient_' . self::CACHE_PREFIX . $feature . '_';
		$count  = 0;

		// Get all transients matching the pattern.
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
	 * Clear all ClassifAI cache.
	 *
	 * @return int Number of cache entries deleted.
	 */
	public static function clear_all(): int {
		global $wpdb;

		$prefix = '_transient_' . self::CACHE_PREFIX;
		$count  = 0;

		// Get all transients matching the pattern.
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
	 * Build a cache key.
	 *
	 * @param string $feature The feature ID.
	 * @param string $hash    The request hash.
	 * @return string The cache key.
	 */
	private static function build_key( string $feature, string $hash ): string {
		return self::CACHE_PREFIX . $feature . '_' . $hash;
	}

	/**
	 * Generate a hash for request parameters.
	 *
	 * @param mixed ...$args Arguments to hash.
	 * @return string MD5 hash of the arguments.
	 */
	public static function generate_hash( ...$args ): string {
		return md5( wp_json_encode( $args ) );
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool True if caching is enabled.
	 */
	public static function is_enabled(): bool {
		/**
		 * Filter whether caching is enabled.
		 *
		 * @since 3.8.0
		 * @hook classifai_cache_enabled
		 *
		 * @param bool $enabled Whether caching is enabled. Default true.
		 *
		 * @return bool Filtered value.
		 */
		return apply_filters( 'classifai_cache_enabled', true );
	}

	/**
	 * Get or set a cached value using a callback.
	 *
	 * @param string   $feature  The feature ID.
	 * @param string   $hash     The request hash.
	 * @param callable $callback Callback to generate the value if not cached.
	 * @param int      $ttl      Time to live in seconds.
	 * @return mixed The cached or generated value.
	 */
	public static function remember( string $feature, string $hash, callable $callback, int $ttl = self::DEFAULT_TTL ) {
		if ( ! self::is_enabled() ) {
			return $callback();
		}

		$cached = self::get( $feature, $hash );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();

		// Don't cache WP_Error objects.
		if ( ! is_wp_error( $value ) ) {
			self::set( $feature, $hash, $value, $ttl );
		}

		return $value;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public static function get_stats(): array {
		global $wpdb;

		$prefix = '_transient_' . self::CACHE_PREFIX;

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		return [
			'total_entries' => (int) $count,
			'prefix'        => self::CACHE_PREFIX,
		];
	}
}
