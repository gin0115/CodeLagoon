<?php
/**
 * Fallback PSR-4 autoloader for Gin0115\Codelagoon\Features.
 *
 * Used when composer's vendor/autoload.php is not available (e.g. fresh dev checkouts
 * where `composer install` has not yet been run). Mirrors the PSR-4 entry in the root
 * composer.json so behaviour is identical either way.
 *
 * @package Gin0115\Codelagoon\Features
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix   = 'Gin0115\\Codelagoon\\Features\\';
		$base_dir = __DIR__ . '/src/';

		$prefix_length = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class_name, $prefix_length ) ) {
			return;
		}

		$relative_class = substr( $class_name, $prefix_length );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
