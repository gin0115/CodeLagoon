<?php
/**
 * Renders a "Download zip" button for the current lagoon.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\Download\ZipDownloadHandler;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_download_button]`.
 *
 * Outputs a plain `<a download>` link pointing at the current lagoon's
 * permalink with `?codelag_download_lagoon=<id>` appended. Hitting that URL
 * triggers ZipDownloadHandler, which validates auth, builds a zip, and
 * streams it back. No JS required — the browser handles the file download
 * natively from the anchor.
 *
 * Empty string outside singular lagoon pages and for logged-out visitors
 * (the handler also enforces this server-side; this just hides the button).
 */
final class DownloadButtonShortcode {

	public const TAG = 'codelag_download_button';

	/**
	 * Hook the shortcode registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Register the shortcode tag with WordPress.
	 */
	public function register_shortcode(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes (unused).
	 * @return string Rendered HTML or empty string.
	 */
	public function render( $atts = array() ): string {
		unset( $atts );

		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( null === $post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$href = add_query_arg(
			ZipDownloadHandler::QUERY_VAR,
			(string) $post_id,
			(string) get_permalink( $post_id )
		);

		$slug   = '' !== $post->post_name ? $post->post_name : 'lagoon-' . $post_id;
		$label  = esc_html__( 'Download', 'codelag-features' );
		$tooltip = esc_attr__( 'Download all files as a zip', 'codelag-features' );

		return sprintf(
			'<a class="codelag-download-button" href="%1$s" download="%2$s" aria-label="%3$s" title="%3$s">'
				. '<span>%4$s</span>'
				. '</a>',
			esc_url( $href ),
			esc_attr( $slug . '.zip' ),
			$tooltip,
			$label
		);
	}
}
