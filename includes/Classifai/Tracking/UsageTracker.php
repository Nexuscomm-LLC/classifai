<?php
/**
 * Usage Tracker class for monitoring API usage.
 *
 * @package Classifai\Tracking
 * @since 3.8.0
 */

namespace Classifai\Tracking;

/**
 * UsageTracker class.
 *
 * Provides functionality to track and report on API usage across
 * all providers and features. Helps users understand their costs.
 *
 * @since 3.8.0
 */
class UsageTracker {

	/**
	 * Option name for storing usage data.
	 *
	 * @var string
	 */
	const USAGE_OPTION = 'classifai_api_usage';

	/**
	 * Option name for storing daily aggregates.
	 *
	 * @var string
	 */
	const DAILY_OPTION = 'classifai_daily_usage';

	/**
	 * Maximum entries to store in raw usage log.
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 1000;

	/**
	 * Cost estimates per 1000 tokens/requests (in USD).
	 * These are approximate and should be updated as pricing changes.
	 *
	 * @var array
	 */
	private static array $cost_estimates = [
		'openai_chatgpt' => [
			'gpt-4'         => [ 'input' => 0.03, 'output' => 0.06 ],
			'gpt-4-turbo'   => [ 'input' => 0.01, 'output' => 0.03 ],
			'gpt-3.5-turbo' => [ 'input' => 0.0005, 'output' => 0.0015 ],
		],
		'openai_embeddings' => [
			'text-embedding-3-small' => [ 'input' => 0.00002 ],
			'text-embedding-3-large' => [ 'input' => 0.00013 ],
		],
		'openai_dalle' => [
			'dall-e-3' => [ 'per_image' => 0.04 ],
			'dall-e-2' => [ 'per_image' => 0.02 ],
		],
	];

	/**
	 * Log an API request.
	 *
	 * @param string $provider The provider ID.
	 * @param string $feature  The feature ID.
	 * @param array  $meta     {
	 *     Metadata about the request.
	 *
	 *     @type int    $input_tokens  Number of input tokens.
	 *     @type int    $output_tokens Number of output tokens.
	 *     @type string $model         The model used.
	 *     @type bool   $success       Whether the request succeeded.
	 *     @type int    $post_id       The post ID if applicable.
	 * }
	 * @return void
	 */
	public static function log_request( string $provider, string $feature, array $meta = [] ): void {
		$usage = get_option( self::USAGE_OPTION, [] );

		$entry = [
			'timestamp'     => time(),
			'provider'      => $provider,
			'feature'       => $feature,
			'model'         => $meta['model'] ?? '',
			'input_tokens'  => $meta['input_tokens'] ?? 0,
			'output_tokens' => $meta['output_tokens'] ?? 0,
			'success'       => $meta['success'] ?? true,
			'post_id'       => $meta['post_id'] ?? 0,
			'user_id'       => get_current_user_id(),
		];

		$usage[] = $entry;

		// Keep only the most recent entries.
		if ( count( $usage ) > self::MAX_ENTRIES ) {
			$usage = array_slice( $usage, -self::MAX_ENTRIES );
		}

		update_option( self::USAGE_OPTION, $usage, false );

		// Update daily aggregates.
		self::update_daily_aggregate( $entry );

		/**
		 * Fires when an API request is logged.
		 *
		 * @since 3.8.0
		 * @hook classifai_usage_logged
		 *
		 * @param array $entry The usage entry.
		 */
		do_action( 'classifai_usage_logged', $entry );
	}

