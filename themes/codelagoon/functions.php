<?php
/**
 * CodeLagoon theme functions and definitions.
 *
 * @link    https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Codelagoon_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the theme's slug.
 *
 * @return  string
 */
function codelag_get_theme_slug(): string {
	return sanitize_key( wp_get_theme()->get( 'Name' ) );
}

/**
 * Returns an array with meta information for a given asset path. First, it checks for an .asset.php file in the same directory
 * as the given asset file whose contents are returns if it exists. If not, it returns an array with the file's last modified
 * time as the version and the main stylesheet + any extra dependencies passed in as the dependencies.
 *
 * @param   string        $asset_path         The path to the asset file.
 * @param   string[]|null $extra_dependencies Any extra dependencies to include in the returned meta.
 *
 * @return  array{ version: string, dependencies: array<string> }|null
 */
function codelag_get_theme_asset_meta( string $asset_path, ?array $extra_dependencies = null ): ?array {
	$asset_path = str_starts_with( $asset_path, get_stylesheet_directory() ) ? $asset_path : get_stylesheet_directory() . "/$asset_path";
	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	$asset_meta = array(
		'dependencies' => array(),
		'version'      => (string) filemtime( $asset_path ),
	);
	if ( '' === $asset_meta['version'] ) {
		$asset_meta['version'] = wp_get_theme()->get( 'Version' );
	}

	$asset_pathinfo              = pathinfo( $asset_path );
	$asset_pathinfo['dirname'] ??= '';

	$asset_meta_file = "{$asset_pathinfo['dirname']}/{$asset_pathinfo['filename']}.asset.php";
	if ( file_exists( $asset_meta_file ) ) {
		$asset_meta_generated = require $asset_meta_file;

		if ( isset( $asset_meta_generated['version'] ) ) {
			$asset_meta['version'] = $asset_meta_generated['version'];
		}
		if ( isset( $asset_meta_generated['dependencies'] ) ) {
			$asset_meta['dependencies'] = $asset_meta_generated['dependencies'];
		}
	}

	if ( is_array( $extra_dependencies ) ) {
		$asset_meta['dependencies'] = array_merge( $asset_meta['dependencies'], $extra_dependencies );
		$asset_meta['dependencies'] = array_unique( $asset_meta['dependencies'] );
	}

	return $asset_meta;
}

// Composer autoloader lives at wp-content/vendor/ because composer.json sits
// at wp-content/. When the autoloader isn't present (local dev before a
// composer install), fall back to requiring theme classes manually so the
// site still boots.
$codelag_theme_composer_autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( is_readable( $codelag_theme_composer_autoload ) ) {
	require_once $codelag_theme_composer_autoload;
}
if ( ! class_exists( \Gin0115\Codelagoon\Theme\SiteThemeService::class ) ) {
	require_once __DIR__ . '/src/SiteThemes.php';
	require_once __DIR__ . '/src/SiteThemeService.php';
}
unset( $codelag_theme_composer_autoload );

// Wire the site-theme service (variable palettes + profile picker).
( new \Gin0115\Codelagoon\Theme\SiteThemeService() )->register();

// Include the rest of the theme's files.
$codelag_theme_files = glob( __DIR__ . '/includes/*.php' );
if ( false !== $codelag_theme_files ) {
	foreach ( $codelag_theme_files as $codelag_filename ) {
		if ( 1 === preg_match( '#/includes/_#i', $codelag_filename ) ) {
			continue; // Ignore files prefixed with an underscore.
		}

		include $codelag_filename;
	}
}
