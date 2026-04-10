<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Simple shortcode to output a string.
 *
 * @return string
 */
function codelag_sc_translatable_string(): string {
	return __( 'This string can be translated!', 'codelag-scaffold' );
}
add_shortcode( 'translate-string', 'codelag_sc_translatable_string' );
