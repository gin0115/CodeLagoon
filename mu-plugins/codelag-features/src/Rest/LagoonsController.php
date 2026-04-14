<?php
/**
 * Custom REST controller for lagoon posts.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\Meta\LagoonMeta;
use Gin0115\Codelagoon\Features\Meta\UserCollection;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Search\LagoonSearchQuery;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonPurposeTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Unified custom REST surface for lagoons. Parallel to `wp/v2/lagoons` — the
 * core routes are left alone for Gutenberg and third-party consumers, and this
 * controller exists as a clean client-facing API where every lagoon response
 * already contains its taxonomies AND its files inline, so a single fetch is
 * enough to render a lagoon end-to-end.
 *
 * Permissions use standard WordPress caps (`read_post`, `edit_post`, `delete_post`,
 * `publish_posts`) — same auth story as `wp/v2` but against a custom route shape.
 */
final class LagoonsController {

	private const NAMESPACE = 'codelag/v1';
	private const BASE      = '/lagoons';

	/**
	 * Shared file repository.
	 *
	 * @var FileRepository
	 */
	private FileRepository $files;

	/**
	 * Shared filter/search query builder.
	 *
	 * @var LagoonSearchQuery
	 */
	private LagoonSearchQuery $search_query;

	public function __construct( FileRepository $files, LagoonSearchQuery $search_query ) {
		$this->files        = $files;
		$this->search_query = $search_query;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->list_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_create' ),
					'args'                => $this->write_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_read' ),
					'args'                => array(
						'id' => $this->id_arg(),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_edit' ),
					'args'                => array(
						'id' => $this->id_arg(),
					) + $this->write_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_delete' ),
					'args'                => array(
						'id'    => $this->id_arg(),
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Bypass trash and permanently delete.', 'codelag-features' ),
						),
					),
				),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Permission callbacks
	// ---------------------------------------------------------------------

