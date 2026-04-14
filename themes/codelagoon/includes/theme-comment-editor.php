<?php
/**
 * Paints the frontend comment-form TinyMCE iframe so it matches the active
 * CodeLagoon site palette.
 *
 * TinyMCE renders its editor inside an `<iframe>`; regular theme styles don't
 * reach it, so the palette has to be piped in via TinyMCE's own `content_style`
 * / `body_class` init settings. We keep the iframe theme-aware by emitting the
 * entire `SiteThemes` variable block as `content_style` and setting
 * `data-site-theme` on the iframe body to match the viewer's choice. When the
 * user swaps site themes the rules still apply because the variable block
 * contains every theme; only the `data-site-theme` attribute changes.
 *
 * @package Codelagoon_Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Gin0115\Codelagoon\Theme\SiteThemes;

/**
 * Build the CSS `content_style` string TinyMCE injects into the iframe head.
 * Returns a full `:root{…}` block plus `[data-site-theme="…"]{…}` rules for
 * every registered theme, followed by component styles that consume those
 * variables only. No raw hex values — swapping palettes at runtime works
 * purely by flipping the `data-site-theme` attribute on the iframe body.
 */
function codelag_tinymce_content_style(): string {
	if ( ! class_exists( SiteThemes::class ) ) {
		return '';
	}

	// Palette block — only the CURRENT user's palette, emitted as `:root` so
	// component rules below can consume `var(--cl-*)` without needing any
	// `data-site-theme` attribute on the iframe body (setting attributes on
	// the iframe body via TinyMCE's JS `setup` callback is brittle because
	// the emitted JS string can break page-level parsing). When the user
	// swaps themes, the iframe is re-rendered on next page load with the
	// new palette inlined.
	$active_slug = codelag_tinymce_current_theme();
	$active      = SiteThemes::all()[ $active_slug ] ?? SiteThemes::all()[ SiteThemes::DEFAULT_THEME ];
	$vars        = '';
	foreach ( $active['vars'] as $property => $value ) {
		$vars .= $property . ':' . $value . ';';
	}
	$palette_css = ':root{' . $vars . '}';

	// Component rules — purely `var(--cl-*)` so every theme works without edits.
	//
	// TinyMCE ships its own `content.min.css` which is loaded INTO the iframe
	// AFTER our content_style. That stylesheet uses ordinary specificity with
	// `!important` on a few rules, so we pre-empt it by both (a) doubling up
	// the selector (`html body.mce-content-body`) to beat their
	// `body.mce-content-body`, and (b) marking every declaration `!important`.
	$component_css = <<<CSS
html,html body,html body.mce-content-body{
 background-color:var(--cl-surface-lowest)!important;
 color:var(--cl-on-surface)!important;
}
html body.mce-content-body{
 font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Inter",Roboto,"Helvetica Neue",Arial,sans-serif!important;
 font-size:0.95rem!important;
 line-height:1.55!important;
 padding:0.85rem 1rem!important;
 caret-color:var(--cl-primary)!important;
 margin:0!important;
}
html body.mce-content-body p{color:var(--cl-on-surface)!important;}
html body.mce-content-body strong,html body.mce-content-body b{color:var(--cl-on-surface)!important;font-weight:700;}
html body.mce-content-body a{color:var(--cl-primary)!important;}
html body.mce-content-body code{
 background:var(--cl-surface-low)!important;
 color:var(--cl-primary)!important;
 padding:0.1em 0.35em;
 border-radius:var(--cl-radius-sm,4px);
 font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace!important;
}
html body.mce-content-body pre{
 background:var(--cl-surface-low)!important;
 color:var(--cl-on-surface)!important;
 padding:0.75rem 1rem!important;
 border-radius:var(--cl-radius-sm,6px);
 font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace!important;
}
html body.mce-content-body blockquote{
 border-left:2px solid var(--cl-primary)!important;
 margin:0.5rem 0;
 padding:0.25rem 0 0.25rem 0.9rem;
 color:var(--cl-on-surface-variant)!important;
}
html body.mce-content-body ul,html body.mce-content-body ol{color:var(--cl-on-surface)!important;}
html body.mce-content-body::selection,
html body.mce-content-body *::selection{
 background:var(--cl-primary)!important;
 color:var(--cl-primary-text)!important;
}
CSS;

	// Core emits `content_style` as a bare JS double-quoted string, so the
	// result MUST be single-line and MUST NOT contain unescaped double
	// quotes. Flatten whitespace + collapse all `"..."` → `'...'`.
	$combined = $palette_css . $component_css;
	$combined = str_replace( array( "\r", "\n", "\t" ), ' ', $combined );
	$combined = (string) preg_replace( '/ {2,}/', ' ', $combined );
	$combined = str_replace( '"', "'", $combined );

	return $combined;
}

/**
 * Resolve the current viewer's site-theme slug, mirroring the logic in the
 * theme's SiteThemeService. Logged-in users get their stored choice; guests
 * fall back to the system default.
 */
function codelag_tinymce_current_theme(): string {
	if ( ! class_exists( SiteThemes::class ) ) {
		return 'obsidian-cyan';
	}
	if ( is_user_logged_in() ) {
		$stored = (string) get_user_meta( get_current_user_id(), 'codelag_site_theme', true );
		if ( '' !== $stored ) {
			return SiteThemes::sanitize( $stored );
		}
	}
	return SiteThemes::DEFAULT_THEME;
}

/**
 * Inject the palette-aware content_style + setup hook into every TinyMCE
 * init that runs on the frontend for the `comment` editor. Admin editors
 * (post editor, widgets) are left alone.
 *
 * The `tiny_mce_before_init` filter fires for every wp_editor() instance,
 * including wp-admin ones — we scope by checking we're NOT in the admin and
 * the editor's `body_class` contains `comment` (the frontend comment editor
 * gets `mceContentBody` + the post type in there via wp_editor's defaults).
 *
 * @param array<string,mixed> $settings TinyMCE init settings.
 * @param string              $editor_id ID of the editor instance being initialised.
 * @return array<string,mixed>
 */
function codelag_filter_tinymce_for_comments( array $settings, string $editor_id = '' ): array {
	if ( is_admin() ) {
		return $settings;
	}

	if ( 'comment' !== $editor_id ) {
		return $settings;
	}

	$injected = codelag_tinymce_content_style();
	$existing = isset( $settings['content_style'] ) ? (string) $settings['content_style'] : '';
	$settings['content_style'] = $existing . $injected;

	// Add a body class so our CSS selectors (`body.mce-content-body`) keep
	// working even if core changes its default body class list.
	$body_class = isset( $settings['body_class'] ) ? (string) $settings['body_class'] : '';
	if ( false === strpos( $body_class, 'codelag-comment-editor' ) ) {
		$settings['body_class'] = trim( $body_class . ' codelag-comment-editor' );
	}

	return $settings;
}
add_filter( 'tiny_mce_before_init', 'codelag_filter_tinymce_for_comments', 10, 2 );
// CommentsEnhancer::replace_with_editor() passes `teeny => true`, which makes
// core fire `teeny_mce_before_init` instead of `tiny_mce_before_init` for this
// editor. Hook both so the filter runs regardless of which mode is active.
add_filter( 'teeny_mce_before_init', 'codelag_filter_tinymce_for_comments', 10, 2 );
