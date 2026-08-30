<?php
/**
 * Image Generation Provider Interface.
 *
 * @package Classifai\Contracts
 * @since 3.8.0
 */

namespace Classifai\Contracts;

use WP_Error;

/**
 * Interface ImageGenerationProvider
 *
 * Defines the contract for providers that generate images.
 *
 * @since 3.8.0
 */
interface ImageGenerationProvider {

	/**
	 * Generate an image based on the provided prompt.
	 *
	 * @param string $prompt The prompt describing the image to generate.
	 * @param array  $args   {
	 *     Optional. Arguments for image generation.
	 *
	 *     @type string $size    Image size (e.g., '1024x1024').
	 *     @type int    $n       Number of images to generate.
	 *     @type string $quality Image quality (standard, hd).
	 *     @type string $style   Image style (vivid, natural).
	 * }
	 * @return array|WP_Error Array of image URLs/data or WP_Error on failure.
	 */
	public function generate_image( string $prompt, array $args = [] );

	/**
	 * Get the available image sizes.
	 *
	 * @return array Array of available sizes.
	 */
	public function get_available_sizes(): array;

	/**
	 * Get the available image generation models.
	 *
	 * @return array Array of model IDs and names.
	 */
	public function get_image_generation_models(): array;
}
