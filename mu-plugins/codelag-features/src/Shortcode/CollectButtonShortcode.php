<?php
/**
 * Renders the "Collect" toggle button for the current lagoon.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\Meta\UserCollection;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_collect_button]`.
 *
 * Drop-in for `wp:shortcode` inside the single-lagoon template, placed in
 * the header action cluster. Renders a toggle button with the caller's
 * current collected-state baked in (server-side, no flicker). Frontend JS
 * flips `data-collected` and swaps the label on click; it POSTs to
 * `/codelag/v1/lagoons/{id}/collect` to add, DELETE to remove.
 *
 * Hidden for logged-out users and on non-lagoon pages.
 */
final class CollectButtonShortcode {

	public const TAG     = 'codelag_collect_button';
	private const HANDLE = 'codelag-collect';

	/**
	 * Hook the shortcode + asset registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register the shortcode tag with WordPress.
	 */
	public function register_shortcode(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Register JS + CSS. Enqueue happens lazily inside render() so non-lagoon
	 * pages don't pay for assets they won't use.
	 */
	public function register_assets(): void {
		$version = (string) filemtime( CODELAG_FEATURES_DIR_PATH . 'assets/collect.js' );
		wp_register_script(
			self::HANDLE,
			CODELAG_FEATURES_DIR_URL . 'assets/collect.js',
			array(),
			$version,
			true
		);

		$css_version = (string) filemtime( CODELAG_FEATURES_DIR_PATH . 'assets/collect.css' );
		wp_register_style(
			self::HANDLE,
			CODELAG_FEATURES_DIR_URL . 'assets/collect.css',
			array(),
			$css_version
		);
	}

	/**
	 * Shortcode callback — render the collect button on a single lagoon.
	 *
	 * @param array<string,mixed>|string $atts Unused.
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

		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );

		$collected = UserCollection::has( get_current_user_id(), $post_id );
		$nonce     = wp_create_nonce( 'wp_rest' );
		$label_add = esc_html__( 'Collect', 'codelag-features' );
		$label_on  = esc_html__( 'Collected', 'codelag-features' );
		$tooltip   = esc_attr__( 'Add to your collection', 'codelag-features' );

		return sprintf(
			'<button type="button" class="codelag-collect-button" data-codelag-collect data-lagoon-id="%1$d" data-collect-nonce="%2$s" data-collect-rest-root="%3$s" data-collected="%4$s" aria-pressed="%4$s" aria-label="%5$s" title="%5$s">'
				. '<span class="codelag-collect-button__star" aria-hidden="true"></span>'
				. '<span data-codelag-collect-default%6$s>%7$s</span>'
				. '<span data-codelag-collect-active%8$s>%9$s</span>'
				. '</button>',
			$post_id,
			esc_attr( $nonce ),
			esc_url_raw( rest_url() ),
			$collected ? 'true' : 'false',
			$tooltip,
			$collected ? ' hidden' : '',
			$label_add,
			$collected ? '' : ' hidden',
			$label_on
		);
	}
}
