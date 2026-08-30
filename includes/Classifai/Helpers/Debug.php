<?php
/**
 * Debug helper class for development and troubleshooting.
 *
 * @package Classifai\Helpers
 * @since 3.8.0
 */

namespace Classifai\Helpers;

/**
 * Debug class.
 *
 * Provides debugging utilities for ClassifAI development and troubleshooting.
 * Only active when CLASSIFAI_DEBUG is enabled.
 *
 * @since 3.8.0
 */
class Debug {

	/**
	 * Debug log entries stored in memory.
	 *
	 * @var array
	 */
	private static array $log = [];

	/**
	 * Maximum entries to keep in memory.
	 *
	 * @var int
	 */
	const MAX_MEMORY_ENTRIES = 100;

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool True if debug mode is enabled.
	 */
	public static function is_enabled(): bool {
		return defined( 'CLASSIFAI_DEBUG' ) && CLASSIFAI_DEBUG;
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message  The debug message.
	 * @param array  $context  Additional context.
	 * @param string $level    Log level (debug, info, warning, error).
	 * @return void
	 */
	public static function log( string $message, array $context = [], string $level = 'debug' ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$entry = [
			'timestamp' => microtime( true ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'memory'    => memory_get_usage( true ),
			'backtrace' => self::get_backtrace(),
		];

		// Store in memory.
		self::$log[] = $entry;

		// Keep only recent entries.
		if ( count( self::$log ) > self::MAX_MEMORY_ENTRIES ) {
			self::$log = array_slice( self::$log, -self::MAX_MEMORY_ENTRIES );
		}

		// Write to error log.
		$formatted = sprintf(
			'[ClassifAI] [%s] %s | %s',
			strtoupper( $level ),
			$message,
			wp_json_encode( $context )
		);

		error_log( $formatted ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public static function info( string $message, array $context = [] ): void {
		self::log( $message, $context, 'info' );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public static function warning( string $message, array $context = [] ): void {
		self::log( $message, $context, 'warning' );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public static function error( string $message, array $context = [] ): void {
		self::log( $message, $context, 'error' );
	}

	/**
	 * Log an API request.
	 *
	 * @param string $provider The provider ID.
	 * @param string $endpoint The API endpoint.
	 * @param array  $request  The request data (will be sanitized).
	 * @return void
	 */
	public static function log_api_request( string $provider, string $endpoint, array $request = [] ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Sanitize sensitive data.
		$sanitized = self::sanitize_request( $request );

		self::log(
			sprintf( 'API Request: %s - %s', $provider, $endpoint ),
			[
				'provider' => $provider,
				'endpoint' => $endpoint,
				'request'  => $sanitized,
			],
			'info'
		);
	}

	/**
	 * Log an API response.
	 *
	 * @param string $provider     The provider ID.
	 * @param string $endpoint     The API endpoint.
	 * @param mixed  $response     The response data.
	 * @param float  $duration     Request duration in seconds.
	 * @return void
	 */
	public static function log_api_response( string $provider, string $endpoint, $response, float $duration = 0 ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$level = is_wp_error( $response ) ? 'error' : 'info';

		self::log(
			sprintf( 'API Response: %s - %s (%.3fs)', $provider, $endpoint, $duration ),
			[
				'provider' => $provider,
				'endpoint' => $endpoint,
				'duration' => $duration,
				'success'  => ! is_wp_error( $response ),
				'response' => is_wp_error( $response ) ? $response->get_error_message() : 'OK',
			],
			$level
		);
	}

	/**
	 * Start a timer for measuring execution time.
	 *
	 * @param string $label A label for the timer.
	 * @return float The start time.
	 */
	public static function start_timer( string $label ): float {
		$start = microtime( true );

		if ( self::is_enabled() ) {
			self::log( sprintf( 'Timer started: %s', $label ), [], 'debug' );
		}

		return $start;
	}

	/**
	 * End a timer and log the duration.
	 *
	 * @param string $label      A label for the timer.
	 * @param float  $start_time The start time from start_timer().
	 * @return float The duration in seconds.
	 */
	public static function end_timer( string $label, float $start_time ): float {
		$duration = microtime( true ) - $start_time;

		if ( self::is_enabled() ) {
			self::log(
				sprintf( 'Timer ended: %s (%.3fs)', $label, $duration ),
				[ 'duration' => $duration ],
				'debug'
			);
		}

		return $duration;
	}

	/**
	 * Get the in-memory log entries.
	 *
	 * @param string|null $level Filter by level.
	 * @return array Log entries.
	 */
	public static function get_log( ?string $level = null ): array {
		if ( null === $level ) {
			return self::$log;
		}

		return array_filter(
			self::$log,
			function ( $entry ) use ( $level ) {
				return $entry['level'] === $level;
			}
		);
	}

	/**
	 * Clear the in-memory log.
	 *
	 * @return void
	 */
	public static function clear_log(): void {
		self::$log = [];
	}

	/**
	 * Dump a variable for debugging.
	 *
	 * @param mixed  $var   The variable to dump.
	 * @param string $label Optional label.
	 * @return void
	 */
	public static function dump( $var, string $label = '' ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$output = $label ? sprintf( '[%s] ', $label ) : '';
		$output .= print_r( $var, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		error_log( '[ClassifAI Debug] ' . $output ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Get a simplified backtrace.
	 *
	 * @return array Simplified backtrace.
	 */
	private static function get_backtrace(): array {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		// Remove the first two entries (this function and the caller).
		$backtrace = array_slice( $backtrace, 2 );

		return array_map(
			function ( $trace ) {
				return [
					'file'     => isset( $trace['file'] ) ? basename( $trace['file'] ) : 'unknown',
					'line'     => $trace['line'] ?? 0,
					'function' => $trace['function'] ?? 'unknown',
					'class'    => $trace['class'] ?? '',
				];
			},
			$backtrace
		);
	}

	/**
	 * Sanitize request data to remove sensitive information.
	 *
	 * @param array $request The request data.
	 * @return array Sanitized request data.
	 */
	private static function sanitize_request( array $request ): array {
		$sensitive_keys = [ 'api_key', 'apikey', 'key', 'secret', 'password', 'token', 'authorization' ];

		$sanitized = [];

		foreach ( $request as $key => $value ) {
			$lower_key = strtolower( $key );

			if ( in_array( $lower_key, $sensitive_keys, true ) ) {
				$sanitized[ $key ] = '[REDACTED]';
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_request( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Get memory usage information.
	 *
	 * @return array Memory usage stats.
	 */
	public static function get_memory_stats(): array {
		return [
			'current'     => memory_get_usage( true ),
			'peak'        => memory_get_peak_usage( true ),
			'current_mb'  => round( memory_get_usage( true ) / 1024 / 1024, 2 ),
			'peak_mb'     => round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ),
			'limit'       => ini_get( 'memory_limit' ),
		];
	}

	/**
	 * Add a debug notice to the admin area.
	 *
	 * @param string $message The message to display.
	 * @param string $type    Notice type (info, warning, error).
	 * @return void
	 */
	public static function admin_notice( string $message, string $type = 'info' ): void {
		if ( ! self::is_enabled() || ! is_admin() ) {
			return;
		}

		add_action(
			'admin_notices',
			function () use ( $message, $type ) {
				printf(
					'<div class="notice notice-%s"><p><strong>[ClassifAI Debug]</strong> %s</p></div>',
					esc_attr( $type ),
					esc_html( $message )
				);
			}
		);
	}
}