	/**
	 * Update daily aggregate statistics.
	 *
	 * @param array $entry The usage entry.
	 * @return void
	 */
	private static function update_daily_aggregate( array $entry ): void {
		$daily = get_option( self::DAILY_OPTION, [] );
		$date  = gmdate( 'Y-m-d', $entry['timestamp'] );

		if ( ! isset( $daily[ $date ] ) ) {
			$daily[ $date ] = [
				'requests'      => 0,
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'successes'     => 0,
				'failures'      => 0,
				'providers'     => [],
				'features'      => [],
			];
		}

		++$daily[ $date ]['requests'];
		$daily[ $date ]['input_tokens']  += $entry['input_tokens'];
		$daily[ $date ]['output_tokens'] += $entry['output_tokens'];

		if ( $entry['success'] ) {
			++$daily[ $date ]['successes'];
		} else {
			++$daily[ $date ]['failures'];
		}

		// Track provider usage.
		if ( ! isset( $daily[ $date ]['providers'][ $entry['provider'] ] ) ) {
			$daily[ $date ]['providers'][ $entry['provider'] ] = 0;
		}
		++$daily[ $date ]['providers'][ $entry['provider'] ];

		// Track feature usage.
		if ( ! isset( $daily[ $date ]['features'][ $entry['feature'] ] ) ) {
			$daily[ $date ]['features'][ $entry['feature'] ] = 0;
		}
		++$daily[ $date ]['features'][ $entry['feature'] ];

		// Keep only last 90 days.
		$cutoff = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
		$daily  = array_filter(
			$daily,
			function ( $key ) use ( $cutoff ) {
				return $key >= $cutoff;
			},
			ARRAY_FILTER_USE_KEY
		);

		update_option( self::DAILY_OPTION, $daily, false );
	}

	/**
	 * Get usage statistics for a period.
	 *
	 * @param string $period The period ('today', 'week', 'month', 'all').
	 * @return array Usage statistics.
	 */
	public static function get_stats( string $period = 'month' ): array {
		$daily = get_option( self::DAILY_OPTION, [] );

		$start_date = self::get_period_start( $period );
		$stats      = [
			'period'         => $period,
			'requests'       => 0,
			'input_tokens'   => 0,
			'output_tokens'  => 0,
			'total_tokens'   => 0,
			'successes'      => 0,
			'failures'       => 0,
			'success_rate'   => 0,
			'providers'      => [],
			'features'       => [],
			'estimated_cost' => 0,
		];

		foreach ( $daily as $date => $day_stats ) {
			if ( $date < $start_date ) {
				continue;
			}

			$stats['requests']       += $day_stats['requests'];
			$stats['input_tokens']   += $day_stats['input_tokens'];
			$stats['output_tokens']  += $day_stats['output_tokens'];
			$stats['successes']      += $day_stats['successes'];
			$stats['failures']       += $day_stats['failures'];

			// Aggregate providers.
			foreach ( $day_stats['providers'] as $provider => $count ) {
				if ( ! isset( $stats['providers'][ $provider ] ) ) {
					$stats['providers'][ $provider ] = 0;
				}
				$stats['providers'][ $provider ] += $count;
			}

			// Aggregate features.
			foreach ( $day_stats['features'] as $feature => $count ) {
				if ( ! isset( $stats['features'][ $feature ] ) ) {
					$stats['features'][ $feature ] = 0;
				}
				$stats['features'][ $feature ] += $count;
			}
		}

		$stats['total_tokens'] = $stats['input_tokens'] + $stats['output_tokens'];

		if ( $stats['requests'] > 0 ) {
			$stats['success_rate'] = round( $stats['successes'] / $stats['requests'] * 100, 2 );
		}

		// Sort providers and features by usage.
		arsort( $stats['providers'] );
		arsort( $stats['features'] );

		return $stats;
	}

	/**
	 * Get usage by provider.
	 *
	 * @param string $provider The provider ID.
	 * @param string $period   The period.
	 * @return array Provider usage statistics.
	 */
	public static function get_provider_stats( string $provider, string $period = 'month' ): array {
		$usage      = get_option( self::USAGE_OPTION, [] );
		$start_time = strtotime( self::get_period_start( $period ) );

		$provider_usage = array_filter(
			$usage,
			function ( $entry ) use ( $provider, $start_time ) {
				return $entry['provider'] === $provider && $entry['timestamp'] >= $start_time;
			}
		);

		$stats = [
			'provider'       => $provider,
			'requests'       => count( $provider_usage ),
			'input_tokens'   => array_sum( array_column( $provider_usage, 'input_tokens' ) ),
			'output_tokens'  => array_sum( array_column( $provider_usage, 'output_tokens' ) ),
			'models'         => [],
			'estimated_cost' => 0,
		];

		// Aggregate by model.
		foreach ( $provider_usage as $entry ) {
			$model = $entry['model'] ?: 'unknown';
			if ( ! isset( $stats['models'][ $model ] ) ) {
				$stats['models'][ $model ] = [
					'requests'      => 0,
					'input_tokens'  => 0,
					'output_tokens' => 0,
				];
			}

			++$stats['models'][ $model ]['requests'];
			$stats['models'][ $model ]['input_tokens']  += $entry['input_tokens'];
			$stats['models'][ $model ]['output_tokens'] += $entry['output_tokens'];
		}

		// Estimate costs.
		$stats['estimated_cost'] = self::estimate_cost( $provider, $stats );

		return $stats;
	}

