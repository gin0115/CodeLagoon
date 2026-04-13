<?php
/**
 * Renders a "Fork" button for the current lagoon via a shortcode.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_fork_button]`.
 *
 * Designed to be dropped into block templates via `wp:shortcode`. Outputs a
 * `<button>` with the current lagoon's post ID and a fresh REST nonce baked
 * into data attributes — view.js (the lagoon-viewer block's frontend script)
 * picks it up by `data-codelag-fork-lagoon` and wires the click to POST
 * `/codelag/v1/lagoons/{id}/fork`, then redirects to the new lagoon's edit
 * screen.
 *
 * Behaviour:
 *  - Returns an empty string when not on a singular lagoon page (so the
 *    shortcode is harmless if accidentally placed elsewhere).
 *  - For logged-out visitors, the button is replaced with nothing — forking
 *    requires an account; see ForkController::permissions().
 */
final class ForkButtonShortcode {

	public const TAG = 'codelag_fork_button';

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
	 * @param array<string,mixed>|string $atts Shortcode attributes.
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

		$nonce = wp_create_nonce( 'wp_rest' );
		$label = esc_html__( 'Fork', 'codelag-features' );
		$busy  = esc_html__( 'Forking…', 'codelag-features' );

		return sprintf(
			'<button type="button" class="codelag-fork-button" data-codelag-fork-lagoon data-lagoon-id="%1$d" data-fork-nonce="%2$s" data-fork-rest-root="%3$s">'
				. '<span data-codelag-fork-default>%4$s</span>'
				. '<span data-codelag-fork-busy hidden>%5$s</span>'
				. '</button>',
			$post_id,
			esc_attr( $nonce ),
			esc_url_raw( rest_url() ),
			$label,
			$busy
		);
	}
}
