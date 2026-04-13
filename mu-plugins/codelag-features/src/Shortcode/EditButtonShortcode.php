<?php
/**
 * Renders an "Edit" link for the lagoon's author.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_edit_button]`.
 *
 * Outputs a link to the lagoon's wp-admin edit screen when the current
 * viewer is either the lagoon's author or a user with `edit_post` on it
 * (editors, admins). Everyone else gets an empty string so the button
 * simply doesn't appear.
 */
final class EditButtonShortcode {

	public const TAG = 'codelag_edit_button';

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

		// Allow the author plus anyone with explicit edit rights on the post
		// (editors, admins). `current_user_can('edit_post', $id)` covers both.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		$href    = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$label   = esc_html__( 'Edit', 'codelag-features' );
		$tooltip = esc_attr__( 'Edit this lagoon in wp-admin', 'codelag-features' );

		return sprintf(
			'<a class="codelag-edit-button" href="%1$s" aria-label="%2$s" title="%2$s">'
				. '<span>%3$s</span>'
				. '</a>',
			esc_url( $href ),
			$tooltip,
			$label
		);
	}
}
