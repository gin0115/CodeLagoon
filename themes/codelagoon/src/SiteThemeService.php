<?php
/**
 * Service that applies the site-wide theme to the frontend and lets users
 * pick their preferred theme from their wp-admin profile.
 *
 * @package Gin0115\Codelagoon\Theme
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Theme;

use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Connects the static theme registry (SiteThemes) to WordPress:
 *
 *  - Emits every theme's CSS custom properties as a single <style> block
 *    in <head>, scoped per theme via `[data-site-theme="..."]`. Component
 *    CSS authored against `var(--cl-*)` then swaps palette instantly when
 *    the attribute on <html> changes — no stylesheet reload.
 *  - Adds `data-site-theme` to the <html> element server-side with the
 *    current user's preference (or the system default for guests) so the
 *    first paint already has the right theme and avoids FOUC.
 *  - Registers the theme preference as user meta (`codelag_site_theme`)
 *    and shows a <select> on the user's profile page so they can pick.
 *    Saving validates through `SiteThemes::sanitize()`.
 */
final class SiteThemeService {

	/**
	 * User meta key storing the chosen site theme slug.
	 */
	public const META_KEY = 'codelag_site_theme';

	/**
	 * User meta key storing the chosen code-syntax theme slug. Separate from
	 * the site theme so users can pair a dark UI with a light syntax theme
	 * (or vice versa) if they prefer.
	 */
	public const SYNTAX_META_KEY = 'codelag_syntax_theme';

	/**
	 * Syntax theme choices — must stay in sync with view.js's THEMES map.
	 *
	 * @var array<string,string>
	 */
	public const SYNTAX_CHOICES = array(
		'tomorrow'       => 'Tomorrow Night (dark)',
		'okaidia'        => 'Okaidia (dark)',
		'twilight'       => 'Twilight (dark)',
		'default'        => 'Default (light)',
		'coy'            => 'Coy (light)',
		'solarizedlight' => 'Solarized (light)',
	);

	public const SYNTAX_DEFAULT = 'tomorrow';

	/**
	 * User meta key storing the preferred archive column count (1 or 2).
	 */
	public const COLUMNS_META_KEY = 'codelag_archive_columns';

	public const COLUMNS_DEFAULT = 1;

