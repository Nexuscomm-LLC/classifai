<?php
/**
 * Processing Queue class for batch operations.
 *
 * @package Classifai\Queue
 * @since 3.8.0
 */

namespace Classifai\Queue;

use WP_Error;

/**
 * ProcessingQueue class.
 *
 * Provides a queue system for handling batch AI processing operations
 * without causing timeouts on large datasets.
 *
 * @since 3.8.0
 */
class ProcessingQueue {

	/**
	 * Queue option name.
	 *
	 * @var string
	 */
	const QUEUE_OPTION = 'classifai_processing_queue';

	/**
	 * Jobs option name.
	 *
	 * @var string
	 */
	const JOBS_OPTION = 'classifai_queue_jobs';

	/**
	 * Default batch size.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 10;

	/**
	 * Queue statuses.
	 */
	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED  = 'completed';
	const STATUS_FAILED     = 'failed';

	/**
	 * Singleton instance.
	 *
	 * @var ProcessingQueue|null
	 */
	private static ?ProcessingQueue $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return ProcessingQueue
	 */
	public static function get_instance(): ProcessingQueue {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'classifai_process_queue', [ $this, 'process_batch' ] );
	}

	/**
	 * Add a job to the queue.
	 *
	 * @param string $feature The feature ID.
	 * @param int    $post_id The post ID to process.
	 * @param array  $args    Additional arguments for the job.
	 * @return int|WP_Error The job ID or WP_Error on failure.
	 */
	public function add_job( string $feature, int $post_id, array $args = [] ) {
		$jobs = get_option( self::JOBS_OPTION, [] );

		// Check if a job for this post/feature already exists.
		foreach ( $jobs as $job ) {
			if (
				$job['post_id'] === $post_id &&
				$job['feature'] === $feature &&
				in_array( $job['status'], [ self::STATUS_PENDING, self::STATUS_PROCESSING ], true )
			) {
				return new WP_Error(
					'duplicate_job',
					__( 'A job for this post is already in the queue.', 'classifai' )
				);
			}
		}

		$job_id = $this->generate_job_id();

		$job = [
			'id'         => $job_id,
			'feature'    => $feature,
			'post_id'    => $post_id,
			'args'       => $args,
			'status'     => self::STATUS_PENDING,
			'created_at' => time(),
			'updated_at' => time(),
			'attempts'   => 0,
			'error'      => null,
		];

		$jobs[ $job_id ] = $job;
		update_option( self::JOBS_OPTION, $jobs, false );

		// Schedule processing if not already scheduled.
		if ( ! wp_next_scheduled( 'classifai_process_queue' ) ) {
			wp_schedule_single_event( time(), 'classifai_process_queue' );
		}

		/**
		 * Fires when a job is added to the queue.
		 *
		 * @since 3.8.0
		 * @hook classifai_queue_job_added
		 *
		 * @param array $job The job data.
		 */
		do_action( 'classifai_queue_job_added', $job );

		return $job_id;
	}

	/**
	 * Add multiple jobs to the queue.
	 *
	 * @param string $feature  The feature ID.
	 * @param array  $post_ids Array of post IDs.
	 * @param array  $args     Additional arguments for all jobs.
	 * @return array Array of job IDs and errors.
	 */
	public function add_bulk_jobs( string $feature, array $post_ids, array $args = [] ): array {
		$results = [
			'jobs'   => [],
			'errors' => [],
		];

		foreach ( $post_ids as $post_id ) {
			$result = $this->add_job( $feature, (int) $post_id, $args );

			if ( is_wp_error( $result ) ) {
				$results['errors'][ $post_id ] = $result->get_error_message();
			} else {
				$results['jobs'][] = $result;
			}
		}

		return $results;
	}

	/**
	 * Process a batch of jobs.
	 *
	 * @param int $batch_size Number of jobs to process.
	 * @return array Processing results.
	 */
	public function process_batch( int $batch_size = self::DEFAULT_BATCH_SIZE ): array {
		$jobs    = get_option( self::JOBS_OPTION, [] );
		$results = [
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
		];

		// Get pending jobs.
		$pending = array_filter(
			$jobs,
			function ( $job ) {
				return self::STATUS_PENDING === $job['status'];
			}
		);

		// Sort by created_at.
		uasort(
			$pending,
			function ( $a, $b ) {
				return $a['created_at'] <=> $b['created_at'];
			}
		);

		// Take batch size.
		$batch = array_slice( $pending, 0, $batch_size, true );

		foreach ( $batch as $job_id => $job ) {
			$result = $this->process_job( $job );

			++$results['processed'];

			if ( is_wp_error( $result ) ) {
				++$results['failed'];
				$jobs[ $job_id ]['status']     = self::STATUS_FAILED;
				$jobs[ $job_id ]['error']      = $result->get_error_message();
				$jobs[ $job_id ]['updated_at'] = time();
				++$jobs[ $job_id ]['attempts'];
			} else {
				++$results['succeeded'];
				$jobs[ $job_id ]['status']     = self::STATUS_COMPLETED;
				$jobs[ $job_id ]['updated_at'] = time();
				++$jobs[ $job_id ]['attempts'];
			}
		}

		update_option( self::JOBS_OPTION, $jobs, false );

		// Schedule next batch if there are more pending jobs.
		$remaining_pending = array_filter(
			$jobs,
			function ( $job ) {
				return self::STATUS_PENDING === $job['status'];
			}
		);

		if ( ! empty( $remaining_pending ) && ! wp_next_scheduled( 'classifai_process_queue' ) ) {
			wp_schedule_single_event( time() + 10, 'classifai_process_queue' );
		}

		/**
		 * Fires when a batch has been processed.
		 *
		 * @since 3.8.0
		 * @hook classifai_queue_batch_processed
		 *
		 * @param array $results Processing results.
		 */
		do_action( 'classifai_queue_batch_processed', $results );

		return $results;
	}

	/**
	 * Process a single job.
	 *
	 * @param array $job The job data.
	 * @return mixed|WP_Error The result or WP_Error on failure.
	 */
	private function process_job( array $job ) {
		$feature_class = $this->get_feature_class( $job['feature'] );

		if ( ! $feature_class || ! class_exists( $feature_class ) ) {
			return new WP_Error(
				'invalid_feature',
				sprintf(
					/* translators: %s: feature ID */
					__( 'Invalid feature: %s', 'classifai' ),
					$job['feature']
				)
			);
		}

		$feature = new $feature_class();

		if ( ! method_exists( $feature, 'run' ) ) {
			return new WP_Error(
				'feature_not_runnable',
				__( 'Feature does not support queue processing.', 'classifai' )
			);
		}

		/**
		 * Fires before a job is processed.
		 *
		 * @since 3.8.0
		 * @hook classifai_queue_before_job
		 *
		 * @param array $job The job data.
		 */
		do_action( 'classifai_queue_before_job', $job );

		$result = $feature->run( $job['post_id'], $job['args'] );

		/**
		 * Fires after a job is processed.
		 *
		 * @since 3.8.0
		 * @hook classifai_queue_after_job
		 *
		 * @param array $job    The job data.
		 * @param mixed $result The processing result.
		 */
		do_action( 'classifai_queue_after_job', $job, $result );

		return $result;
	}

	/**
	 * Get the status of a job.
	 *
	 * @param int $job_id The job ID.
	 * @return array|null Job data or null if not found.
	 */
	public function get_job( int $job_id ): ?array {
		$jobs = get_option( self::JOBS_OPTION, [] );

		return $jobs[ $job_id ] ?? null;
	}

	/**
	 * Get all jobs with optional status filter.
	 *
	 * @param string|null $status Optional status to filter by.
	 * @return array Array of jobs.
	 */
	public function get_jobs( ?string $status = null ): array {
		$jobs = get_option( self::JOBS_OPTION, [] );

		if ( null === $status ) {
			return $jobs;
		}

		return array_filter(
			$jobs,
			function ( $job ) use ( $status ) {
				return $job['status'] === $status;
			}
		);
	}

	/**
	 * Cancel a pending job.
	 *
	 * @param int $job_id The job ID.
	 * @return bool True if job was cancelled.
	 */
	public function cancel_job( int $job_id ): bool {
		$jobs = get_option( self::JOBS_OPTION, [] );

		if ( ! isset( $jobs[ $job_id ] ) ) {
			return false;
		}

		if ( self::STATUS_PENDING !== $jobs[ $job_id ]['status'] ) {
			return false;
		}

		unset( $jobs[ $job_id ] );
		update_option( self::JOBS_OPTION, $jobs, false );

		return true;
	}

	/**
	 * Clear completed jobs.
	 *
	 * @param int $older_than Only clear jobs older than this many seconds.
	 * @return int Number of jobs cleared.
	 */
	public function clear_completed( int $older_than = 86400 ): int {
		$jobs    = get_option( self::JOBS_OPTION, [] );
		$count   = 0;
		$cutoff  = time() - $older_than;

		foreach ( $jobs as $job_id => $job ) {
			if (
				self::STATUS_COMPLETED === $job['status'] &&
				$job['updated_at'] < $cutoff
			) {
				unset( $jobs[ $job_id ] );
				++$count;
			}
		}

		update_option( self::JOBS_OPTION, $jobs, false );

		return $count;
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array Queue statistics.
	 */
	public function get_stats(): array {
		$jobs = get_option( self::JOBS_OPTION, [] );

		return [
			'total'      => count( $jobs ),
			'pending'    => count( $this->get_jobs( self::STATUS_PENDING ) ),
			'processing' => count( $this->get_jobs( self::STATUS_PROCESSING ) ),
			'completed'  => count( $this->get_jobs( self::STATUS_COMPLETED ) ),
			'failed'     => count( $this->get_jobs( self::STATUS_FAILED ) ),
		];
	}

	/**
	 * Generate a unique job ID.
	 *
	 * @return int The job ID.
	 */
	private function generate_job_id(): int {
		$counter = (int) get_option( 'classifai_job_counter', 0 );
		++$counter;
		update_option( 'classifai_job_counter', $counter, false );

		return $counter;
	}

	/**
	 * Get the feature class for a feature ID.
	 *
	 * @param string $feature_id The feature ID.
	 * @return string|null The feature class name or null.
	 */
	private function get_feature_class( string $feature_id ): ?string {
		$map = [
			'feature_title_generation'           => 'Classifai\Features\TitleGeneration',
			'feature_excerpt_generation'         => 'Classifai\Features\ExcerptGeneration',
			'feature_content_generation'         => 'Classifai\Features\ContentGeneration',
			'feature_classification'             => 'Classifai\Features\Classification',
			'feature_descriptive_text_generator' => 'Classifai\Features\DescriptiveTextGenerator',
			'feature_image_tags_generator'       => 'Classifai\Features\ImageTagsGenerator',
		];

		return $map[ $feature_id ] ?? null;
	}
}
