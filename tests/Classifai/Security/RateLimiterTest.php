<?php
/**
 * Tests for RateLimiter class.
 *
 * @package Classifai\Tests\Security
 */

namespace Classifai\Tests\Security;

use Classifai\Security\RateLimiter;
use WP_UnitTestCase;

/**
 * RateLimiter test case.
 *
 * @covers \Classifai\Security\RateLimiter
 */
class RateLimiterTest extends WP_UnitTestCase {

	/**
	 * Test user ID for tests.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		RateLimiter::reset_user( $this->user_id );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		RateLimiter::reset_user( $this->user_id );
		parent::tear_down();
	}

	/**
	 * Test is_limited returns false initially.
	 */
	public function test_not_limited_initially() {
		$result = RateLimiter::is_limited( $this->user_id, 'feature_title_generation' );
		$this->assertFalse( $result );
	}

	/**
	 * Test increment increases count.
	 */
	public function test_increment() {
		$feature = 'feature_title_generation';

		$data1 = RateLimiter::increment( $this->user_id, $feature );
		$this->assertEquals( 1, $data1['count'] );

		$data2 = RateLimiter::increment( $this->user_id, $feature );
		$this->assertEquals( 2, $data2['count'] );
	}

	/**
	 * Test get_remaining decreases with increments.
	 */
	public function test_get_remaining() {
		$feature   = 'feature_title_generation';
		$initial   = RateLimiter::get_remaining( $this->user_id, $feature );

		RateLimiter::increment( $this->user_id, $feature );
		$after_one = RateLimiter::get_remaining( $this->user_id, $feature );

		$this->assertEquals( $initial - 1, $after_one );
	}

	/**
	 * Test check returns true when not limited.
	 */
	public function test_check_returns_true() {
		$result = RateLimiter::check( $this->user_id, 'feature_title_generation' );
		$this->assertTrue( $result );
	}

	/**
	 * Test check returns WP_Error when limited.
	 */
	public function test_check_returns_error_when_limited() {
		$feature = 'feature_title_generation';

		// Set a very low limit for testing
		RateLimiter::set_feature_limit( $feature, 2, 60 );

		RateLimiter::increment( $this->user_id, $feature );
		RateLimiter::increment( $this->user_id, $feature );

		$result = RateLimiter::check( $this->user_id, $feature );

		$this->assertWPError( $result );
		$this->assertEquals( 'rate_limit_exceeded', $result->get_error_code() );
	}

	/**
	 * Test reset clears the rate limit.
	 */
	public function test_reset() {
		$feature = 'feature_title_generation';

		RateLimiter::increment( $this->user_id, $feature );
		RateLimiter::increment( $this->user_id, $feature );

		$before = RateLimiter::get_remaining( $this->user_id, $feature );

		RateLimiter::reset( $this->user_id, $feature );

		$after = RateLimiter::get_remaining( $this->user_id, $feature );

		$this->assertGreaterThan( $before, $after );
	}

	/**
	 * Test get_headers returns proper headers.
	 */
	public function test_get_headers() {
		$feature = 'feature_title_generation';

		RateLimiter::increment( $this->user_id, $feature );

		$headers = RateLimiter::get_headers( $this->user_id, $feature );

		$this->assertArrayHasKey( 'X-RateLimit-Limit', $headers );
		$this->assertArrayHasKey( 'X-RateLimit-Remaining', $headers );
		$this->assertArrayHasKey( 'X-RateLimit-Reset', $headers );
	}

	/**
	 * Test is_enabled filter.
	 */
	public function test_is_enabled_filter() {
		$this->assertTrue( RateLimiter::is_enabled() );

		add_filter( 'classifai_rate_limiting_enabled', '__return_false' );
		$this->assertFalse( RateLimiter::is_enabled() );
		remove_filter( 'classifai_rate_limiting_enabled', '__return_false' );
	}

	/**
	 * Test check bypasses when disabled.
	 */
	public function test_check_bypassed_when_disabled() {
		$feature = 'feature_title_generation';

		// Set a very low limit
		RateLimiter::set_feature_limit( $feature, 1, 60 );
		RateLimiter::increment( $this->user_id, $feature );

		// Should be limited
		$this->assertTrue( RateLimiter::is_limited( $this->user_id, $feature ) );

		// Disable rate limiting
		add_filter( 'classifai_rate_limiting_enabled', '__return_false' );

		// Check should return true (bypass)
		$result = RateLimiter::check( $this->user_id, $feature );
		$this->assertTrue( $result );

		remove_filter( 'classifai_rate_limiting_enabled', '__return_false' );
	}

	/**
	 * Test custom rate limits via filter.
	 */
	public function test_rate_limits_filter() {
		$feature = 'feature_title_generation';

		add_filter( 'classifai_rate_limits', function( $limits, $feat ) use ( $feature ) {
			if ( $feat === $feature ) {
				$limits['limit'] = 100;
			}
			return $limits;
		}, 10, 2 );

		$remaining = RateLimiter::get_remaining( $this->user_id, $feature );
		$this->assertEquals( 100, $remaining );

		remove_all_filters( 'classifai_rate_limits' );
	}
}
