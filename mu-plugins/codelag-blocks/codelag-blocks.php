<?php
/**
 * The CodeLagoon Blocks mu-plugin bootstrap file.
 *
 * Houses Gutenberg blocks only. Each block under blocks/src/<name>/ is fully
 * self-contained; this bootstrap's only job is to auto-discover built blocks in
 * blocks/build/* and register them with WordPress. Non-block logic (CPTs, REST,
 * taxonomies, etc.) lives in the codelag-features mu-plugin.
 *
 * Created with the a8cteam51/team51-project-scaffold
 * (https://github.com/a8cteam51/team51-project-scaffold).
 *
 * @since       0.1.0
 * @version     0.1.0
 * @package     Gin0115\Codelagoon\Blocks
 * @license     GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:             CodeLagoon Blocks
 * Description:             Gutenberg blocks for CodeLagoon. Each block lives under blocks/src/<name>/ and is auto-registered from its build directory. Created with a8cteam51/team51-project-scaffold. Do not add non-block logic here.
 * Version:                 0.1.0
 * Requires at least:       6.6
 * Requires PHP:            8.3
 * Author:                  gin0115 & WP Special Projects
 * Author URI:              https://github.com/gin0115/codelagoon
 * License:                 GPL-3.0-or-later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             codelag-blocks
 * Domain Path:             /languages
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'CODELAG_BLOCKS_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CODELAG_BLOCKS_DIR_URL', plugin_dir_url( __FILE__ ) );

// Load composer's autoloader if present, otherwise fall back to the internal PSR-4 loader.
// Composer vendor dir lives at wp-content/vendor when composer.json sits at wp-content/.
$codelag_blocks_composer_autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( is_readable( $codelag_blocks_composer_autoload ) ) {
	require_once $codelag_blocks_composer_autoload;
}
if ( ! class_exists( \Gin0115\Codelagoon\Blocks\Plugin::class ) ) {
	require_once __DIR__ . '/autoload.php';
}
unset( $codelag_blocks_composer_autoload );

require_once __DIR__ . '/functions.php';

if ( is_php_version_compatible( codelag_blocks_get_metadata( 'RequiresPHP' ) ) && is_wp_version_compatible( codelag_blocks_get_metadata( 'RequiresWP' ) ) ) {
	// Boot the namespaced plugin. Block auto-discovery hooks into `init` from initialize().
	\Gin0115\Codelagoon\Blocks\Plugin::instance()->initialize();
}
