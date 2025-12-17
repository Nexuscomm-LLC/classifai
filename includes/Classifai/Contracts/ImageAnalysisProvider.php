<?php
/**
 * Image Analysis Provider Interface.
 *
 * @package Classifai\Contracts
 * @since 3.8.0
 */

namespace Classifai\Contracts;

use WP_Error;

/**
 * Interface ImageAnalysisProvider
 *
 * Defines the contract for providers that analyze images.
 * This includes alt text generation, image tagging, smart cropping, etc.
 *
 * @since 3.8.0
 */
interface ImageAnalysisProvider {

	/**
	 * Analyze an image and return the analysis results.
	 *
	 * @param int   $attachment_id The attachment ID to analyze.
	 * @param array $args          {
	 *     Optional. Arguments for image analysis.
	 *
	 *     @type bool   $generate_alt     Generate alt text.
	 *     @type bool   $generate_tags    Generate image tags.
	 *     @type bool   $extract_text     Extract text from image.
	 *     @type string $language         Language for analysis.
	 * }
	 * @return array|WP_Error Analysis results or WP_Error on failure.
	 */
	public function analyze_image( int $attachment_id, array $args = [] );

	/**
	 * Get the supported image formats.
	 *
	 * @return array Array of supported MIME types.
	 */
	public function get_supported_image_formats(): array;

	/**
	 * Get the maximum image size in bytes.
	 *
	 * @return int Maximum file size in bytes.
	 */
	public function get_max_image_size(): int;
}
