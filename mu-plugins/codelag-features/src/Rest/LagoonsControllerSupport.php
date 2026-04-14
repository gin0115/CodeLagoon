<?php
/**
 * Shared helpers for the lagoon REST controllers.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\Meta\LagoonMeta;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonPurposeTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;
use WP_Error;
use WP_Post;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Resolver / serialiser / schema helpers shared by {@see LagoonsController}
 * (read) and {@see LagoonsWriteController} (write). Consumers must expose a
 * `FileRepository $files` property so `prepare_lagoon()` can inline the
 * lagoon's file rows into each response.
 */
trait LagoonsControllerSupport {

	/**
	 * Resolve the lagoon referenced by the URL `id` arg.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_Post|WP_Error
	 */
	private function resolve_post( WP_REST_Request $request ) {
		$lagoon_id = (int) $request['id'];
		$post      = get_post( $lagoon_id );
		if ( ! $post instanceof WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'codelag_not_found',
				__( 'Lagoon not found.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}
		return $post;
	}

	/**
	 * Serialize a lagoon into the unified response shape used by every
	 * route in both controllers. Includes taxonomies and files inline.
	 *
	 * @param WP_Post $post Lagoon to serialise.
	 * @return array<string,mixed>
	 */
	private function prepare_lagoon( WP_Post $post ): array {
		$author = get_userdata( (int) $post->post_author );

		$files = array_map(
			static function ( array $row ): array {
				return array(
					'id'           => (int) $row['id'],
					'owner_id'     => (int) $row['owner_id'],
					'visibility'   => (string) $row['visibility'],
					'file_order'   => (int) $row['file_order'],
					'name'         => (string) $row['name'],
					'description'  => (string) $row['description'],
					'language'     => (string) $row['language'],
					'content'      => (string) $row['content'],
					'content_hash' => (string) $row['content_hash'],
					'created_at'   => mysql_to_rfc3339( (string) $row['created_at'] ),
					'updated_at'   => mysql_to_rfc3339( (string) $row['updated_at'] ),
				);
			},
			$this->files->list_for_lagoon( (int) $post->ID )
		);

		return array(
			'id'           => (int) $post->ID,
			'title'        => (string) $post->post_title,
			'slug'         => (string) $post->post_name,
			'status'       => (string) $post->post_status,
			'date'         => mysql_to_rfc3339( (string) $post->post_date_gmt ),
			'modified'     => mysql_to_rfc3339( (string) $post->post_modified_gmt ),
			'link'         => (string) get_permalink( $post ),
			'content'      => (string) $post->post_content,
			'author'       => array(
				'id'           => (int) $post->post_author,
				'username'     => $author instanceof \WP_User ? $author->user_login : '',
				'nicename'     => $author instanceof \WP_User ? $author->user_nicename : '',
				'display_name' => $author instanceof \WP_User ? $author->display_name : '',
				'archive_url'  => $author instanceof \WP_User && (int) $post->post_author > 0
					? (string) get_author_posts_url( (int) $post->post_author )
					: '',
			),
			'visibility'   => $this->resolve_visibility_for_response( $post ),
			'forked_from'  => (int) get_post_meta( $post->ID, LagoonMeta::META_FORKED_FROM, true ),
			'fork_root'    => (int) get_post_meta( $post->ID, LagoonMeta::META_FORK_ROOT, true ),
			'fork_history' => (array) get_post_meta( $post->ID, LagoonMeta::META_FORK_HISTORY, true ),
			'languages'    => $this->terms_for( $post->ID, LagoonLanguageTaxonomy::TAXONOMY ),
			'tags'         => $this->terms_for( $post->ID, LagoonTagTaxonomy::TAXONOMY ),
			'purposes'     => $this->terms_for( $post->ID, LagoonPurposeTaxonomy::TAXONOMY ),
			'files'        => $files,
		);
	}

	/**
	 * Resolve the lagoon's visibility meta for the response, falling back to
	 * `public` when the meta is missing or empty. Extracted so the ternary
	 * has a typed `is_string()` guard rather than relying on `mixed` truthy
	 * coercion.
	 *
	 * @param WP_Post $post Lagoon post.
	 * @return string
	 */
	private function resolve_visibility_for_response( WP_Post $post ): string {
		$value = get_post_meta( $post->ID, LagoonMeta::META_VISIBILITY, true );
		return is_string( $value ) && '' !== $value ? $value : LagoonMeta::VISIBILITY_PUBLIC;
	}

	/**
	 * Helper that returns a serialised list of term rows for a given taxonomy.
	 *
	 * @param int    $post_id  Lagoon post ID.
	 * @param string $taxonomy Taxonomy slug to read terms from.
	 * @return array<int,array<string,mixed>>
	 */
	private function terms_for( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( false === $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return array_values(
			array_map(
				static function ( $term ): array {
					return array(
						'id'     => (int) $term->term_id,
						'name'   => (string) $term->name,
						'slug'   => (string) $term->slug,
						'parent' => (int) $term->parent,
					);
				},
				$terms
			)
		);
	}

	/**
	 * Apply incoming term arrays (`languages`, `tags`, `purposes`) to the post.
	 *
	 * @param int             $post_id Lagoon post ID.
	 * @param WP_REST_Request $request Incoming REST request carrying the term arrays.
	 */
	private function sync_terms_from_request( int $post_id, WP_REST_Request $request ): void {
		$map = array(
			'languages' => LagoonLanguageTaxonomy::TAXONOMY,
			'tags'      => LagoonTagTaxonomy::TAXONOMY,
			'purposes'  => LagoonPurposeTaxonomy::TAXONOMY,
		);
		foreach ( $map as $param => $taxonomy ) {
			if ( null === $request->get_param( $param ) ) {
				continue;
			}
			$raw = (array) $request->get_param( $param );
			$ids = array();
			foreach ( $raw as $value ) {
				if ( is_numeric( $value ) ) {
					$ids[] = (int) $value;
					continue;
				}
				$term = get_term_by( 'slug', (string) $value, $taxonomy );
				if ( $term instanceof \WP_Term ) {
					$ids[] = (int) $term->term_id;
				}
			}
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
	}

	/**
	 * Clamp the requested post_status to one we accept and the user can use.
	 *
	 * @param string $status Raw status string from the request.
	 */
	private function coerce_status( string $status ): string {
		$allowed = array( 'draft', 'pending', 'publish', 'private' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return 'draft';
		}
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			return 'pending';
		}
		if ( 'private' === $status && ! current_user_can( 'publish_posts' ) ) {
			return 'pending';
		}
		return $status;
	}

	/**
	 * Clamp the requested visibility to a valid value.
	 *
	 * @param string $visibility Raw visibility string from the request.
	 */
	private function coerce_visibility( string $visibility ): string {
		return in_array( $visibility, LagoonMeta::VISIBILITY_VALUES, true )
			? $visibility
			: LagoonMeta::VISIBILITY_PUBLIC;
	}

	/**
	 * Reusable URL arg schema for the lagoon `id`.
	 *
	 * @return array<string,mixed>
	 */
	private function id_arg(): array {
		return array(
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * REST args schema for the GET list route.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function list_args(): array {
		return array(
			'page'            => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page'        => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			),
			'author'          => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			// All three taxonomy params accept a single slug string OR an array
			// of slugs so the filter UI can select multiple (e.g.
			// `?filter_language[]=php&filter_language[]=go`). Param names are
			// `filter_*` prefixed to avoid colliding with either WP's reserved
			// query vars (notably `tag`, which WP auto-maps to core `post_tag`)
			// or our own taxonomy query_vars (`lagoon_tag`, etc. — using those
			// names triggers `redirect_canonical` to the taxonomy archive).
			'filter_language' => array(
				'type'  => array( 'string', 'array' ),
				'items' => array( 'type' => 'string' ),
			),
			'filter_tag'      => array(
				'type'  => array( 'string', 'array' ),
				'items' => array( 'type' => 'string' ),
			),
			'filter_purpose'  => array(
				'type'  => array( 'string', 'array' ),
				'items' => array( 'type' => 'string' ),
			),
			'search'          => array( 'type' => 'string' ),
			'date_range'      => array(
				'type' => 'string',
				'enum' => array( 'any', '7d', '30d', '90d', 'year' ),
			),
		);
	}

	/**
	 * REST args schema for create + update routes.
	 *
	 * @param bool $create True for create (some fields required), false for update.
	 * @return array<string,array<string,mixed>>
	 */
	private function write_args( bool $create ): array {
		return array(
			'title'      => array(
				'type'     => 'string',
				'required' => $create,
			),
			'content'    => array(
				'type'     => 'string',
				'required' => false,
			),
			'status'     => array(
				'type'     => 'string',
				'enum'     => array( 'draft', 'pending', 'publish', 'private' ),
				'required' => false,
			),
			'visibility' => array(
				'type'     => 'string',
				'enum'     => LagoonMeta::VISIBILITY_VALUES,
				'required' => false,
			),
			'languages'  => array(
				'type'        => 'array',
				'required'    => false,
				'items'       => array( 'type' => array( 'string', 'integer' ) ),
				'description' => __( 'Array of language term slugs or IDs.', 'codelag-features' ),
			),
			'tags'       => array(
				'type'        => 'array',
				'required'    => false,
				'items'       => array( 'type' => array( 'string', 'integer' ) ),
				'description' => __( 'Array of tag term slugs or IDs.', 'codelag-features' ),
			),
			'purposes'   => array(
				'type'        => 'array',
				'required'    => false,
				'items'       => array( 'type' => array( 'string', 'integer' ) ),
				'description' => __( 'Array of purpose term slugs or IDs.', 'codelag-features' ),
			),
		);
	}
}
