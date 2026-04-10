<?php
/**
 * The CodeLagoon features plugin bootstrap file.
 *
 * @since       0.1.0
 * @version     0.1.0
 * @author      Automattic Special Projects
 * @package     CODELAG_Project_Scaffold_Features
 * @license     GPL-3.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             CodeLagoon Features
 * Description:             A features plugin for a custom theme. Could include post types, metaboxes etc. Do not use for blocks!
 * Version:                 0.1.0
 * Requires at least:       6.6
 * Requires PHP:            8.3
 * Author:                  Automattic Special Projects
 * Author URI:              https://wpspecialprojects.com
 * License:                 GPL-3.0-or-later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             codelag-features
 * Domain Path:             /languages
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'CODELAG_FEATURES_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CODELAG_FEATURES_DIR_URL', plugin_dir_url( __FILE__ ) );

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
}
