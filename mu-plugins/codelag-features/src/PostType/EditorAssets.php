<?php
/**
 * Enqueue the lagoon editor sidebar bundle.
 *
 * Loads the built mu-plugin editor script only on the lagoon edit screen so
 * we don't bloat every Gutenberg instance across the site. Relies on the
 * wp-scripts asset file for dependencies + version.
 *
 * @package Gin0115\Codelagoon\Features\PostType
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\PostType;

defined( 'ABSPATH' ) || exit;

final class EditorAssets {

	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || LagoonPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$build_dir = CODELAG_FEATURES_DIR_PATH . 'assets/js/build';
		$build_url = CODELAG_FEATURES_DIR_URL . 'assets/js/build';
		$asset     = $build_dir . '/index.asset.php';
		if ( ! is_readable( $asset ) ) {
			return;
		}
		$meta = require $asset;

		wp_enqueue_script(
			'codelag-features-editor',
			$build_url . '/index.js',
			$meta['dependencies'] ?? array(),
			$meta['version']      ?? null,
			true
		);
	}
}
