<?php
/**
 * Tests for UsageTracker class.
 *
 * @package Classifai\Tests\Tracking
 */

namespace Classifai\Tests\Tracking;

use Classifai\Tracking\UsageTracker;
use WP_UnitTestCase;

/**
 * UsageTracker test case.
 *
 * @covers \Classifai\Tracking\UsageTracker
 */
class UsageTrackerTest extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		UsageTracker::clear();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		UsageTracker::clear();
		parent::tear_down();
	}

	/**
	 * Test logging a request.
	 */
	public function test_log_request() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [
			'input_tokens'  => 100,
			'output_tokens' => 50,
			'model'         => 'gpt-4',
			'success'       => true,
		] );

		$stats = UsageTracker::get_stats( 'today' );

		$this->assertEquals( 1, $stats['requests'] );
		$this->assertEquals( 100, $stats['input_tokens'] );
		$this->assertEquals( 50, $stats['output_tokens'] );
	}

	/**
	 * Test get_stats aggregation.
	 */
	public function test_get_stats_aggregation() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [
			'input_tokens'  => 100,
			'output_tokens' => 50,
		] );

		UsageTracker::log_request( 'openai_chatgpt', 'feature_excerpt_generation', [
			'input_tokens'  => 200,
			'output_tokens' => 100,
		] );

		$stats = UsageTracker::get_stats( 'today' );

		$this->assertEquals( 2, $stats['requests'] );
		$this->assertEquals( 300, $stats['input_tokens'] );
		$this->assertEquals( 150, $stats['output_tokens'] );
		$this->assertEquals( 450, $stats['total_tokens'] );
	}

	/**
	 * Test provider tracking.
	 */
	public function test_provider_tracking() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation' );
		UsageTracker::log_request( 'azure_openai', 'feature_title_generation' );
		UsageTracker::log_request( 'openai_chatgpt', 'feature_excerpt_generation' );

		$stats = UsageTracker::get_stats( 'today' );

		$this->assertArrayHasKey( 'openai_chatgpt', $stats['providers'] );
		$this->assertArrayHasKey( 'azure_openai', $stats['providers'] );
		$this->assertEquals( 2, $stats['providers']['openai_chatgpt'] );
		$this->assertEquals( 1, $stats['providers']['azure_openai'] );
	}

	/**
	 * Test feature tracking.
	 */
	public function test_feature_tracking() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation' );
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation' );
		UsageTracker::log_request( 'openai_chatgpt', 'feature_excerpt_generation' );

		$stats = UsageTracker::get_stats( 'today' );

		$this->assertEquals( 2, $stats['features']['feature_title_generation'] );
		$this->assertEquals( 1, $stats['features']['feature_excerpt_generation'] );
	}

	/**
	 * Test success rate calculation.
	 */
	public function test_success_rate() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [ 'success' => true ] );
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [ 'success' => true ] );
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [ 'success' => false ] );
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [ 'success' => false ] );

		$stats = UsageTracker::get_stats( 'today' );

		$this->assertEquals( 50, $stats['success_rate'] );
		$this->assertEquals( 2, $stats['successes'] );
		$this->assertEquals( 2, $stats['failures'] );
	}

	/**
	 * Test get_provider_stats.
	 */
	public function test_get_provider_stats() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [
			'input_tokens'  => 100,
			'output_tokens' => 50,
			'model'         => 'gpt-4',
		] );

		$stats = UsageTracker::get_provider_stats( 'openai_chatgpt', 'today' );

		$this->assertEquals( 1, $stats['requests'] );
		$this->assertEquals( 100, $stats['input_tokens'] );
		$this->assertArrayHasKey( 'gpt-4', $stats['models'] );
	}

	/**
	 * Test export_csv.
	 */
	public function test_export_csv() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [
			'input_tokens'  => 100,
			'output_tokens' => 50,
			'model'         => 'gpt-4',
		] );

		$csv = UsageTracker::export_csv( 'today' );

		$this->assertStringContainsString( 'timestamp', $csv );
		$this->assertStringContainsString( 'openai_chatgpt', $csv );
		$this->assertStringContainsString( 'feature_title_generation', $csv );
		$this->assertStringContainsString( 'gpt-4', $csv );
	}

	/**
	 * Test clear.
	 */
	public function test_clear() {
		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation' );

		$stats_before = UsageTracker::get_stats( 'today' );
		$this->assertEquals( 1, $stats_before['requests'] );

		UsageTracker::clear();

		$stats_after = UsageTracker::get_stats( 'today' );
		$this->assertEquals( 0, $stats_after['requests'] );
	}

	/**
	 * Test usage_logged action is fired.
	 */
	public function test_usage_logged_action() {
		$action_fired = false;
		$logged_entry = null;

		add_action( 'classifai_usage_logged', function( $entry ) use ( &$action_fired, &$logged_entry ) {
			$action_fired = true;
			$logged_entry = $entry;
		} );

		UsageTracker::log_request( 'openai_chatgpt', 'feature_title_generation', [
			'input_tokens' => 100,
		] );

		$this->assertTrue( $action_fired );
		$this->assertEquals( 'openai_chatgpt', $logged_entry['provider'] );
		$this->assertEquals( 100, $logged_entry['input_tokens'] );
	}
}