	public function permissions_read( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error(
				'codelag_cannot_read',
				__( 'You are not allowed to read this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	public function permissions_create(): bool|WP_Error {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error(
				'codelag_cannot_create',
				__( 'You are not allowed to create lagoons.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	public function permissions_edit( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'codelag_cannot_edit',
				__( 'You are not allowed to edit this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	public function permissions_delete( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error(
				'codelag_cannot_delete',
				__( 'You are not allowed to delete this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	// ---------------------------------------------------------------------
	// Route handlers
	// ---------------------------------------------------------------------

	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$args = array(
			'post_type'      => LagoonPostType::POST_TYPE,
			'post_status'    => current_user_can( 'read_private_posts' ) ? array( 'publish', 'private', 'draft' ) : array( 'publish' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( null !== $request->get_param( 'author' ) ) {
			$args['author'] = (int) $request->get_param( 'author' );
		}

		// Collection scope: the collection page template marks its grid with
		// `data-codelag-collection="1"`, and view.js forwards that as the
		// `X-Codelag-Collection` request header when it re-fetches. No URL
		// param, no hidden input — the signal lives in the DOM marker the
		// server already rendered. Narrow to the caller's saved lagoons
		// before any other filter runs so search hits below intersect
		// against this pool rather than replacing it.
		$collection_pool = null;
		if ( '1' === (string) $request->get_header( 'x_codelag_collection' ) ) {
			$collection_pool  = is_user_logged_in()
				? UserCollection::get_ids( get_current_user_id() )
				: array();
			$args['post__in'] = array() === $collection_pool ? array( 0 ) : $collection_pool;
		}

		// Tax + date filters via shared helper (also drives the frontend
		// archive's pre_get_posts so semantics stay aligned).
		$this->search_query->apply_filters(
			$args,
			array(
				'filter_language' => $request->get_param( 'filter_language' ),
				'filter_tag'      => $request->get_param( 'filter_tag' ),
				'filter_purpose'  => $request->get_param( 'filter_purpose' ),
				'date_range'      => (string) $request->get_param( 'date_range' ),
			)
		);

		// Search → shared helper resolves the union of matches across post
		// fields, file FULLTEXT, and authors. Returned as post__in so the
		// final WP_Query below drives pagination / response shape.
		$search = trim( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$ids = $this->search_query->resolve_search_post_ids( $search, $args );

			// When the collection scope is active, intersect the search hits
			// with the saved set rather than replacing it — otherwise search
			// would widen the collection back out to the whole CPT.
			if ( null !== $collection_pool ) {
				$pool = array() === $collection_pool ? array( 0 ) : $collection_pool;
				$ids  = array_values( array_intersect( $pool, $ids ) );
			}

			if ( array() === $ids ) {
				$args['post__in'] = array( 0 );
			} else {
				$args['post__in'] = $ids;
				$args['orderby']  = 'post__in';
			}
		}

		$query = new WP_Query( $args );
		$items = array_map( array( $this, 'prepare_lagoon' ), $query->posts );

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	public function get_item( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}
		return rest_ensure_response( $this->prepare_lagoon( $post ) );
	}

	public function create_item( WP_REST_Request $request ) {
		$title = (string) $request->get_param( 'title' );
		if ( '' === trim( $title ) ) {
			return new WP_Error(
				'codelag_missing_title',
				__( 'Lagoon title is required.', 'codelag-features' ),
				array( 'status' => 400 )
			);
		}

		$status     = $this->coerce_status( (string) $request->get_param( 'status' ) );
		$visibility = $this->coerce_visibility( (string) $request->get_param( 'visibility' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => LagoonPostType::POST_TYPE,
				'post_title'   => sanitize_text_field( $title ),
				'post_status'  => $status,
				'post_author'  => get_current_user_id(),
				'post_content' => (string) $request->get_param( 'content' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, LagoonMeta::META_VISIBILITY, $visibility );

		$this->sync_terms_from_request( $post_id, $request );

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'codelag_create_lookup_failed',
				__( 'Lagoon was created but could not be loaded.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$response = rest_ensure_response( $this->prepare_lagoon( $post ) );
		$response->set_status( 201 );
		return $response;
	}

	public function update_item( WP_REST_Request $request ) {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$update = array( 'ID' => $post->ID );

		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$update['post_status'] = $this->coerce_status( (string) $request->get_param( 'status' ) );
		}
		if ( null !== $request->get_param( 'content' ) ) {
			$update['post_content'] = (string) $request->get_param( 'content' );
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( null !== $request->get_param( 'visibility' ) ) {
			update_post_meta(
				$post->ID,
				LagoonMeta::META_VISIBILITY,
				$this->coerce_visibility( (string) $request->get_param( 'visibility' ) )
			);
		}

		$this->sync_terms_from_request( $post->ID, $request );

		$fresh = get_post( $post->ID );
		return rest_ensure_response( $this->prepare_lagoon( $fresh ?? $post ) );
	}

	public function delete_item( WP_REST_Request $request ) {
		$post  = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$force   = (bool) $request->get_param( 'force' );
		$deleted = wp_delete_post( $post->ID, $force );

		if ( false === $deleted || null === $deleted ) {
			return new WP_Error(
				'codelag_delete_failed',
				__( 'Could not delete the lagoon.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'force'   => $force,
				'id'      => $post->ID,
			)
		);
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * @return WP_Post|WP_Error
	 */
	private function resolve_post( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
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
	 * Serialize a lagoon into the unified response shape used by every route in
	 * this controller. Includes taxonomies and files inline.
	 *
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
				'username'     => $author ? $author->user_login : '',
				'nicename'     => $author ? $author->user_nicename : '',
				'display_name' => $author ? $author->display_name : '',
				'archive_url'  => $author && (int) $post->post_author > 0
					? (string) get_author_posts_url( (int) $post->post_author )
					: '',
			),
			'visibility'   => (string) ( get_post_meta( $post->ID, LagoonMeta::META_VISIBILITY, true ) ?: LagoonMeta::VISIBILITY_PUBLIC ),
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
				if ( $term ) {
					$ids[] = (int) $term->term_id;
				}
			}
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
	}

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

	private function coerce_visibility( string $visibility ): string {
		return in_array( $visibility, LagoonMeta::VISIBILITY_VALUES, true )
			? $visibility
			: LagoonMeta::VISIBILITY_PUBLIC;
	}

	/**
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