	/**
	 * Estimate the cost of usage.
	 *
	 * @param string $provider The provider ID.
	 * @param array  $stats    Usage statistics.
	 * @return float Estimated cost in USD.
	 */
	private static function estimate_cost( string $provider, array $stats ): float {
		if ( ! isset( self::$cost_estimates[ $provider ] ) ) {
			return 0.0;
		}

		$total = 0.0;
		$rates = self::$cost_estimates[ $provider ];

		foreach ( $stats['models'] as $model => $model_stats ) {
			if ( ! isset( $rates[ $model ] ) ) {
				continue;
			}

			$model_rates = $rates[ $model ];

			if ( isset( $model_rates['input'] ) ) {
				$total += ( $model_stats['input_tokens'] / 1000 ) * $model_rates['input'];
			}

			if ( isset( $model_rates['output'] ) ) {
				$total += ( $model_stats['output_tokens'] / 1000 ) * $model_rates['output'];
			}

			if ( isset( $model_rates['per_image'] ) ) {
				$total += $model_stats['requests'] * $model_rates['per_image'];
			}
		}

		return round( $total, 4 );
	}

	/**
	 * Get the start date for a period.
	 *
	 * @param string $period The period.
	 * @return string Date in Y-m-d format.
	 */
	private static function get_period_start( string $period ): string {
		switch ( $period ) {
			case 'today':
				return gmdate( 'Y-m-d' );
			case 'week':
				return gmdate( 'Y-m-d', strtotime( '-7 days' ) );
			case 'month':
				return gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			case 'all':
			default:
				return '1970-01-01';
		}
	}

	/**
	 * Clear usage data.
	 *
	 * @param string|null $period Period to clear (null for all).
	 * @return bool True if cleared successfully.
	 */
	public static function clear( ?string $period = null ): bool {
		if ( null === $period ) {
			delete_option( self::USAGE_OPTION );
			delete_option( self::DAILY_OPTION );
			return true;
		}

		$start_time = strtotime( self::get_period_start( $period ) );

		// Clear raw usage.
		$usage = get_option( self::USAGE_OPTION, [] );
		$usage = array_filter(
			$usage,
			function ( $entry ) use ( $start_time ) {
				return $entry['timestamp'] < $start_time;
			}
		);
		update_option( self::USAGE_OPTION, $usage, false );

		// Clear daily aggregates.
		$daily      = get_option( self::DAILY_OPTION, [] );
		$start_date = self::get_period_start( $period );
		$daily      = array_filter(
			$daily,
			function ( $key ) use ( $start_date ) {
				return $key < $start_date;
			},
			ARRAY_FILTER_USE_KEY
		);
		update_option( self::DAILY_OPTION, $daily, false );

		return true;
	}

	/**
	 * Export usage data as CSV.
	 *
	 * @param string $period The period to export.
	 * @return string CSV content.
	 */
	public static function export_csv( string $period = 'month' ): string {
		$usage      = get_option( self::USAGE_OPTION, [] );
		$start_time = strtotime( self::get_period_start( $period ) );

		$filtered = array_filter(
			$usage,
			function ( $entry ) use ( $start_time ) {
				return $entry['timestamp'] >= $start_time;
			}
		);

		$csv = "timestamp,date,provider,feature,model,input_tokens,output_tokens,success,user_id,post_id\n";

		foreach ( $filtered as $entry ) {
			$csv .= sprintf(
				"%d,%s,%s,%s,%s,%d,%d,%s,%d,%d\n",
				$entry['timestamp'],
				gmdate( 'Y-m-d H:i:s', $entry['timestamp'] ),
				$entry['provider'],
				$entry['feature'],
				$entry['model'],
				$entry['input_tokens'],
				$entry['output_tokens'],
				$entry['success'] ? 'true' : 'false',
				$entry['user_id'],
				$entry['post_id']
			);
		}

		return $csv;
	}
}
