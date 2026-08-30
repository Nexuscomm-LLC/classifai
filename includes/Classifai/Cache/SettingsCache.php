<?php
/**
 * Settings Cache class for optimizing database queries.
 *
 * @package Classifai\Cache
 * @since 3.8.0
 */

namespace Classifai\Cache;

/**
 * SettingsCache class.
 *
 * Provides in-memory caching for settings to reduce database queries
 * during a single request lifecycle.
 *
 * @since 3.8.0
 */
class SettingsCache {

	/**
	 * In-memory cache for settings.
	 *
	 * @var array
	 */
	private static array $cache = [];

	/**
	 * Track the number of cache hits.
	 *
	 * @var int
	 */
	private static int $hits = 0;

	/**
	 * Track the number of cache misses.
	 *
	 * @var int
	 */
	private static int $misses = 0;

	/**
	 * Get a cached option value.
	 *
	 * @param string $option_name The option name.
	 * @param mixed  $default     Default value if option doesn't exist.
	 * @return mixed The option value.
	 */
	public static function get( string $option_name, $default = [] ) {
		if ( isset( self::$cache[ $option_name ] ) ) {
			++self::$hits;
			return self::$cache[ $option_name ];
		}

		++self::$misses;
		$value = get_option( $option_name, $default );

		self::$cache[ $option_name ] = $value;

		return $value;
	}

	/**
	 * Update a cached option value.
	 *
	 * @param string $option_name The option name.
	 * @param mixed  $value       The value to set.
	 * @param bool   $autoload    Whether to autoload the option.
	 * @return bool True if the option was updated successfully.
	 */
	public static function set( string $option_name, $value, bool $autoload = true ): bool {
		$result = update_option( $option_name, $value, $autoload );

		if ( $result ) {
			self::$cache[ $option_name ] = $value;
		}

		return $result;
	}

	/**
	 * Delete a cached option.
	 *
	 * @param string $option_name The option name.
	 * @return bool True if the option was deleted successfully.
	 */
	public static function delete( string $option_name ): bool {
		$result = delete_option( $option_name );

		if ( $result ) {
			unset( self::$cache[ $option_name ] );
		}

		return $result;
	}

	/**
	 * Invalidate a cached option.
	 *
	 * Forces the next get() call to fetch from the database.
	 *
	 * @param string $option_name The option name.
	 * @return void
	 */
	public static function invalidate( string $option_name ): void {
		unset( self::$cache[ $option_name ] );
	}

	/**
	 * Clear the entire settings cache.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = [];
	}

	/**
	 * Preload multiple options into the cache.
	 *
	 * Useful for reducing database queries by loading all
	 * needed options in a single query.
	 *
	 * @param array $option_names Array of option names to preload.
	 * @return void
	 */
	public static function preload( array $option_names ): void {
		// Filter out already cached options.
		$to_load = array_filter(
			$option_names,
			function ( $name ) {
				return ! isset( self::$cache[ $name ] );
			}
		);

		if ( empty( $to_load ) ) {
			return;
		}

		// Use WordPress function to load options efficiently.
		wp_load_alloptions();

		foreach ( $to_load as $option_name ) {
			self::$cache[ $option_name ] = get_option( $option_name, [] );
		}
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public static function get_stats(): array {
		$total = self::$hits + self::$misses;

		return [
			'hits'      => self::$hits,
			'misses'    => self::$misses,
			'hit_ratio' => $total > 0 ? round( self::$hits / $total * 100, 2 ) : 0,
			'entries'   => count( self::$cache ),
		];
	}

	/**
	 * Check if an option is cached.
	 *
	 * @param string $option_name The option name.
	 * @return bool True if the option is cached.
	 */
	public static function has( string $option_name ): bool {
		return isset( self::$cache[ $option_name ] );
	}

	/**
	 * Get all cached option names.
	 *
	 * @return array Array of cached option names.
	 */
	public static function get_cached_keys(): array {
		return array_keys( self::$cache );
	}
}
