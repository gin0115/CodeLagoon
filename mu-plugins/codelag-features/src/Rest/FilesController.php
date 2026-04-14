<?php
/**
 * REST controller for lagoon file CRUD.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the file CRUD routes under `/wp-json/codelag/v1/lagoons/{id}/files`.
 *
 * Permissions:
 *  - Read operations require `read_post` on the parent lagoon (public lagoons
 *    are readable by anyone; private posts require the usual WP caps; link-only
 *    gating is added in Phase 7's VisibilityManager).
 *  - Write operations require `edit_post` on the parent lagoon.
 */
final class FilesController {

	private const NAMESPACE = 'codelag/v1';

	/**
	 * Shared file repository.
	 *
	 * @var FileRepository
	 */
	private FileRepository $files;

	/**
	 * Wire in the shared file repository.
	 *
	 * @param FileRepository $files Files-table repository this controller reads/writes through.
	 */
	public function __construct( FileRepository $files ) {
		$this->files = $files;
	}

	/**
	 * Hook route registration into REST init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register every route owned by this controller.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lagoons/(?P<id>\d+)/files',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_files' ),
					'permission_callback' => array( $this, 'permissions_read' ),
					'args'                => array(
						'id' => $this->lagoon_id_arg(),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_file' ),
					'permission_callback' => array( $this, 'permissions_edit' ),
					'args'                => array(
						'id' => $this->lagoon_id_arg(),
					) + $this->file_write_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lagoons/(?P<id>\d+)/files/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder_files' ),
				'permission_callback' => array( $this, 'permissions_edit' ),
				'args'                => array(
					'id'  => $this->lagoon_id_arg(),
					'ids' => array(
						'type'        => 'array',
						'required'    => true,
						'description' => __( 'Ordered list of file IDs. The index becomes the new file_order.', 'codelag-features' ),
						'items'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/lagoons/(?P<id>\d+)/files/(?P<file_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_file' ),
					'permission_callback' => array( $this, 'permissions_read' ),
					'args'                => array(
						'id'      => $this->lagoon_id_arg(),
						'file_id' => $this->file_id_arg(),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_file' ),
					'permission_callback' => array( $this, 'permissions_edit' ),
					'args'                => array(
						'id'      => $this->lagoon_id_arg(),
						'file_id' => $this->file_id_arg(),
					) + $this->file_write_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_file' ),
					'permission_callback' => array( $this, 'permissions_edit' ),
					'args'                => array(
						'id'      => $this->lagoon_id_arg(),
						'file_id' => $this->file_id_arg(),
					),
				),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Permission callbacks
	// ---------------------------------------------------------------------

	/**
	 * Require `read_post` on the parent lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return true|WP_Error
	 */
	public function permissions_read( WP_REST_Request $request ) {
		$post = $this->resolve_lagoon( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error(
				'codelag_cannot_read_lagoon',
				__( 'You are not allowed to read this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Require `edit_post` on the parent lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return true|WP_Error
	 */
	public function permissions_edit( WP_REST_Request $request ) {
		$post = $this->resolve_lagoon( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'codelag_cannot_edit_lagoon',
				__( 'You are not allowed to edit this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	// ---------------------------------------------------------------------
	// Route handlers
	// ---------------------------------------------------------------------

	/**
	 * GET /lagoons/{id}/files — list every file in the lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_files( WP_REST_Request $request ) {
		$post = $this->resolve_lagoon( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$rows = $this->files->list_for_lagoon( $post->ID );
		$data = array_map( array( $this, 'prepare_file_for_response' ), $rows );

		return rest_ensure_response( $data );
	}

	/**
	 * POST /lagoons/{id}/files — create a new file.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_file( WP_REST_Request $request ) {
		$post = $this->resolve_lagoon( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$data = array(
			'name'        => (string) $request->get_param( 'name' ),
			'description' => (string) $request->get_param( 'description' ),
			'language'    => (string) $request->get_param( 'language' ),
			'content'     => (string) $request->get_param( 'content' ),
		);

		if ( null !== $request->get_param( 'file_order' ) ) {
			$data['file_order'] = (int) $request->get_param( 'file_order' );
		}

		$new_id = $this->files->create( $post->ID, $data );
		if ( 0 === $new_id ) {
			return new WP_Error(
				'codelag_file_create_failed',
				__( 'Could not create the file.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$row = $this->files->find( $new_id );
		if ( null === $row ) {
			return new WP_Error(
				'codelag_file_missing_after_create',
				__( 'File was created but could not be loaded.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$response = rest_ensure_response( $this->prepare_file_for_response( $row ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * GET /lagoons/{id}/files/{file_id} — fetch a single file.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_file( WP_REST_Request $request ) {
		$row = $this->resolve_file( $request );
		if ( $row instanceof WP_Error ) {
			return $row;
		}

		return rest_ensure_response( $this->prepare_file_for_response( $row ) );
	}

	/**
	 * PUT /lagoons/{id}/files/{file_id} — update a file.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_file( WP_REST_Request $request ) {
		$row = $this->resolve_file( $request );
		if ( $row instanceof WP_Error ) {
			return $row;
		}

		$data = array();
		foreach ( array( 'name', 'description', 'language', 'content' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$data[ $key ] = (string) $request->get_param( $key );
			}
		}
		if ( null !== $request->get_param( 'file_order' ) ) {
			$data['file_order'] = (int) $request->get_param( 'file_order' );
		}

		if ( array() === $data ) {
			return rest_ensure_response( $this->prepare_file_for_response( $row ) );
		}

		$updated = $this->files->update( (int) $row['id'], $data );
		if ( ! $updated ) {
			return new WP_Error(
				'codelag_file_update_failed',
				__( 'Could not update the file.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$fresh = $this->files->find( (int) $row['id'] );

		return rest_ensure_response( $this->prepare_file_for_response( $fresh ?? $row ) );
	}

	/**
	 * DELETE /lagoons/{id}/files/{file_id} — delete a file.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_file( WP_REST_Request $request ) {
		$row = $this->resolve_file( $request );
		if ( $row instanceof WP_Error ) {
			return $row;
		}

		$deleted = $this->files->delete( (int) $row['id'] );
		if ( ! $deleted ) {
			return new WP_Error(
				'codelag_file_delete_failed',
				__( 'Could not delete the file.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_file_for_response( $row ),
			)
		);
	}

	/**
	 * POST /lagoons/{id}/files/reorder — bulk reorder.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_files( WP_REST_Request $request ) {
		$post = $this->resolve_lagoon( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$ids       = (array) $request->get_param( 'ids' );
		$reordered = $this->files->reorder( $post->ID, $ids );
		if ( ! $reordered ) {
			return new WP_Error(
				'codelag_reorder_failed',
				__( 'Could not reorder files.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$rows = $this->files->list_for_lagoon( $post->ID );

		return rest_ensure_response( array_map( array( $this, 'prepare_file_for_response' ), $rows ) );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Resolve the parent lagoon from the request's `id` arg, or WP_Error if not found.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_Post|WP_Error
	 */
	private function resolve_lagoon( WP_REST_Request $request ) {
		$lagoon_id = (int) $request['id'];
		$post      = get_post( $lagoon_id );

		if ( ! $post instanceof WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'codelag_lagoon_not_found',
				__( 'Lagoon not found.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}

		return $post;
	}

	/**
	 * Resolve a file row and confirm it belongs to the lagoon in the URL.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function resolve_file( WP_REST_Request $request ) {
		$post = $this->resolve_lagoon( $request );
		if ( $post instanceof WP_Error ) {
			return $post;
		}

		$file_id = (int) $request['file_id'];
		$file    = $this->files->find( $file_id );
		if ( null === $file ) {
			return new WP_Error(
				'codelag_file_not_found',
				__( 'File not found.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $file['lagoon_id'] !== $post->ID ) {
			return new WP_Error(
				'codelag_file_wrong_lagoon',
				__( 'File does not belong to this lagoon.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}

		return $file;
	}

	/**
	 * Coerce a raw DB row into a REST-ready structure with proper types and RFC-3339 dates.
	 *
	 * @param array<string,mixed> $row Raw DB row from the files table.
	 * @return array<string,mixed>
	 */
	private function prepare_file_for_response( array $row ): array {
		return array(
			'id'           => (int) $row['id'],
			'lagoon_id'    => (int) $row['lagoon_id'],
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
	}

	/**
	 * Reusable URL arg for the lagoon ID.
	 *
	 * @return array<string,mixed>
	 */
	private function lagoon_id_arg(): array {
		return array(
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Reusable URL arg for the file ID.
	 *
	 * @return array<string,mixed>
	 */
	private function file_id_arg(): array {
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
	private function file_write_args( bool $create ): array {
		return array(
			'name'        => array(
				'type'     => 'string',
				'required' => $create,
			),
			'description' => array(
				'type'     => 'string',
				'required' => false,
			),
			'language'    => array(
				'type'     => 'string',
				'required' => false,
			),
			'content'     => array(
				'type'     => 'string',
				'required' => false,
			),
			'file_order'  => array(
				'type'     => 'integer',
				'required' => false,
				'minimum'  => 0,
			),
		);
	}
}
