<?php
/**
 * Shared phpstan stubs for constants phpstan's analyser can't resolve on its
 * own — typically because they're defined via `define()` at runtime in a
 * bootstrap file that we can't execute during static analysis.
 *
 * Referenced from every `.phpstan.neon` in this repo via `bootstrapFiles`.
 * NOT loaded at runtime — `defined()` guards ensure this file is a no-op if
 * it ever does get pulled in.
 *
 * Add a const here whenever phpstan reports `Constant X not found`.
 *
 * @package Codelagoon
 */

// WP core — phpstan's WP stubs don't always expose these.
defined( 'ABSPATH' ) || define( 'ABSPATH', '/' );
defined( 'WPINC' )   || define( 'WPINC', 'wp-includes' );

// codelag-features mu-plugin (set in codelag-features.php via plugin_dir_*()).
defined( 'CODELAG_FEATURES_DIR_PATH' ) || define( 'CODELAG_FEATURES_DIR_PATH', '' );
defined( 'CODELAG_FEATURES_DIR_URL' )  || define( 'CODELAG_FEATURES_DIR_URL', '' );

// codelag-blocks mu-plugin (set in codelag-blocks.php via plugin_dir_*()).
defined( 'CODELAG_BLOCKS_DIR_PATH' ) || define( 'CODELAG_BLOCKS_DIR_PATH', '' );
defined( 'CODELAG_BLOCKS_DIR_URL' )  || define( 'CODELAG_BLOCKS_DIR_URL', '' );
