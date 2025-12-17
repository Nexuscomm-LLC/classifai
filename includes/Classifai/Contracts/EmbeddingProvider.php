<?php
/**
 * Embedding Provider Interface.
 *
 * @package Classifai\Contracts
 * @since 3.8.0
 */

namespace Classifai\Contracts;

use WP_Error;

/**
 * Interface EmbeddingProvider
 *
 * Defines the contract for providers that generate embeddings.
 * Embeddings are vector representations of text used for semantic search
 * and classification.
 *
 * @since 3.8.0
 */
interface EmbeddingProvider {

	/**
	 * Generate embeddings for the given text.
	 *
	 * @param string $text The text to generate embeddings for.
	 * @param array  $args {
	 *     Optional. Arguments for embedding generation.
	 *
	 *     @type string $model The model to use for embeddings.
	 * }
	 * @return array|WP_Error Array of embedding vectors or WP_Error on failure.
	 */
	public function generate_embeddings( string $text, array $args = [] );

	/**
	 * Get the dimension of the embedding vectors.
	 *
	 * @param string $model The model ID.
	 * @return int The dimension of embedding vectors.
	 */
	public function get_embedding_dimension( string $model = '' ): int;

	/**
	 * Get the available embedding models.
	 *
	 * @return array Array of model IDs and names.
	 */
	public function get_embedding_models(): array;

	/**
	 * Calculate similarity between two embedding vectors.
	 *
	 * @param array  $embedding1 First embedding vector.
	 * @param array  $embedding2 Second embedding vector.
	 * @param string $method     Similarity method (cosine, dot_product, euclidean).
	 * @return float Similarity score.
	 */
	public function calculate_similarity( array $embedding1, array $embedding2, string $method = 'cosine' ): float;
}
