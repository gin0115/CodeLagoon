<?php
/**
 * Renders a "Share" button that copies the current page URL to the clipboard.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_share_button]`.
 *
 * Outputs a `<button>` paired with view.js's share-button handler. The handler
 * reads `window.location.href` at click time (not a baked-in URL) so any
 * fragment / query state the user has is preserved, and copies it via the
 * Clipboard API. Empty string outside singular lagoon pages.
 */
final class ShareButtonShortcode {

	public const TAG = 'codelag_share_button';

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
	 * @return string Rendered HTML or empty string when not on a lagoon page.
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

		$label   = esc_html__( 'Share', 'codelag-features' );
		$copied  = esc_html__( 'Copied!', 'codelag-features' );
		$tooltip = esc_attr__( 'Copy link to this lagoon', 'codelag-features' );

		return sprintf(
			'<button type="button" class="codelag-share-button" data-codelag-share aria-label="%1$s" title="%1$s">'
				. '<span data-codelag-share-default>%2$s</span>'
				. '<span data-codelag-share-success hidden>%3$s</span>'
				. '</button>',
			$tooltip,
			$label,
			$copied
		);
	}
}
