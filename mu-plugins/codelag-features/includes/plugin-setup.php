<?php

defined( 'ABSPATH' ) || exit;

/**
 * Loads the features plugin's translated strings.
 *
 * @version 0.1.0
 *
 * @return  void
 */
function codelag_features_load_textdomain(): void {
	load_muplugin_textdomain(
		codelag_features_get_metadata( 'TextDomain' ),
		dirname( plugin_basename( constant( 'CODELAG_FEATURES_DIR_PATH' ) ) ) . codelag_features_get_metadata( 'DomainPath' )
	);
}
add_action( 'init', 'codelag_features_load_textdomain' );
