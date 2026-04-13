<?php
/**
 * Renders a Prism theme picker for the frontend lagoon page.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_theme_picker]`.
 *
 * Renders a `<select>` whose options match the theme map declared in
 * view.js. The actual theme switching (loading the right Prism + markdown
 * stylesheets from a CDN, persisting the choice in localStorage, swapping
 * the body data attribute) lives entirely in JS — this shortcode just paints
 * the control. Empty string outside singular lagoon pages.
 *
 * Theme keys here MUST stay in sync with the THEMES map in
 * mu-plugins/codelag-blocks/blocks/src/lagoon-viewer/view.js.
 */
final class ThemePickerShortcode {

	public const TAG = 'codelag_theme_picker';

	/**
	 * Theme list shown in the picker.
	 *
	 * Each entry: option value (matches the JS THEMES key) => display label.
	 * Order is the dropdown order.
	 *
	 * @var array<string,string>
	 */
	private const THEMES = array(
		'tomorrow'       => 'Tomorrow Night (dark)',
		'okaidia'        => 'Okaidia (dark)',
		'twilight'       => 'Twilight (dark)',
		'default'        => 'Default (light)',
		'coy'            => 'Coy (light)',
		'solarizedlight' => 'Solarized (light)',
	);

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

		$options = '';
		foreach ( self::THEMES as $value => $label ) {
			$options .= sprintf(
				'<option value="%1$s">%2$s</option>',
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<label class="codelag-theme-picker">'
				. '<span class="codelag-theme-picker__label">%1$s</span>'
				. '<select class="codelag-theme-picker__select" data-codelag-theme-picker>%2$s</select>'
				. '</label>',
			esc_html__( 'Theme', 'codelag-features' ),
			$options
		);
	}
}
