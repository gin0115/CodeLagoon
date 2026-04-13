<?php
/**
 * The CodeLagoon features plugin bootstrap file.
 *
 * Created with the a8cteam51/team51-project-scaffold
 * (https://github.com/a8cteam51/team51-project-scaffold).
 *
 * @since       0.1.0
 * @version     0.1.0
 * @author      gin0115 & WP Special Projects
 * @package     Gin0115\Codelagoon\Features
 * @license     GPL-3.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             CodeLagoon Features
 * Description:             Domain layer for CodeLagoon — lagoon CPT, taxonomies, custom files table, REST, forks, downloads. Created with a8cteam51/team51-project-scaffold. Do not use for blocks!
 * Version:                 0.1.0
 * Requires at least:       6.6
 * Requires PHP:            8.3
 * Author:                  gin0115 & WP Special Projects
 * Author URI:              https://github.com/gin0115/codelagoon
 * License:                 GPL-3.0-or-later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             codelag-features
 * Domain Path:             /languages
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'CODELAG_FEATURES_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CODELAG_FEATURES_DIR_URL', plugin_dir_url( __FILE__ ) );

// Load composer's autoloader if present, otherwise fall back to the internal PSR-4 loader.
// Composer vendor dir lives at wp-content/vendor when composer.json sits at wp-content/.
$codelag_features_composer_autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( is_readable( $codelag_features_composer_autoload ) ) {
	require_once $codelag_features_composer_autoload;
}
if ( ! class_exists( \Gin0115\Codelagoon\Features\Plugin::class ) ) {
	require_once __DIR__ . '/autoload.php';
}
unset( $codelag_features_composer_autoload );

// Include the rest of the features plugin's files if system requirements check out.
require_once __DIR__ . '/functions.php';

if ( is_php_version_compatible( codelag_features_get_metadata( 'RequiresPHP' ) ) && is_wp_version_compatible( codelag_features_get_metadata( 'RequiresWP' ) ) ) {
	$codelag_features_files = glob( __DIR__ . '/includes/*.php' );
	if ( false !== $codelag_features_files ) {
		foreach ( $codelag_features_files as $codelag_features_filename ) {
			if ( 1 === preg_match( '#/includes/_#i', $codelag_features_filename ) ) {
				continue; // Ignore files prefixed with an underscore.
			}

			include $codelag_features_filename;
		}
	}

	// Boot the namespaced plugin. Services register their own hooks from initialize().
	\Gin0115\Codelagoon\Features\Plugin::instance()->initialize();
}
