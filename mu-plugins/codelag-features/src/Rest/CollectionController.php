<?php
/**
 * REST controller for the per-user lagoon collection.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Meta\UserCollection;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Two endpoints:
 *  - POST   /codelag/v1/lagoons/{id}/collect   — add to the caller's collection
 *  - DELETE /codelag/v1/lagoons/{id}/collect   — remove from the caller's collection
 *
 * Both require login plus read-access to the source lagoon, mirroring the
 * gating used by ForkController. Returns the new state and the updated
 * total count so the client can update the button without a second round-trip.
 */
final class CollectionController {

	private const NAMESPACE = 'codelag/v1';
	private const ROUTE     = '/lagoons/(?P<id>\d+)/collect';

	/**
	 * Hook REST route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the POST/DELETE routes for `/lagoons/{id}/collect`.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => self::id_arg(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => self::id_arg(),
				),
			)
		);
	}

	/**
	 * Shared `args` schema fragment for the `id` URL parameter.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function id_arg(): array {
		return array(
			'id' => array(
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value > 0;
				},
			),
		);
	}

	/**
	 * Permission callback — login + read-access to the source lagoon.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error
	 */
	public function permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'codelag_collect_login_required',
				__( 'You must be logged in to manage your collection.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post instanceof WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'codelag_collect_not_found',
				__( 'Lagoon not found.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error(
				'codelag_collect_cannot_read',
				__( 'You are not allowed to collect this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * POST handler — add the lagoon to the caller's collection.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function add_item( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		$user_id = get_current_user_id();

		UserCollection::add( $user_id, $post_id );

		return rest_ensure_response( $this->state_payload( $user_id, $post_id ) );
	}

	/**
	 * DELETE handler — remove the lagoon from the caller's collection.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function remove_item( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		$user_id = get_current_user_id();

		UserCollection::remove( $user_id, $post_id );

		return rest_ensure_response( $this->state_payload( $user_id, $post_id ) );
	}

	/**
	 * Build the response payload for both add + remove handlers.
	 *
	 * @param int $user_id Caller's user ID.
	 * @param int $post_id Lagoon post ID being added/removed.
	 * @return array<string,mixed>
	 */
	private function state_payload( int $user_id, int $post_id ): array {
		$ids = UserCollection::get_ids( $user_id );
		return array(
			'id'        => $post_id,
			'collected' => in_array( $post_id, $ids, true ),
			'count'     => count( $ids ),
		);
	}
}
