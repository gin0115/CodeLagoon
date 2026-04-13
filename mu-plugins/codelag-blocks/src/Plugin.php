<?php
/**
 * Main plugin class for the CodeLagoon Blocks mu-plugin.
 *
 * @package Gin0115\Codelagoon\Blocks
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton bootstrap for the blocks mu-plugin.
 *
 * Responsible for discovering every built block under blocks/build/ and registering
 * it with WordPress. Each block's block.json is self-contained; WordPress reads its
 * metadata (editor script, view script, styles, render callback) directly from the
 * build directory, so this bootstrap stays completely block-agnostic — new blocks
 * are picked up by dropping a folder in blocks/src/ and running the build.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Guards against double-initialisation.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Retrieve the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use {@see Plugin::instance()}.
	 */
	private function __construct() {}

	/**
	 * Wire the plugin into WordPress.
	 *
	 * Safe to call more than once; subsequent calls are ignored.
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Auto-discover every built block under blocks/build/<name>/ and register it.
	 *
	 * Runs on the `init` action. If `blocks/build/` is empty (e.g. before the first
	 * build has been run) this is a silent no-op.
	 */
	public function register_blocks(): void {
		$pattern = constant( 'CODELAG_BLOCKS_DIR_PATH' ) . 'blocks/build/*/block.json';
		$found   = glob( $pattern );

		if ( false === $found ) {
			return;
		}

		foreach ( $found as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}
}
