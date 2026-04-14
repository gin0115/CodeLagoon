<?php
/**
 * Custom REST read controller for lagoon posts.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\Meta\UserCollection;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Search\LagoonSearchQuery;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Custom REST read surface for lagoons. Parallel to `wp/v2/lagoons` — the
 * core routes are left alone for Gutenberg and third-party consumers, and
 * this controller exists as a clean client-facing API where every lagoon
 * response already contains its taxonomies AND its files inline, so a single
 * fetch is enough to render a lagoon end-to-end.
 *
 * Write operations (create / update / delete) live on the sibling
 * {@see LagoonsWriteController}; both register against the same route base.
 * Shared resolvers / schemas / serialisers live on
 * {@see LagoonsControllerSupport}.
 */
final class LagoonsController {

	use LagoonsControllerSupport;

	private const NAMESPACE = 'codelag/v1';
	private const BASE      = '/lagoons';

	/**
	 * Shared file repository (used by the support trait's `prepare_lagoon()`).
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

	/**
	 * Wire in the file repository + shared search helper.
	 *
	 * @param FileRepository    $files        Files-table repository.
	 * @param LagoonSearchQuery $search_query Shared filter/search helper.
	 */
	public function __construct( FileRepository $files, LagoonSearchQuery $search_query ) {
		$this->files        = $files;
		$this->search_query = $search_query;
	}

	/**
	 * Hook REST route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the GET routes. WP merges these with the write controller's
	 * registrations against the same route paths.
	 */
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
			)
		);
	}

	/**
	 * Read permission — published lagoons are public (matches how the
	 * frontend template renders them); private / draft require the
	 * `read_post` cap on the resolved post.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function permissions_read( WP_REST_Request $request ): bool|WP_Error {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}
		if ( 'publish' === $post->post_status ) {
			return true;
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

	/**
	 * GET handler — list lagoons with optional filters / search / collection scope.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 *
	 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
	 */
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

	/**
	 * GET handler — fetch a single lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->resolve_post( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}
		return rest_ensure_response( $this->prepare_lagoon( $post ) );
	}
}
