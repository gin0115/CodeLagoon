<?php
/**
 * Site-wide theme palette registry for the CodeLagoon theme.
 *
 * @package Gin0115\Codelagoon\Theme
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for site-wide theme palettes.
 *
 * Each theme is a flat map of CSS custom property name to value. Every
 * component in the theme's SCSS consumes `var(--cl-*)` only; switching
 * palette is as simple as flipping `data-site-theme` on <html>. Adding or
 * tweaking a theme means editing the array here — no component CSS touches
 * raw colours.
 *
 * This is presentation only. Code-block syntax highlighting (Prism /
 * markdown) has its own theme picker inside the lagoon-viewer block and
 * is intentionally not coupled to this system.
 */
final class SiteThemes {

	/**
	 * Slug of the theme applied to guests and to users who haven't picked one.
	 */
	public const DEFAULT_THEME = 'obsidian-cyan';

	/**
	 * Theme definitions. Keys become `data-site-theme` values on the root
	 * element; the inner `vars` map is the CSS custom property block applied
	 * when that theme is active.
	 *
	 * @var array<string,array{label:string,vars:array<string,string>}>
	 */
	private const THEMES = array(
		'obsidian-cyan' => array(
			'label' => 'Obsidian Cyan',
			'vars'  => array(
				'--cl-bg'                 => '#131313',
				'--cl-surface'            => '#131313',
				'--cl-surface-lowest'     => '#0e0e0e',
				'--cl-surface-low'        => '#1c1b1b',
				'--cl-surface-container'  => '#201f1f',
				'--cl-surface-high'       => '#2a2a2a',
				'--cl-surface-highest'    => '#353534',
				'--cl-on-surface'         => '#e5e2e1',
				'--cl-on-surface-variant' => '#b9cacb',
				'--cl-outline'            => '#849495',
				'--cl-outline-variant'    => '#3b494b',
				'--cl-primary'            => '#00f0ff',
				'--cl-primary-dim'        => '#00dbe9',
				'--cl-primary-text'       => '#00363a',
				'--cl-secondary'          => '#074f54',
				'--cl-toolbar'            => '#002022',
				'--cl-accent'             => '#fed639',
				'--cl-accent-dim'         => '#eac324',
				'--cl-error'              => '#ffb4ab',
				'--cl-radius'             => '1rem',
				'--cl-radius-sm'          => '0.5rem',
				'--cl-radius-lg'          => '2rem',
				'--cl-radius-pill'        => '9999px',
			),
		),
		'neon-green'    => array(
			'label' => 'Neon Green',
			'vars'  => array(
				'--cl-bg'                 => '#0b0f08',
				'--cl-surface'            => '#0b0f08',
				'--cl-surface-lowest'     => '#070905',
				'--cl-surface-low'        => '#121a0d',
				'--cl-surface-container'  => '#17210f',
				'--cl-surface-high'       => '#1f2c14',
				'--cl-surface-highest'    => '#2a3a1c',
				'--cl-on-surface'         => '#e8f1da',
				'--cl-on-surface-variant' => '#b7ca96',
				'--cl-outline'            => '#8aa36a',
				'--cl-outline-variant'    => '#2e4520',
				'--cl-primary'            => '#9dff20',
				'--cl-primary-dim'        => '#7bd604',
				'--cl-primary-text'       => '#0f1d08',
				'--cl-secondary'          => '#274700',
				'--cl-toolbar'            => '#0f1d08',
				'--cl-accent'             => '#fed639',
				'--cl-accent-dim'         => '#eac324',
				'--cl-error'              => '#ffb4ab',
				'--cl-radius'             => '1rem',
				'--cl-radius-sm'          => '0.5rem',
				'--cl-radius-lg'          => '2rem',
				'--cl-radius-pill'        => '9999px',
			),
		),
		'paper-lime'    => array(
			'label' => 'Paper Lime',
			'vars'  => array(
				'--cl-bg'                 => '#fbfcf7',
				'--cl-surface'            => '#ffffff',
				'--cl-surface-lowest'     => '#ffffff',
				'--cl-surface-low'        => '#f4f7ec',
				'--cl-surface-container'  => '#ecf2e0',
				'--cl-surface-high'       => '#dde8c7',
				'--cl-surface-highest'    => '#c4d59f',
				'--cl-on-surface'         => '#0d1f02',
				'--cl-on-surface-variant' => '#3a5a1a',
				'--cl-outline'            => '#6b8a48',
				'--cl-outline-variant'    => '#c4d59f',
				'--cl-primary'            => '#3d8f0c',
				'--cl-primary-dim'        => '#306c09',
				'--cl-primary-text'       => '#ffffff',
				'--cl-secondary'          => '#1f5200',
				'--cl-toolbar'            => '#e4efcd',
				'--cl-accent'             => '#f59e0b',
				'--cl-accent-dim'         => '#d97706',
				'--cl-error'              => '#b91c1c',
				'--cl-radius'             => '0.75rem',
				'--cl-radius-sm'          => '0.375rem',
				'--cl-radius-lg'          => '1.25rem',
				'--cl-radius-pill'        => '9999px',
			),
		),
		'paper-indigo'  => array(
			'label' => 'Paper Indigo',
			'vars'  => array(
				'--cl-bg'                 => '#fcfcfb',
				'--cl-surface'            => '#ffffff',
				'--cl-surface-lowest'     => '#ffffff',
				'--cl-surface-low'        => '#f4f5f9',
				'--cl-surface-container'  => '#eceef5',
				'--cl-surface-high'       => '#dfe3ee',
				'--cl-surface-highest'    => '#c8cfe1',
				'--cl-on-surface'         => '#0f172a',
				'--cl-on-surface-variant' => '#475569',
				'--cl-outline'            => '#64748b',
				'--cl-outline-variant'    => '#cbd5e1',
				'--cl-primary'            => '#1e3a8a',
				'--cl-primary-dim'        => '#1d4ed8',
				'--cl-primary-text'       => '#ffffff',
				'--cl-secondary'          => '#3730a3',
				'--cl-toolbar'            => '#eef1f7',
				'--cl-accent'             => '#f59e0b',
				'--cl-accent-dim'         => '#d97706',
				'--cl-error'              => '#b91c1c',
				'--cl-radius'             => '0.75rem',
				'--cl-radius-sm'          => '0.375rem',
				'--cl-radius-lg'          => '1.25rem',
				'--cl-radius-pill'        => '9999px',
			),
		),
	);

	/**
	 * Return the registered theme keys.
	 *
	 * @return array<int,string>
	 */
	public static function keys(): array {
		return array_keys( self::THEMES );
	}

	/**
	 * Return theme choices keyed by slug with their display labels.
	 *
	 * @return array<string,string>
	 */
	public static function choices(): array {
		$out = array();
		foreach ( self::THEMES as $key => $data ) {
			$out[ $key ] = $data['label'];
		}
		return $out;
	}

	/**
	 * Return the full theme map, for places that need to iterate over all
	 * themes (e.g. the `<head>` CSS emitter).
	 *
	 * @return array<string,array{label:string,vars:array<string,string>}>
	 */
	public static function all(): array {
		return self::THEMES;
	}

	/**
	 * Validate a candidate theme key. Returns the default if unknown.
	 *
	 * @param mixed $candidate Raw value to validate.
	 */
	public static function sanitize( $candidate ): string {
		if ( is_string( $candidate ) && isset( self::THEMES[ $candidate ] ) ) {
			return $candidate;
		}
		return self::DEFAULT_THEME;
	}
}
