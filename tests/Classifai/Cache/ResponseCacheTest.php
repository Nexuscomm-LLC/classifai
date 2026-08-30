<?php
/**
 * Tests for ResponseCache class.
 *
 * @package Classifai\Tests\Cache
 */

namespace Classifai\Tests\Cache;

use Classifai\Cache\ResponseCache;
use WP_UnitTestCase;

/**
 * ResponseCache test case.
 *
 * @covers \Classifai\Cache\ResponseCache
 */
class ResponseCacheTest extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		ResponseCache::clear_all();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		ResponseCache::clear_all();
		parent::tear_down();
	}

	/**
	 * Test setting and getting cache.
	 */
	public function test_set_and_get() {
		$feature = 'test_feature';
		$hash    = 'abc123';
		$data    = [ 'result' => 'test data' ];

		ResponseCache::set( $feature, $hash, $data );
		$result = ResponseCache::get( $feature, $hash );

		$this->assertEquals( $data, $result );
	}

	/**
	 * Test cache returns false for non-existent key.
	 */
	public function test_get_returns_false_for_missing() {
		$result = ResponseCache::get( 'nonexistent', 'hash' );
		$this->assertFalse( $result );
	}

	/**
	 * Test deleting cache.
	 */
	public function test_delete() {
		$feature = 'test_feature';
		$hash    = 'abc123';

		ResponseCache::set( $feature, $hash, 'data' );
		$this->assertNotFalse( ResponseCache::get( $feature, $hash ) );

		ResponseCache::delete( $feature, $hash );
		$this->assertFalse( ResponseCache::get( $feature, $hash ) );
	}

	/**
	 * Test clearing feature cache.
	 */
	public function test_clear_feature() {
		ResponseCache::set( 'feature1', 'hash1', 'data1' );
		ResponseCache::set( 'feature1', 'hash2', 'data2' );
		ResponseCache::set( 'feature2', 'hash1', 'data3' );

		$count = ResponseCache::clear_feature( 'feature1' );

		$this->assertEquals( 2, $count );
		$this->assertFalse( ResponseCache::get( 'feature1', 'hash1' ) );
		$this->assertFalse( ResponseCache::get( 'feature1', 'hash2' ) );
		$this->assertNotFalse( ResponseCache::get( 'feature2', 'hash1' ) );
	}

	/**
	 * Test clearing all cache.
	 */
	public function test_clear_all() {
		ResponseCache::set( 'feature1', 'hash1', 'data1' );
		ResponseCache::set( 'feature2', 'hash1', 'data2' );

		$count = ResponseCache::clear_all();

		$this->assertEquals( 2, $count );
		$this->assertFalse( ResponseCache::get( 'feature1', 'hash1' ) );
		$this->assertFalse( ResponseCache::get( 'feature2', 'hash1' ) );
	}

	/**
	 * Test generating hash.
	 */
	public function test_generate_hash() {
		$hash1 = ResponseCache::generate_hash( 'arg1', 'arg2' );
		$hash2 = ResponseCache::generate_hash( 'arg1', 'arg2' );
		$hash3 = ResponseCache::generate_hash( 'arg1', 'arg3' );

		$this->assertEquals( $hash1, $hash2 );
		$this->assertNotEquals( $hash1, $hash3 );
	}

	/**
	 * Test remember callback.
	 */
	public function test_remember() {
		$feature = 'test_feature';
		$hash    = 'test_hash';
		$called  = 0;

		$callback = function() use ( &$called ) {
			$called++;
			return 'generated_value';
		};

		// First call should execute callback.
		$result1 = ResponseCache::remember( $feature, $hash, $callback );
		$this->assertEquals( 'generated_value', $result1 );
		$this->assertEquals( 1, $called );

		// Second call should use cache.
		$result2 = ResponseCache::remember( $feature, $hash, $callback );
		$this->assertEquals( 'generated_value', $result2 );
		$this->assertEquals( 1, $called );
	}

	/**
	 * Test is_enabled filter.
	 */
	public function test_is_enabled_filter() {
		$this->assertTrue( ResponseCache::is_enabled() );

		add_filter( 'classifai_cache_enabled', '__return_false' );
		$this->assertFalse( ResponseCache::is_enabled() );
		remove_filter( 'classifai_cache_enabled', '__return_false' );
	}

	/**
	 * Test get_stats.
	 */
	public function test_get_stats() {
		ResponseCache::set( 'feature1', 'hash1', 'data1' );
		ResponseCache::set( 'feature2', 'hash1', 'data2' );

		$stats = ResponseCache::get_stats();

		$this->assertArrayHasKey( 'total_entries', $stats );
		$this->assertEquals( 2, $stats['total_entries'] );
	}
}
