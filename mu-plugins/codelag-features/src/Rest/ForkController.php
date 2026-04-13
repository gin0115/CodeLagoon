<?php
/**
 * REST controller for forking a lagoon.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Fork\ForkService;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Single endpoint: `POST /codelag/v1/lagoons/{id}/fork`.
 *
 * Authenticated users with read access to the source lagoon can fork it into
 * a new draft owned by themselves. Returns the new lagoon's id, slug, edit
 * link and view link so the client can redirect immediately.
 */
final class ForkController {

	private const NAMESPACE = 'codelag/v1';
	private const ROUTE     = '/lagoons/(?P<id>\d+)/fork';

	private ForkService $fork_service;

	public function __construct( ForkService $fork_service ) {
		$this->fork_service = $fork_service;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'fork_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => static function ( $value ): bool {
								return is_numeric( $value ) && (int) $value > 0;
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Fork permissions: must be logged in, must be allowed to read the source,
	 * must be allowed to publish lagoons (the new fork is owned by the caller).
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error
	 */
	public function permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'codelag_fork_login_required',
				__( 'You must be logged in to fork a lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$source_id = (int) $request['id'];
		$source    = get_post( $source_id );
		if ( ! $source instanceof WP_Post || LagoonPostType::POST_TYPE !== $source->post_type ) {
			return new WP_Error(
				'codelag_fork_source_not_found',
				__( 'Source lagoon not found.', 'codelag-features' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'read_post', $source_id ) ) {
			return new WP_Error(
				'codelag_fork_cannot_read_source',
				__( 'You are not allowed to fork this lagoon.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error(
				'codelag_fork_cannot_create',
				__( 'You are not allowed to create lagoons.', 'codelag-features' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Endpoint callback — runs the fork and returns lookup data for the new lagoon.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function fork_item( WP_REST_Request $request ) {
		$source_id = (int) $request['id'];
		$user_id   = get_current_user_id();

		$new_id = $this->fork_service->fork( $source_id, $user_id );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$new_post = get_post( $new_id );
		if ( ! $new_post instanceof WP_Post ) {
			return new WP_Error(
				'codelag_fork_post_missing',
				__( 'Forked post could not be loaded after creation.', 'codelag-features' ),
				array( 'status' => 500 )
			);
		}

		$response = rest_ensure_response(
			array(
				'id'         => (int) $new_id,
				'slug'       => (string) $new_post->post_name,
				'title'      => (string) $new_post->post_title,
				'edit_link'  => admin_url( 'post.php?post=' . $new_id . '&action=edit' ),
				'view_link'  => (string) get_permalink( $new_id ),
				'forked_from' => $source_id,
			)
		);
		$response->set_status( 201 );

		return $response;
	}
}
