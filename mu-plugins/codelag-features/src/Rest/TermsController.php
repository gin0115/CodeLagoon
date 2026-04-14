<?php
/**
 * Generic CRUD REST controller for any lagoon taxonomy.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Full CRUD over a single taxonomy under the custom `codelag/v1` namespace.
 *
 * The taxonomy slug and REST base are injected via the constructor, so one
 * class services `lagoon-languages`, `lagoon-tags`, and `lagoon-purposes`
 * with zero duplication.
 *
 * Permissions use the taxonomy's registered capabilities
 * (`manage_terms` / `edit_terms` / `delete_terms`), matching core behaviour.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final class TermsController {

	private const NAMESPACE = 'codelag/v1';

	/**
	 * Taxonomy slug this controller services.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * REST base (e.g. `lagoon-tags`). Prepended with `/` when registering routes.
	 *
	 * @var string
	 */
	private string $rest_base;

	/**
	 * Bind the controller to a specific taxonomy + REST base.
	 *
	 * @param string $taxonomy  Taxonomy slug this controller exposes.
	 * @param string $rest_base REST base segment (e.g. `lagoon-tags`).
	 */
	public function __construct( string $taxonomy, string $rest_base ) {
		$this->taxonomy  = $taxonomy;
		$this->rest_base = $rest_base;
	}

	/**
	 * Hook REST route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the CRUD routes for this taxonomy.
	 */
	public function register_routes(): void {
		$base = '/' . $this->rest_base;

		register_rest_route(
			self::NAMESPACE,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'search'   => array( 'type' => 'string' ),
						'parent'   => array( 'type' => 'integer' ),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 50,
							'minimum' => 1,
							'maximum' => 200,
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
					),
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
			$base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
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
						'id' => $this->id_arg(),
					),
				),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Permission callbacks
	// ---------------------------------------------------------------------

	/**
	 * Allow creation only if the current user has `manage_terms` on this taxonomy.
	 */
	public function permissions_create(): bool|WP_Error {
		$tax = get_taxonomy( $this->taxonomy );
		if ( ! $tax instanceof \WP_Taxonomy || ! current_user_can( $tax->cap->manage_terms ) ) {
			return new WP_Error(
				'codelag_cannot_create_term',
				__( 'You are not allowed to create terms in this taxonomy.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Allow editing only if the current user has `edit_terms` on this taxonomy.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function permissions_edit( WP_REST_Request $request ): bool|WP_Error {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$tax = get_taxonomy( $this->taxonomy );
		if ( ! $tax instanceof \WP_Taxonomy || ! current_user_can( $tax->cap->edit_terms ) ) {
			return new WP_Error(
				'codelag_cannot_edit_term',
				__( 'You are not allowed to edit terms in this taxonomy.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Allow deletion only if the current user has `delete_terms` on this taxonomy.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function permissions_delete( WP_REST_Request $request ): bool|WP_Error {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		$tax = get_taxonomy( $this->taxonomy );
		if ( ! $tax instanceof \WP_Taxonomy || ! current_user_can( $tax->cap->delete_terms ) ) {
			return new WP_Error(
				'codelag_cannot_delete_term',
				__( 'You are not allowed to delete terms in this taxonomy.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	// ---------------------------------------------------------------------
	// Route handlers
	// ---------------------------------------------------------------------

	/**
	 * GET handler — list terms in this taxonomy.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );

		$args = array(
			'taxonomy'   => $this->taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( null !== $request->get_param( 'search' ) && '' !== (string) $request->get_param( 'search' ) ) {
			$args['search'] = (string) $request->get_param( 'search' );
		}
		if ( null !== $request->get_param( 'parent' ) ) {
			$args['parent'] = (int) $request->get_param( 'parent' );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return rest_ensure_response( array() );
		}

		$count = wp_count_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
			)
		);
		$total = $count instanceof WP_Error ? 0 : (int) $count;

		$response = rest_ensure_response( array_map( array( $this, 'prepare_term' ), (array) $terms ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ( $per_page > 0 ? (int) ceil( $total / $per_page ) : 1 ) );
		return $response;
	}

	/**
	 * GET handler — fetch a single term.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}
		return rest_ensure_response( $this->prepare_term( $term ) );
	}

	/**
	 * POST handler — create a new term.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error(
				'codelag_missing_term_name',
				__( 'Term name is required.', 'codelag-features' ),
				array( 'status' => 400 )
			);
		}

		$args = array();
		if ( null !== $request->get_param( 'slug' ) ) {
			$args['slug'] = sanitize_title( (string) $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$args['description'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		}
		if ( null !== $request->get_param( 'parent' ) && is_taxonomy_hierarchical( $this->taxonomy ) ) {
			$args['parent'] = (int) $request->get_param( 'parent' );
		}

		$result = wp_insert_term( $name, $this->taxonomy, $args );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$term = get_term( (int) $result['term_id'], $this->taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error(
				'codelag_term_lookup_failed',
				__( 'Term was created but could not be loaded.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$response = rest_ensure_response( $this->prepare_term( $term ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * PUT handler — update an existing term.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 *
	 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
	 * @SuppressWarnings("PHPMD.NPathComplexity")
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}

		$args = array();
		if ( null !== $request->get_param( 'name' ) ) {
			$args['name'] = sanitize_text_field( (string) $request->get_param( 'name' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$args['slug'] = sanitize_title( (string) $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$args['description'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		}
		if ( null !== $request->get_param( 'parent' ) && is_taxonomy_hierarchical( $this->taxonomy ) ) {
			$args['parent'] = (int) $request->get_param( 'parent' );
		}

		if ( array() === $args ) {
			return rest_ensure_response( $this->prepare_term( $term ) );
		}

		$result = wp_update_term( $term->term_id, $this->taxonomy, $args );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$fresh = get_term( (int) $result['term_id'], $this->taxonomy );
		return rest_ensure_response( $this->prepare_term( $fresh instanceof WP_Term ? $fresh : $term ) );
	}

	/**
	 * DELETE handler — remove a term.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term = $this->resolve_term( $request );
		if ( $term instanceof WP_Error ) {
			return $term;
		}

		$result = wp_delete_term( $term->term_id, $this->taxonomy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result || 0 === $result ) {
			return new WP_Error(
				'codelag_term_delete_failed',
				__( 'Could not delete the term.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_term( $term ),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Resolve the term referenced by the URL `id` arg.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_Term|WP_Error
	 */
	private function resolve_term( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];
		$term    = get_term( $term_id, $this->taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return new WP_Error(
				'codelag_term_not_found',
				__( 'Term not found.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}
		return $term;
	}

	/**
	 * Coerce a `WP_Term` into the REST response shape this controller emits.
	 *
	 * @param WP_Term $term Term to serialise.
	 * @return array<string,mixed>
	 */
	private function prepare_term( WP_Term $term ): array {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'taxonomy'    => (string) $term->taxonomy,
		);
	}

	/**
	 * Reusable URL arg schema for the term `id`.
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
	 * Schema for body params on create / update.
	 *
	 * @param bool $create True for create (some fields required), false for update.
	 * @return array<string,array<string,mixed>>
	 */
	private function write_args( bool $create ): array {
		$args = array(
			'name'        => array(
				'type'     => 'string',
				'required' => $create,
			),
			'slug'        => array(
				'type'     => 'string',
				'required' => false,
			),
			'description' => array(
				'type'     => 'string',
				'required' => false,
			),
		);
		if ( is_taxonomy_hierarchical( $this->taxonomy ) ) {
			$args['parent'] = array(
				'type'     => 'integer',
				'required' => false,
				'minimum'  => 0,
			);
		}
		return $args;
	}
}
