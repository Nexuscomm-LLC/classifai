<?php
/**
 * Speech Provider Interface.
 *
 * @package Classifai\Contracts
 * @since 3.8.0
 */

namespace Classifai\Contracts;

use WP_Error;

/**
 * Interface SpeechProvider
 *
 * Defines the contract for providers that handle speech operations.
 * This includes text-to-speech and speech-to-text.
 *
 * @since 3.8.0
 */
interface SpeechProvider {

	/**
	 * Convert text to speech.
	 *
	 * @param string $text The text to convert.
	 * @param array  $args {
	 *     Optional. Arguments for text-to-speech.
	 *
	 *     @type string $voice  The voice to use.
	 *     @type string $format Audio format (mp3, wav, etc.).
	 *     @type float  $speed  Speech speed multiplier.
	 * }
	 * @return string|WP_Error Audio file path/URL or WP_Error on failure.
	 */
	public function text_to_speech( string $text, array $args = [] );

	/**
	 * Convert speech to text.
	 *
	 * @param string $audio_file Path to the audio file.
	 * @param array  $args       {
	 *     Optional. Arguments for speech-to-text.
	 *
	 *     @type string $language Language code.
	 *     @type bool   $timestamps Include timestamps.
	 * }
	 * @return string|WP_Error Transcribed text or WP_Error on failure.
	 */
	public function speech_to_text( string $audio_file, array $args = [] );

	/**
	 * Get the available voices.
	 *
	 * @return array Array of voice IDs and names.
	 */
	public function get_available_voices(): array;

	/**
	 * Get the supported audio formats.
	 *
	 * @return array Array of supported audio formats.
	 */
	public function get_supported_audio_formats(): array;
}
