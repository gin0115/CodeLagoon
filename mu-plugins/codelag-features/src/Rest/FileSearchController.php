<?php
/**
 * REST controller for full-text file search.
 *
 * @package Gin0115\Codelagoon\Features\Rest
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Rest;

use Gin0115\Codelagoon\Features\Database\FileSearch;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /codelag/v1/files/search` — FULLTEXT search across file name, description
 * and content. Public endpoint; the underlying FileRepository enforces visibility
 * based on the caller's capabilities (anon sees public published only, logged-in
 * users additionally see their own files, admins see everything).
 *
 * Supported query params:
 *  - q         (string, required) search term, BOOLEAN MODE syntax supported.
 *  - language  (string, optional) filter by language slug.
 *  - owner     (int,    optional) filter by owner user id.
 *  - lagoon    (int,    optional) restrict to one lagoon.
 *  - page      (int,    optional, default 1)
 *  - per_page  (int,    optional, default 20, max 100)
 */
final class FileSearchController {

	private const NAMESPACE = 'codelag/v1';
	private const ROUTE     = '/files/search';

	/**
	 * Shared file-search service.
	 *
	 * @var FileSearch
	 */
	private FileSearch $files;

	/**
	 * Inject the shared file-search service.
	 *
	 * @param FileSearch $files File-search service instance.
	 */
	public function __construct( FileSearch $files ) {
		$this->files = $files;
	}

	/**
	 * Hook route registration into REST init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the search route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'language' => array(
						'type'     => 'string',
						'required' => false,
					),
					'owner'    => array(
						'type'     => 'integer',
						'required' => false,
						'minimum'  => 0,
					),
					'lagoon'   => array(
						'type'     => 'integer',
						'required' => false,
						'minimum'  => 0,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);
	}

	/**
	 * Handle `GET /files/search`.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->files->search(
			array(
				'q'         => (string) $request->get_param( 'q' ),
				'language'  => (string) $request->get_param( 'language' ),
				'owner_id'  => (int) $request->get_param( 'owner' ),
				'lagoon_id' => (int) $request->get_param( 'lagoon' ),
				'page'      => (int) $request->get_param( 'page' ),
				'per_page'  => (int) $request->get_param( 'per_page' ),
			)
		);

		$files = array_map(
			static function ( array $row ): array {
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
					'score'        => isset( $row['score'] ) ? (float) $row['score'] : 0.0,
					'created_at'   => mysql_to_rfc3339( (string) $row['created_at'] ),
					'updated_at'   => mysql_to_rfc3339( (string) $row['updated_at'] ),
				);
			},
			$result['files']
		);

		$response = rest_ensure_response(
			array(
				'files'    => $files,
				'total'    => $result['total'],
				'page'     => $result['page'],
				'per_page' => $result['per_page'],
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header(
			'X-WP-TotalPages',
			(string) ( $result['per_page'] > 0 ? (int) ceil( $result['total'] / $result['per_page'] ) : 1 )
		);

		return $response;
	}
}
