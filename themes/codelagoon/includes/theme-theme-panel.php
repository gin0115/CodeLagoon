<?php
/**
 * Frontend floating theme picker for logged-in users.
 *
 * Renders a draggable side button + popout panel in the footer. The panel
 * exposes the same two choices that live on the wp-admin profile page
 * (site theme + code syntax theme). Changes are persisted to user meta via
 * the standard WP REST `/wp/v2/users/me` endpoint with the meta keys we
 * registered in SiteThemeService::register_meta(), and applied live by
 * swapping `data-site-theme` on <html> so the CSS variable cascade takes
 * over instantly — no reload required.
 *
 * @package Codelagoon_Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Gin0115\Codelagoon\Theme\SiteThemes;
use Gin0115\Codelagoon\Theme\SiteThemeService;

/**
 * Print the floating panel markup + config into the footer. Logged-out
 * visitors get nothing — the feature is an authenticated convenience only.
 */
function codelag_render_theme_panel(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( ! class_exists( SiteThemes::class ) || ! class_exists( SiteThemeService::class ) ) {
		return;
	}

	$user_id         = get_current_user_id();
	$current_site    = (string) get_user_meta( $user_id, SiteThemeService::META_KEY, true );
	$current_syntax  = (string) get_user_meta( $user_id, SiteThemeService::SYNTAX_META_KEY, true );
	$current_columns = (int) get_user_meta( $user_id, SiteThemeService::COLUMNS_META_KEY, true );
	if ( '' === $current_site ) {
		$current_site = SiteThemes::DEFAULT_THEME;
	}
	if ( '' === $current_syntax ) {
		$current_syntax = SiteThemeService::SYNTAX_DEFAULT;
	}
	if ( 1 !== $current_columns && 2 !== $current_columns ) {
		$current_columns = SiteThemeService::COLUMNS_DEFAULT;
	}

	$site_choices   = SiteThemes::choices();
	$syntax_choices = SiteThemeService::SYNTAX_CHOICES;
	$nonce          = wp_create_nonce( 'wp_rest' );
	$rest_root      = esc_url_raw( rest_url() );
	?>
	<aside
		class="codelag-theme-panel"
		data-codelag-theme-panel
		data-site-meta-key="<?php echo esc_attr( SiteThemeService::META_KEY ); ?>"
		data-syntax-meta-key="<?php echo esc_attr( SiteThemeService::SYNTAX_META_KEY ); ?>"
		data-columns-meta-key="<?php echo esc_attr( SiteThemeService::COLUMNS_META_KEY ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-rest-root="<?php echo esc_attr( $rest_root ); ?>"
		aria-label="<?php esc_attr_e( 'Theme settings', 'codelagoon' ); ?>"
	>
		<button
			type="button"
			class="codelag-theme-panel__toggle"
			data-codelag-theme-toggle
			aria-expanded="false"
			aria-label="<?php esc_attr_e( 'Open theme settings', 'codelagoon' ); ?>"
		>
			<span class="material-symbols-outlined" aria-hidden="true">palette</span>
		</button>

		<div class="codelag-theme-panel__body" data-codelag-theme-body hidden>
			<div class="codelag-theme-panel__header">
				<span class="codelag-theme-panel__title"><?php esc_html_e( 'Appearance', 'codelagoon' ); ?></span>
				<div class="codelag-theme-panel__header-actions">
					<button
						type="button"
						class="codelag-theme-panel__side-btn"
						data-codelag-theme-side
						data-side="toggle"
						aria-label="<?php esc_attr_e( 'Swap panel side', 'codelagoon' ); ?>"
					>
						<span class="material-symbols-outlined" aria-hidden="true">swap_horiz</span>
					</button>
					<button
						type="button"
						class="codelag-theme-panel__close"
						data-codelag-theme-close
						aria-label="<?php esc_attr_e( 'Close theme settings', 'codelagoon' ); ?>"
					>
						<span class="material-symbols-outlined" aria-hidden="true">close</span>
					</button>
				</div>
			</div>

			<label class="codelag-theme-panel__field">
				<span class="codelag-theme-panel__label"><?php esc_html_e( 'Site theme', 'codelagoon' ); ?></span>
				<select data-codelag-site-theme class="codelag-theme-panel__select">
					<?php foreach ( $site_choices as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_site, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="codelag-theme-panel__field">
				<span class="codelag-theme-panel__label"><?php esc_html_e( 'Code syntax theme', 'codelagoon' ); ?></span>
				<select data-codelag-syntax-theme class="codelag-theme-panel__select">
					<?php foreach ( $syntax_choices as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_syntax, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<div class="codelag-theme-panel__field">
				<span class="codelag-theme-panel__label"><?php esc_html_e( 'Archive columns', 'codelagoon' ); ?></span>
				<div class="codelag-theme-panel__cols" role="group">
					<button
						type="button"
						class="codelag-theme-panel__cols-btn"
						data-codelag-columns="1"
						aria-pressed="<?php echo 1 === $current_columns ? 'true' : 'false'; ?>"
						aria-label="<?php esc_attr_e( 'One column', 'codelagoon' ); ?>"
					>
						<span class="material-symbols-outlined" aria-hidden="true">view_agenda</span>
					</button>
					<button
						type="button"
						class="codelag-theme-panel__cols-btn"
						data-codelag-columns="2"
						aria-pressed="<?php echo 2 === $current_columns ? 'true' : 'false'; ?>"
						aria-label="<?php esc_attr_e( 'Two columns', 'codelagoon' ); ?>"
					>
						<span class="material-symbols-outlined" aria-hidden="true">splitscreen</span>
					</button>
				</div>
			</div>
		</div>
	</aside>
	<?php
}
add_action( 'wp_footer', 'codelag_render_theme_panel' );

/**
 * Enqueue the small vanilla-JS module that wires the panel up.
 */
function codelag_theme_panel_assets(): void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$handle = 'codelagoon-theme-panel';
	$src    = get_theme_file_uri( 'assets/js/theme-panel.js' );
	$path   = get_theme_file_path( 'assets/js/theme-panel.js' );
	if ( ! file_exists( $path ) ) {
		return;
	}
	wp_enqueue_script( $handle, $src, array(), (string) filemtime( $path ), true );
}
add_action( 'wp_enqueue_scripts', 'codelag_theme_panel_assets' );
