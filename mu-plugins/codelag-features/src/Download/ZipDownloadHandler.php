<?php
/**
 * Streams a lagoon as a downloadable zip archive.
 *
 * @package Gin0115\Codelagoon\Features\Download
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Download;

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Post;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Handler that intercepts requests with `?codelag_download_lagoon=<id>` and
 * streams a zip of the lagoon's files back to the browser.
 *
 * Why a query-var handler instead of a REST endpoint:
 *  - REST callbacks are designed around JSON; binary downloads work but need
 *    response interception, set headers manually, and feel awkward.
 *  - A direct early-init handler reads the query var, validates auth, builds
 *    the zip on a temp file, sends headers + body, exits. Half the code.
 *
 * Lifecycle:
 *  1. `init` action (priority 5) checks for the query var.
 *  2. Validates: post exists, post is a lagoon, user is logged in, user can
 *     `read_post` the lagoon, lagoon has at least one file. Any failure is a
 *     wp_die with the appropriate HTTP status.
 *  3. Builds the zip in a tmp file, streams it, deletes the tmp file, exits.
 */
final class ZipDownloadHandler {

	public const QUERY_VAR = 'codelag_download_lagoon';

	/**
	 * Shared file repository (owns the lagoon files custom table).
	 *
	 * @var FileRepository
	 */
	private FileRepository $files;

	/**
	 * Constructor.
	 *
	 * @param FileRepository $files Shared file repository.
	 */
	public function __construct( FileRepository $files ) {
		$this->files = $files;
	}

	/**
	 * Hook the handler on `template_redirect`. By the time this fires, `init`
	 * has fully run (so the lagoon CPT is registered and `current_user_can`
	 * doesn't complain about an unknown post type) and we're past the query
	 * parsing stage but before any template is loaded — the canonical place
	 * for "intercept the request and serve a custom response".
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_handle_download' ) );
	}

	/**
	 * If the request carries `?codelag_download_lagoon`, validate and serve.
	 * Returns silently for every other request.
	 *
	 * @SuppressWarnings("PHPMD.ExitExpression")
	 */
	public function maybe_handle_download(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = (int) $_GET[ self::QUERY_VAR ];
		if ( $post_id <= 0 ) {
			$this->fail_404();
		}

		if ( ! is_user_logged_in() ) {
			wp_die(
				esc_html__( 'You must be logged in to download a lagoon.', 'codelag-features' ),
				'',
				array( 'response' => 401 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			$this->fail_404();
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			wp_die(
				esc_html__( 'You are not allowed to download this lagoon.', 'codelag-features' ),
				'',
				array( 'response' => 403 )
			);
		}

		$files = $this->files->list_for_lagoon( $post_id );
		if ( array() === $files ) {
			$this->fail_404();
		}

		$this->stream_zip( $post, $files );
		exit;
	}

	/**
	 * Build the zip on a temp file, stream it to the browser with the right
	 * Content-Disposition headers, then delete the temp file.
	 *
	 * @param WP_Post                        $post  The lagoon post.
	 * @param array<int,array<string,mixed>> $files Rows from FileRepository::list_for_lagoon.
	 */
	private function stream_zip( WP_Post $post, array $files ): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die(
				esc_html__( 'Zip support is not available on this server.', 'codelag-features' ),
				'',
				array( 'response' => 500 )
			);
		}

		$tmp = tempnam( sys_get_temp_dir(), 'codelag-zip-' );
		if ( false === $tmp ) {
			wp_die(
				esc_html__( 'Cannot create temporary file for zip.', 'codelag-features' ),
				'',
				array( 'response' => 500 )
			);
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $tmp );
			wp_die(
				esc_html__( 'Failed to create zip archive.', 'codelag-features' ),
				'',
				array( 'response' => 500 )
			);
		}

		$used_names = array();
		foreach ( $files as $idx => $file ) {
			$raw_name = isset( $file['name'] ) ? (string) $file['name'] : '';
			$entry    = $this->safe_filename( $raw_name, $idx );

			// Disambiguate duplicates so ZipArchive::addFromString doesn't
			// silently overwrite. Append " (n)" before the extension.
			$entry = $this->dedupe( $entry, $used_names );

			$content = isset( $file['content'] ) ? (string) $file['content'] : '';
			$zip->addFromString( $entry, $content );
		}

		$zip->close();

		$slug          = '' !== $post->post_name ? $post->post_name : 'lagoon-' . (int) $post->ID;
		$download_name = sanitize_file_name( $slug . '.zip' );

		// Discard any output buffered by other plugins / debug renderers so
		// the response body is pure binary zip data, not HTML + zip.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $download_name . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );

		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $tmp );
	}

	/**
	 * Sanitise a file name for use as a zip entry. Falls back to a numbered
	 * placeholder if the input is empty.
	 *
	 * @param string $name      Raw filename from the file row.
	 * @param int    $fallback  Numeric index used to build the fallback name.
	 */
	private function safe_filename( string $name, int $fallback ): string {
		$name = trim( $name );
		if ( '' === $name ) {
			return sprintf( 'untitled-%d.txt', $fallback + 1 );
		}

		$name = sanitize_file_name( $name );
		if ( '' === $name ) {
			return sprintf( 'untitled-%d.txt', $fallback + 1 );
		}

		return $name;
	}

	/**
	 * Ensure the entry name doesn't collide with a previously-added one in
	 * the same zip. Tracks used names by reference and appends "-2", "-3"…
	 * before the extension as needed.
	 *
	 * @param string             $name       Candidate entry name.
	 * @param array<string,bool> $used_names Tracker of names already in the zip.
	 */
	private function dedupe( string $name, array &$used_names ): string {
		if ( ! isset( $used_names[ $name ] ) ) {
			$used_names[ $name ] = true;
			return $name;
		}

		$dot       = strrpos( $name, '.' );
		$base      = false === $dot ? $name : substr( $name, 0, $dot );
		$extension = false === $dot ? '' : substr( $name, $dot );

		$counter = 2;
		do {
			$candidate = $base . '-' . $counter . $extension;
			++$counter;
		} while ( isset( $used_names[ $candidate ] ) );

		$used_names[ $candidate ] = true;
		return $candidate;
	}

	/**
	 * Send a 404 response and die. Used for "lagoon doesn't exist" and
	 * "lagoon has no files" cases.
	 *
	 * @return never
	 */
	private function fail_404(): never {
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'Lagoon not found.', 'codelag-features' ),
			'',
			array( 'response' => 404 )
		);
	}
}
