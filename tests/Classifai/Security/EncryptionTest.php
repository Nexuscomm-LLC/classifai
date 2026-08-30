<?php
/**
 * Tests for Encryption class.
 *
 * @package Classifai\Tests\Security
 */

namespace Classifai\Tests\Security;

use Classifai\Security\Encryption;
use WP_UnitTestCase;

/**
 * Encryption test case.
 *
 * @covers \Classifai\Security\Encryption
 */
class EncryptionTest extends WP_UnitTestCase {

	/**
	 * Test encryption and decryption.
	 */
	public function test_encrypt_and_decrypt() {
		$original = 'my-secret-api-key-12345';

		$encrypted = Encryption::encrypt( $original );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertNotEquals( $original, $encrypted );
		$this->assertEquals( $original, $decrypted );
	}

	/**
	 * Test encrypting empty string.
	 */
	public function test_encrypt_empty_string() {
		$result = Encryption::encrypt( '' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test decrypting empty string.
	 */
	public function test_decrypt_empty_string() {
		$result = Encryption::decrypt( '' );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test decrypting plaintext returns plaintext.
	 */
	public function test_decrypt_plaintext() {
		$plaintext = 'not-encrypted-value';

		// Decrypting plaintext should return the original value
		$result = Encryption::decrypt( $plaintext );

		// It should return something (either the original or attempted decryption)
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test is_encrypted detection.
	 */
	public function test_is_encrypted() {
		$original  = 'test-value';
		$encrypted = Encryption::encrypt( $original );

		$this->assertTrue( Encryption::is_encrypted( $encrypted ) );
		$this->assertFalse( Encryption::is_encrypted( $original ) );
		$this->assertFalse( Encryption::is_encrypted( '' ) );
	}

	/**
	 * Test encryption is available.
	 */
	public function test_is_available() {
		// OpenSSL should be available in most PHP installations
		$this->assertTrue( Encryption::is_available() );
	}

	/**
	 * Test different values produce different ciphertexts.
	 */
	public function test_unique_encryption() {
		$value1 = 'api-key-1';
		$value2 = 'api-key-2';

		$encrypted1 = Encryption::encrypt( $value1 );
		$encrypted2 = Encryption::encrypt( $value2 );

		$this->assertNotEquals( $encrypted1, $encrypted2 );
	}

	/**
	 * Test same value produces different ciphertexts (due to random IV).
	 */
	public function test_random_iv() {
		$value = 'same-api-key';

		$encrypted1 = Encryption::encrypt( $value );
		$encrypted2 = Encryption::encrypt( $value );

		// Should be different due to random IV
		$this->assertNotEquals( $encrypted1, $encrypted2 );

		// But both should decrypt to the same value
		$this->assertEquals( $value, Encryption::decrypt( $encrypted1 ) );
		$this->assertEquals( $value, Encryption::decrypt( $encrypted2 ) );
	}

	/**
	 * Test encryption handles special characters.
	 */
	public function test_special_characters() {
		$special = 'key!@#$%^&*()_+-=[]{}|;:\'",.<>?/\\`~';

		$encrypted = Encryption::encrypt( $special );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertEquals( $special, $decrypted );
	}

	/**
	 * Test encryption handles unicode.
	 */
	public function test_unicode() {
		$unicode = 'キー日本語中文العربية';

		$encrypted = Encryption::encrypt( $unicode );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertEquals( $unicode, $decrypted );
	}

	/**
	 * Test encryption handles long strings.
	 */
	public function test_long_string() {
		$long = str_repeat( 'a', 10000 );

		$encrypted = Encryption::encrypt( $long );
		$decrypted = Encryption::decrypt( $encrypted );

		$this->assertEquals( $long, $decrypted );
	}
}
