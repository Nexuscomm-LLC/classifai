<?php
/**
 * Tests for ErrorMessages class.
 *
 * @package Classifai\Tests\Helpers
 */

namespace Classifai\Tests\Helpers;

use Classifai\Helpers\ErrorMessages;
use WP_Error;
use WP_UnitTestCase;

/**
 * ErrorMessages test case.
 *
 * @covers \Classifai\Helpers\ErrorMessages
 */
class ErrorMessagesTest extends WP_UnitTestCase {

	/**
	 * Test getting known error message.
	 */
	public function test_get_known_error() {
		$message = ErrorMessages::get( 'rate_limit_exceeded' );

		$this->assertNotEmpty( $message );
		$this->assertStringContainsString( 'too many requests', strtolower( $message ) );
	}

	/**
	 * Test getting unknown error message.
	 */
	public function test_get_unknown_error() {
		$message = ErrorMessages::get( 'some_random_unknown_error' );

		$this->assertNotEmpty( $message );
		// Should return the generic "unexpected error" message
	}

	/**
	 * Test translating WP_Error.
	 */
	public function test_translate_wp_error() {
		$original_error = new WP_Error(
			'rate_limit_exceeded',
			'Rate limit exceeded: 429 Too Many Requests'
		);

		$translated = ErrorMessages::translate_wp_error( $original_error );

		$this->assertInstanceOf( WP_Error::class, $translated );
		$this->assertEquals( 'rate_limit_exceeded', $translated->get_error_code() );

		// Should have user-friendly message
		$message = $translated->get_error_message();
		$this->assertStringContainsString( 'too many requests', strtolower( $message ) );

		// Original message should be preserved in data
		$data = $translated->get_error_data();
		$this->assertEquals( 'Rate limit exceeded: 429 Too Many Requests', $data['original_message'] );
	}

	/**
	 * Test has_translation.
	 */
	public function test_has_translation() {
		$this->assertTrue( ErrorMessages::has_translation( 'rate_limit_exceeded' ) );
		$this->assertTrue( ErrorMessages::has_translation( 'invalid_api_key' ) );
		$this->assertFalse( ErrorMessages::has_translation( 'random_nonexistent_code' ) );
	}

	/**
	 * Test get_error_codes.
	 */
	public function test_get_error_codes() {
		$codes = ErrorMessages::get_error_codes();

		$this->assertIsArray( $codes );
		$this->assertContains( 'rate_limit_exceeded', $codes );
		$this->assertContains( 'invalid_api_key', $codes );
		$this->assertContains( 'content_too_long', $codes );
	}

	/**
	 * Test add custom error message.
	 */
	public function test_add_custom_error() {
		ErrorMessages::add( 'my_custom_error', 'This is a custom error message.' );

		$this->assertTrue( ErrorMessages::has_translation( 'my_custom_error' ) );
		$this->assertEquals( 'This is a custom error message.', ErrorMessages::get( 'my_custom_error' ) );
	}

	/**
	 * Test error messages filter.
	 */
	public function test_error_messages_filter() {
		add_filter( 'classifai_error_messages', function( $map ) {
			$map['filtered_error'] = 'Filtered error message';
			return $map;
		} );

		$this->assertTrue( ErrorMessages::has_translation( 'filtered_error' ) );
		$this->assertEquals( 'Filtered error message', ErrorMessages::get( 'filtered_error' ) );

		remove_all_filters( 'classifai_error_messages' );
	}

	/**
	 * Test pattern matching for unknown errors.
	 */
	public function test_pattern_matching() {
		$error = new WP_Error(
			'api_error',
			'Error: Rate limit has been exceeded for this API'
		);

		$translated = ErrorMessages::translate_wp_error( $error );
		$message    = $translated->get_error_message();

		// Should match the rate limit pattern
		$this->assertStringContainsString( 'too many requests', strtolower( $message ) );
	}

	/**
	 * Test all default error codes have messages.
	 */
	public function test_all_codes_have_messages() {
		$codes = ErrorMessages::get_error_codes();

		foreach ( $codes as $code ) {
			$message = ErrorMessages::get( $code );
			$this->assertNotEmpty( $message, "Error code '$code' should have a message" );
		}
	}

	/**
	 * Test various error categories.
	 */
	public function test_error_categories() {
		// Authentication errors
		$this->assertNotEmpty( ErrorMessages::get( 'invalid_api_key' ) );
		$this->assertNotEmpty( ErrorMessages::get( 'authentication_failed' ) );

		// Content errors
		$this->assertNotEmpty( ErrorMessages::get( 'content_too_long' ) );
		$this->assertNotEmpty( ErrorMessages::get( 'empty_content' ) );

		// Service errors
		$this->assertNotEmpty( ErrorMessages::get( 'service_unavailable' ) );
		$this->assertNotEmpty( ErrorMessages::get( 'timeout' ) );

		// Quota errors
		$this->assertNotEmpty( ErrorMessages::get( 'quota_exceeded' ) );
		$this->assertNotEmpty( ErrorMessages::get( 'insufficient_quota' ) );
	}
}
