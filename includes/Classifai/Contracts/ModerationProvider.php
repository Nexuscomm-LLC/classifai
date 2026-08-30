<?php
/**
 * Moderation Provider Interface.
 *
 * @package Classifai\Contracts
 * @since 3.8.0
 */

namespace Classifai\Contracts;

use WP_Error;

/**
 * Interface ModerationProvider
 *
 * Defines the contract for providers that handle content moderation.
 *
 * @since 3.8.0
 */
interface ModerationProvider {

	/**
	 * Moderate content for policy violations.
	 *
	 * @param string $content The content to moderate.
	 * @param array  $args    {
	 *     Optional. Arguments for moderation.
	 *
	 *     @type array $categories Categories to check.
	 * }
	 * @return array|WP_Error Moderation results or WP_Error on failure.
	 */
	public function moderate_content( string $content, array $args = [] );

	/**
	 * Get the available moderation categories.
	 *
	 * @return array Array of category IDs and descriptions.
	 */
	public function get_moderation_categories(): array;

	/**
	 * Check if content is flagged.
	 *
	 * @param array $moderation_result The result from moderate_content().
	 * @return bool True if content is flagged, false otherwise.
	 */
	public function is_flagged( array $moderation_result ): bool;
}
