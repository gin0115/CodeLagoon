<?php
/**
 * Shared query builder for lagoon listings.
 *
 * Used by both the `codelag/v1/lagoons` REST list endpoint and the frontend
 * archive main query (via `pre_get_posts`) so filter + search semantics stay
 * identical across both paths.
 *
 * @package Gin0115\Codelagoon\Features\Search
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Search;

use Gin0115\Codelagoon\Features\Database\FileSearch;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonPurposeTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Translates filter/search input (language/tag/purpose/date_range/search) into
 * WP_Query-compatible args. Keeps REST and the frontend archive coherent.
 */
final class LagoonSearchQuery {

	/**
	 * Maximum size of the intermediate pool when resolving a search term into
	 * a post__in set. Large enough to cover real archives; capped so we don't
	 * regress to unbounded queries.
	 */
	private const SEARCH_POOL_LIMIT = 500;

	/**
	 * Shared file-search service used for FULLTEXT lookups against file rows.
	 *
	 * @var FileSearch
	 */
	private FileSearch $files;

	/**
	 * Wire in the shared file-search service.
	 *
	 * @param FileSearch $files File-search service used to search file content.
	 */
	public function __construct( FileSearch $files ) {
		$this->files = $files;
	}

	/**
	 * Merge tax_query + date_query onto an existing args array based on input.
	 *
	 * - Taxonomy filters accept string slug, CSV, or array. Empty values skipped.
	 * - Existing tax_query entries (e.g. from a taxonomy archive) are preserved
	 *   and combined via AND.
	 * - date_range of `any` or unknown is a no-op.
	 *
	 * @param array<string,mixed> $args  Existing WP_Query args — mutated in place.
	 * @param array<string,mixed> $input Raw filter input (language/tag/purpose/date_range).
	 *
	 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
	 */
	public function apply_filters( array &$args, array $input ): void {
		$tax_map = array(
			'filter_language' => LagoonLanguageTaxonomy::TAXONOMY,
			'filter_tag'      => LagoonTagTaxonomy::TAXONOMY,
			'filter_purpose'  => LagoonPurposeTaxonomy::TAXONOMY,
		);

		$new_tax_clauses = array();
		foreach ( $tax_map as $key => $taxonomy ) {
			$terms = $this->normalise_terms( $input[ $key ] ?? null );
			if ( array() === $terms ) {
				continue;
			}
			$new_tax_clauses[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'slug',
				'terms'            => $terms,
				'include_children' => true,
			);
		}

		if ( array() !== $new_tax_clauses ) {
			$existing = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
			// Strip any inherited relation key so we can re-add it cleanly.
			unset( $existing['relation'] );
			$combined = array_merge( $existing, $new_tax_clauses );
			if ( count( $combined ) > 1 ) {
				$combined = array_merge( array( 'relation' => 'AND' ), $combined );
			}
			$args['tax_query'] = $combined;
		}

		$date_range = isset( $input['date_range'] ) ? (string) $input['date_range'] : '';
		$anchor_map = array(
			'7d'   => '-7 days',
			'30d'  => '-30 days',
			'90d'  => '-90 days',
			'year' => '-1 year',
		);
		if ( '' !== $date_range && 'any' !== $date_range && isset( $anchor_map[ $date_range ] ) ) {
			$args['date_query'] = array(
				array( 'after' => $anchor_map[ $date_range ] ),
			);
		}
	}

	/**
	 * Resolve a search term into the union of post IDs matching:
	 *  - post title / content (via WP_Query `s`),
	 *  - file name / description / content (via FileRepository FULLTEXT),
	 *  - post_author whose user_login / user_nicename / display_name matches.
	 *
	 * The union is returned so the caller can feed it as `post__in`, keeping
	 * pagination driven by a single downstream WP_Query.
	 *
	 * @param string              $term      Raw search term.
	 * @param array<string,mixed> $base_args Already-filtered WP_Query args
	 *                                       (tax/date/post_type/post_status).
	 *                                       Used to scope the search pool so
	 *                                       filter+search intersect correctly.
	 *
	 * @return array<int,int> Post IDs. Empty array means zero matches — callers
	 *                        should translate that to `post__in => [0]` to force
	 *                        an empty result set.
	 */
	public function resolve_search_post_ids( string $term, array $base_args ): array {
		$term = trim( $term );
		if ( '' === $term ) {
			return array();
		}

		$pool_args = array_merge(
			$base_args,
			array(
				'posts_per_page' => self::SEARCH_POOL_LIMIT,
				'paged'          => 1,
				'fields'         => 'ids',
			)
		);
		// Strip any incoming post__in / orderby so the pool query is not
		// narrowed by a previous search result.
		unset( $pool_args['post__in'], $pool_args['orderby'], $pool_args['order'] );

		// (a) posts matching keyword directly on post fields.
		$posts_by_s = get_posts( array_merge( $pool_args, array( 's' => $term ) ) );

		// (b) lagoon IDs referenced by file rows matching the keyword via FULLTEXT.
		$file_hits      = $this->files->search(
			array(
				'q'        => $term,
				'per_page' => 100,
				'page'     => 1,
			)
		);
		$posts_by_files = array();
		foreach ( $file_hits['files'] as $row ) {
			$posts_by_files[] = (int) $row['lagoon_id'];
		}

		// (c) posts authored by any user whose login / nicename / display_name
		// matches the term. Wildcards let WP_User_Query do substring matches.
		$posts_by_author = array();
		$matching_users  = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
				'fields'         => 'ID',
			)
		);
		if ( is_array( $matching_users ) && array() !== $matching_users ) {
			$posts_by_author = get_posts(
				array_merge(
					$pool_args,
					array(
						'author__in' => array_map( 'intval', $matching_users ),
					)
				)
			);
		}

		$union = array_values(
			array_unique(
				array_map(
					static fn( $post_id ): int => $post_id instanceof \WP_Post ? (int) $post_id->ID : $post_id,
					array_merge( $posts_by_s, $posts_by_files, $posts_by_author )
				)
			)
		);

		return $union;
	}

	/**
	 * A minimal base-args bundle useful when scoping a search pool from a
	 * pre_get_posts context where you only have the main query to read from.
	 *
	 * @param array<string,mixed> $overrides Extra args to merge (e.g. tax_query).
	 * @return array<string,mixed>
	 */
	public function base_args_for_pool( array $overrides = array() ): array {
		$base = array(
			'post_type'   => LagoonPostType::POST_TYPE,
			'post_status' => current_user_can( 'read_private_posts' )
				? array( 'publish', 'private' )
				: array( 'publish' ),
		);
		return array_merge( $base, $overrides );
	}

	/**
	 * Normalise a taxonomy filter value into an array of sanitised slugs.
	 * Accepts null, string (single slug or CSV), or array of slugs.
	 *
	 * @param mixed $value Raw input value (null, string, CSV, or array).
	 * @return array<int,string>
	 */
	private function normalise_terms( $value ): array {
		if ( null === $value || '' === $value ) {
			return array();
		}
		if ( is_array( $value ) ) {
			$list = $value;
		} else {
			$list = explode( ',', (string) $value );
		}
		$out = array();
		foreach ( $list as $item ) {
			$slug = sanitize_key( (string) $item );
			if ( '' !== $slug ) {
				$out[] = $slug;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