	/**
	 * Hook the service into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'wp_head', array( $this, 'print_theme_css' ), 1 );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ) );
		add_action( 'show_user_profile', array( $this, 'render_profile_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_field' ) );
	}

	/**
	 * Register the user-meta key so it persists through core's meta
	 * sanitisation and is exposed via REST (context=edit).
	 */
	public function register_meta(): void {
		register_meta(
			'user',
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => SiteThemes::DEFAULT_THEME,
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => SiteThemes::keys(),
					),
				),
				'sanitize_callback' => array( SiteThemes::class, 'sanitize' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $user_id ) {
					return get_current_user_id() === (int) $user_id
						|| current_user_can( 'edit_user', (int) $user_id );
				},
			)
		);

		register_meta(
			'user',
			self::SYNTAX_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => self::SYNTAX_DEFAULT,
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array_keys( self::SYNTAX_CHOICES ),
					),
				),
				'sanitize_callback' => array( self::class, 'sanitize_syntax_theme' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $user_id ) {
					return get_current_user_id() === (int) $user_id
						|| current_user_can( 'edit_user', (int) $user_id );
				},
			)
		);

		register_meta(
			'user',
			self::COLUMNS_META_KEY,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => self::COLUMNS_DEFAULT,
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'integer',
						'enum' => array( 1, 2 ),
					),
				),
				'sanitize_callback' => array( self::class, 'sanitize_columns' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $user_id ) {
					return get_current_user_id() === (int) $user_id
						|| current_user_can( 'edit_user', (int) $user_id );
				},
			)
		);
	}

	/**
	 * Validate a column count. Only 1 or 2 are allowed.
	 *
	 * @param mixed $candidate Raw value.
	 */
	public static function sanitize_columns( $candidate ): int {
		$value = (int) $candidate;
		return 2 === $value ? 2 : self::COLUMNS_DEFAULT;
	}

	/**
	 * Validate a candidate syntax-theme slug. Returns the default if unknown.
	 *
	 * @param mixed $candidate Raw value.
	 */
	public static function sanitize_syntax_theme( $candidate ): string {
		if ( is_string( $candidate ) && isset( self::SYNTAX_CHOICES[ $candidate ] ) ) {
			return $candidate;
		}
		return self::SYNTAX_DEFAULT;
	}

	/**
	 * Emit the per-theme CSS custom property blocks into <head>. One block
	 * per theme means swapping `data-site-theme` on the root element is the
	 * only thing needed to change palette at runtime.
	 */
	public function print_theme_css(): void {
		$css = '';
		foreach ( SiteThemes::all() as $key => $definition ) {
			$vars = '';
			foreach ( $definition['vars'] as $property => $value ) {
				$vars .= sprintf( '%s:%s;', $property, $value );
			}
			$css .= sprintf( '[data-site-theme="%s"]{%s}', $key, $vars );
		}

		if ( '' === $css ) {
			return;
		}

		echo '<style id="codelag-site-themes">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Append `data-site-theme="..."` to the <html> element (filtered by
	 * `language_attributes`) so the first paint has the correct theme.
	 *
	 * @param string $attributes Existing language attributes string.
	 */
	public function filter_language_attributes( string $attributes ): string {
		$theme   = $this->resolve_current_theme();
		$columns = $this->resolve_current_columns();
		return $attributes
			. sprintf( ' data-site-theme="%s"', esc_attr( $theme ) )
			. sprintf( ' data-codelag-cols="%d"', $columns );
	}

	/**
	 * Render the theme picker on the user's profile page.
	 *
	 * @param WP_User $user User being edited.
	 */
	public function render_profile_field( WP_User $user ): void {
		$current_site = (string) get_user_meta( $user->ID, self::META_KEY, true );
		if ( '' === $current_site ) {
			$current_site = SiteThemes::DEFAULT_THEME;
		}

		$current_syntax = (string) get_user_meta( $user->ID, self::SYNTAX_META_KEY, true );
		if ( '' === $current_syntax ) {
			$current_syntax = self::SYNTAX_DEFAULT;
		}

		$current_columns = (int) get_user_meta( $user->ID, self::COLUMNS_META_KEY, true );
		if ( 1 !== $current_columns && 2 !== $current_columns ) {
			$current_columns = self::COLUMNS_DEFAULT;
		}

		$site_choices = SiteThemes::choices();
		?>
		<h2><?php esc_html_e( 'CodeLagoon appearance', 'codelagoon' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="codelag_site_theme"><?php esc_html_e( 'Site theme', 'codelagoon' ); ?></label>
				</th>
				<td>
					<select name="<?php echo esc_attr( self::META_KEY ); ?>" id="codelag_site_theme">
						<?php foreach ( $site_choices as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_site, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Applies across every CodeLagoon page you view.', 'codelagoon' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="codelag_syntax_theme"><?php esc_html_e( 'Code syntax theme', 'codelagoon' ); ?></label>
				</th>
				<td>
					<select name="<?php echo esc_attr( self::SYNTAX_META_KEY ); ?>" id="codelag_syntax_theme">
						<?php foreach ( self::SYNTAX_CHOICES as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_syntax, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Only affects the colours inside file cards on lagoon pages.', 'codelagoon' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="codelag_archive_columns"><?php esc_html_e( 'Archive columns', 'codelagoon' ); ?></label>
				</th>
				<td>
					<select name="<?php echo esc_attr( self::COLUMNS_META_KEY ); ?>" id="codelag_archive_columns">
						<option value="1" <?php selected( $current_columns, 1 ); ?>><?php esc_html_e( '1 column', 'codelagoon' ); ?></option>
						<option value="2" <?php selected( $current_columns, 2 ); ?>><?php esc_html_e( '2 columns', 'codelagoon' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'How many columns of snippet cards to show on archive pages.', 'codelagoon' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the profile field. Runs on both own-profile and other-user
	 * edit screens.
	 *
	 * @param int $user_id User being saved.
	 */
	public function save_profile_field( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core handles the profile nonce.
		if ( isset( $_POST[ self::META_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$candidate = wp_unslash( (string) $_POST[ self::META_KEY ] );
			update_user_meta( $user_id, self::META_KEY, SiteThemes::sanitize( $candidate ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ self::SYNTAX_META_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$candidate = wp_unslash( (string) $_POST[ self::SYNTAX_META_KEY ] );
			update_user_meta( $user_id, self::SYNTAX_META_KEY, self::sanitize_syntax_theme( $candidate ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ self::COLUMNS_META_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$candidate = wp_unslash( (string) $_POST[ self::COLUMNS_META_KEY ] );
			update_user_meta( $user_id, self::COLUMNS_META_KEY, self::sanitize_columns( $candidate ) );
		}
	}

	/**
	 * Resolve the active theme slug for the current request. Logged-in
	 * users get their meta choice; guests fall back to a cookie written by
	 * the floating theme panel, then to the system default.
	 */
	private function resolve_current_theme(): string {
		if ( is_user_logged_in() ) {
			$stored = (string) get_user_meta( get_current_user_id(), self::META_KEY, true );
			if ( '' !== $stored ) {
				return SiteThemes::sanitize( $stored );
			}
			return SiteThemes::DEFAULT_THEME;
		}
		if ( isset( $_COOKIE[ self::META_KEY ] ) ) {
			return SiteThemes::sanitize( wp_unslash( (string) $_COOKIE[ self::META_KEY ] ) );
		}
		return SiteThemes::DEFAULT_THEME;
	}

	/**
	 * Resolve the archive column count for the current viewer. Logged-in
	 * users get their stored preference (1 or 2); guests fall back to a
	 * cookie written by the floating theme panel, then to the default.
	 * Guaranteed to return 1 or 2.
	 */
	private function resolve_current_columns(): int {
		if ( is_user_logged_in() ) {
			$stored = (int) get_user_meta( get_current_user_id(), self::COLUMNS_META_KEY, true );
			if ( 1 === $stored || 2 === $stored ) {
				return $stored;
			}
			return self::COLUMNS_DEFAULT;
		}
		if ( isset( $_COOKIE[ self::COLUMNS_META_KEY ] ) ) {
			return self::sanitize_columns( wp_unslash( (string) $_COOKIE[ self::COLUMNS_META_KEY ] ) );
		}
		return self::COLUMNS_DEFAULT;
	}
}
