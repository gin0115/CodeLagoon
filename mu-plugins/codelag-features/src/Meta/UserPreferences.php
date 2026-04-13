<?php
/**
 * Registers per-user preference meta used by the block editor.
 *
 * @package Gin0115\Codelagoon\Features\Meta
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * User meta used by the CodeLagoon block editor.
 *
 * - `codelag_editor_theme` — the Monaco theme slug the current user picked.
 *                            Stored per-user so every lagoon they edit uses the
 *                            same editor theme without any per-post wiring.
 *                            Exposed through REST so the block can read and
 *                            write it via the standard `wp/v2/users/me` route.
 */
final class UserPreferences {

	public const META_EDITOR_THEME = 'codelag_editor_theme';

	/**
	 * Default theme used when the user has not picked one yet.
	 */
	public const DEFAULT_THEME = 'vs-dark';

	/**
	 * Hook meta registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Register the editor-theme user meta.
	 */
	public function register_meta(): void {
		register_meta(
			'user',
			self::META_EDITOR_THEME,
			array(
				'type'              => 'string',
				'description'       => __( 'Monaco editor theme the user prefers for the CodeLagoon block editor.', 'codelag-features' ),
				'single'            => true,
				'default'           => self::DEFAULT_THEME,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( self::class, 'can_edit_own_meta' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => self::DEFAULT_THEME,
					),
				),
			)
		);
	}

	/**
	 * Allow a user to read and write their own preference meta, and allow
	 * administrators to edit anyone's.
	 *
	 * @param bool   $allowed  Whether the user is currently allowed (unused).
	 * @param string $meta_key Meta key being checked (unused).
	 * @param int    $user_id  Target user id the meta belongs to.
	 *
	 * @return bool
	 */
	public static function can_edit_own_meta( bool $allowed, string $meta_key, int $user_id ): bool {
		unset( $allowed, $meta_key );
		return get_current_user_id() === $user_id || current_user_can( 'edit_users' );
	}
}
