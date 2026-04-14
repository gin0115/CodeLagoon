<?php
/**
 * Custom REST write controller for lagoon posts.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\Meta\LagoonMeta;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Custom REST write surface for lagoons — create, update, delete. Registers
 * against the same `/codelag/v1/lagoons` base as {@see LagoonsController}
 * (read); WP merges the method variants at the route layer.
 *
 * Shared resolvers / schemas / serialisers live on {@see LagoonsControllerSupport}.
 */
final class LagoonsWriteController {

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
	 * Wire in the shared file repository.
	 *
	 * @param FileRepository $files Files-table repository.
	 */
	public function __construct( FileRepository $files ) {
		$this->files = $files;
	}

	/**
	 * Hook REST route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the POST / PUT / DELETE routes. WP merges these with the read
	 * controller's registrations against the same route paths.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::BASE,
			array(
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

	/**
	 * Require the `publish_posts` cap to create a lagoon.
	 */
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

	/**
	 * Require `edit_post` cap on the resolved lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function permissions_edit( WP_REST_Request $request ): bool|WP_Error {
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

	/**
	 * Require `delete_post` cap on the resolved lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function permissions_delete( WP_REST_Request $request ): bool|WP_Error {
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

	/**
	 * POST handler — create a new lagoon owned by the caller.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
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

	/**
	 * PUT handler — update an existing lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
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

	/**
	 * DELETE handler — trash or permanently delete a lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = $this->resolve_post( $request );
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
}
