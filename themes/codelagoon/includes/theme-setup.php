<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Loads the theme's translated strings.
 *
 * @return  void
 */
function codelag_load_theme_textdomain(): void {
	wp_get_theme()->load_textdomain();
}
add_action( 'init', 'codelag_load_theme_textdomain' );

/**
 * Registers one or more stylesheets to enqueue in the editor (both Gutenberg and TinyMCE).
 *
 * @return  void
 */
function codelag_enqueue_editor_style(): void {
	add_editor_style( 'style-editor.css' );
}
add_action( 'admin_init', 'codelag_enqueue_editor_style' );

/**
 * Registers and/or enqueues theme scripts and stylesheets.
 *
 * @return  void
 */
function codelag_enqueue_frontend_assets(): void {
	$theme_slug = codelag_get_theme_slug();

	// Material Symbols Outlined — the theme references them via the `.material-symbols-outlined`
	// class in block markup for nav + action icons. Loaded from Google Fonts CDN; no fallback
	// icon font is needed because every usage is paired with accessible text labels.
	wp_enqueue_style(
		"$theme_slug-material-symbols",
		'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- versionless to allow browser caching of the Google Fonts CSS.
	);

	$asset_meta = codelag_get_theme_asset_meta( get_theme_file_path( 'style.css' ), array( /* parent theme style, if applicable */ ) );
	if ( is_array( $asset_meta ) ) {
		wp_enqueue_style(
			"$theme_slug-style",
			get_stylesheet_uri(),
			$asset_meta['dependencies'],
			$asset_meta['version']
		);
		wp_style_add_data( "$theme_slug-style", 'rtl', 'replace' );
	}

	$asset_meta = codelag_get_theme_asset_meta( get_theme_file_path( 'assets/js/build/index.js' ) );
	if ( is_array( $asset_meta ) ) {
		wp_enqueue_script(
			"$theme_slug-script",
			get_theme_file_uri( 'assets/js/build/index.js' ),
			$asset_meta['dependencies'],
			$asset_meta['version'],
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'codelag_enqueue_frontend_assets' );
