<?php
/**
 * Text Generation Provider Interface.
 *
 * @package Classifai\Contracts
 * @since 3.8.0
 */

namespace Classifai\Contracts;

use WP_Error;

/**
 * Interface TextGenerationProvider
 *
 * Defines the contract for providers that generate text content.
 * This includes title generation, excerpt generation, content generation, etc.
 *
 * @since 3.8.0
 */
interface TextGenerationProvider {

	/**
	 * Generate text based on the provided content.
	 *
	 * @param int   $post_id The post ID to generate text for.
	 * @param array $args    {
	 *     Optional. Arguments for text generation.
	 *
	 *     @type string $prompt     The prompt to use for generation.
	 *     @type int    $max_tokens Maximum tokens to generate.
	 *     @type float  $temperature Temperature for randomness (0-2).
	 *     @type int    $n          Number of completions to generate.
	 * }
	 * @return string|array|WP_Error Generated text, array of texts, or WP_Error on failure.
	 */
	public function generate_text( int $post_id, array $args = [] );

	/**
	 * Get the available models for text generation.
	 *
	 * @return array Array of model IDs and names.
	 */
	public function get_text_generation_models(): array;

	/**
	 * Get the maximum token limit for the provider.
	 *
	 * @param string $model The model ID.
	 * @return int Maximum tokens allowed.
	 */
	public function get_max_tokens( string $model = '' ): int;
}
