<?php
/**
 * Auto-generates a random, immutable slug for every lagoon on first save.
 *
 * @package Gin0115\Codelagoon\Features\PostType
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\PostType;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Slug strategy for the `lagoon` CPT.
 *
 * Lagoons get a random, opaque slug the first time they are saved (i.e. the
 * first save that takes them out of `auto-draft`). The chosen slug is then
 * mirrored into post meta and re-applied on every subsequent save, so even if
 * something tries to change `post_name` later (REST PATCH, the Permalink
 * editor, wp-cli) the change is overwritten and the URL stays stable.
 *
 * The Permalink panel in the block editor is also hidden so users don't see a
 * field they cannot meaningfully edit.
 */
final class SlugGenerator {

	/**
	 * Post meta key that stores the locked slug.
	 *
	 * Underscore prefix keeps it out of the default custom-fields UI.
	 */
	public const META_KEY = '_codelag_lagoon_slug';

	/**
	 * Length of the generated slug, in characters.
	 *
	 * 10 lowercase alphanumeric characters give ~52 bits of entropy — far more
	 * than enough for an internal tool to avoid accidental collisions or
	 * casual enumeration. Short enough to copy/paste comfortably.
	 */
	public const SLUG_LENGTH = 10;

	/**
	 * Hook the slug logic into WordPress.
	 */
	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'enforce_slug' ), 10, 2 );
		add_action( 'save_post_' . LagoonPostType::POST_TYPE, array( $this, 'persist_slug' ), 10, 2 );
		add_action( 'admin_print_styles-post.php', array( $this, 'print_editor_lock_css' ) );
		add_action( 'admin_print_styles-post-new.php', array( $this, 'print_editor_lock_css' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_panel_lock_script' ) );
	}

	/**
	 * Filter callback for `wp_insert_post_data`.
	 *
	 * Decides what `post_name` to write for a lagoon save:
	 *  - Auto-drafts are passed through untouched (no slug yet — wait for the
	 *    first real save).
	 *  - Brand-new posts (no ID) and posts whose previous status was
	 *    `auto-draft` get a fresh random slug.
	 *  - Existing posts that already have a slug stored in meta get that slug
	 *    re-applied, regardless of what was submitted, locking the URL.
	 *  - Existing posts without a meta slug are left alone — legacy lagoons
	 *    keep whatever they had.
	 *
	 * @param array<string,mixed> $data    Post data about to be inserted.
	 * @param array<string,mixed> $postarr Raw post array as supplied to wp_insert_post().
	 * @return array<string,mixed>
	 */
	public function enforce_slug( array $data, array $postarr ): array {
		if ( ! $this->is_lagoon_save( $data ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		// Brand-new insert (e.g. wp-cli). No existing post to consult.
		if ( $post_id <= 0 ) {
			$data['post_name'] = $this->generate_slug();
			return $data;
		}

		return $this->apply_existing_post_slug( $data, $post_id );
	}

	/**
	 * True when the save is for the lagoon CPT and is NOT the transient
	 * `auto-draft` record WP creates on "Add New". We defer slug generation
	 * until the first real save so auto-drafts don't burn a slug.
	 *
	 * @param array<string,mixed> $data Post data from the insert filter.
	 */
	private function is_lagoon_save( array $data ): bool {
		if ( ! isset( $data['post_type'] ) || LagoonPostType::POST_TYPE !== $data['post_type'] ) {
			return false;
		}
		if ( isset( $data['post_status'] ) && 'auto-draft' === $data['post_status'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Resolve the slug for an existing post:
	 *  - meta slug wins (lock),
	 *  - otherwise generate only when transitioning out of `auto-draft`,
	 *  - legacy posts without meta are left alone.
	 *
	 * @param array<string,mixed> $data    Post data from the insert filter.
	 * @param int                 $post_id Existing post ID.
	 * @return array<string,mixed>
	 */
	private function apply_existing_post_slug( array $data, int $post_id ): array {
		$existing_meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $existing_meta ) && '' !== $existing_meta ) {
			$data['post_name'] = $existing_meta;
			return $data;
		}

		// No meta slug yet. If the previous status was `auto-draft`, this is
		// the first real save — generate. Otherwise it's a legacy post that
		// pre-dates this feature; leave its slug alone.
		$existing_post = get_post( $post_id );
		if ( $existing_post instanceof WP_Post && 'auto-draft' === $existing_post->post_status ) {
			$data['post_name'] = $this->generate_slug();
		}

		return $data;
	}

	/**
	 * Persist the freshly-saved slug into post meta so future saves can lock
	 * to it. Runs once per lagoon — subsequent saves see the meta and bail.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function persist_slug( int $post_id, WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status || false !== wp_is_post_revision( $post_id ) ) {
			return;
		}

		$existing = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $existing ) && '' !== $existing ) {
			return;
		}

		if ( '' !== $post->post_name ) {
			update_post_meta( $post_id, self::META_KEY, $post->post_name );
		}
	}

	/**
	 * Print inline CSS that hides the Permalink panel/field on lagoon edit
	 * screens. Cheap belt-and-braces alongside the JS panel removal.
	 */
	public function print_editor_lock_css(): void {
		$screen = get_current_screen();
		if ( null === $screen || LagoonPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}
		?>
		<style id="codelag-lagoon-slug-lock">
			.editor-post-url,
			.edit-post-post-link,
			.editor-post-link,
			.components-panel__body[class*="post-link"],
			.components-panel__body[class*="post-url"],
			.components-panel__body[class*="permalink"] {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Try to remove the Permalink panel from the block editor sidebar via the
	 * core/editor data store. The CSS rule above is the actual safety net —
	 * this is a tidy-up that runs only on lagoon screens.
	 */
	public function enqueue_panel_lock_script(): void {
		$screen = get_current_screen();
		if ( null === $screen || LagoonPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_add_inline_script(
			'wp-edit-post',
			"wp.domReady( function () {
				if ( wp.data && wp.data.dispatch( 'core/editor' ) && wp.data.dispatch( 'core/editor' ).removeEditorPanel ) {
					wp.data.dispatch( 'core/editor' ).removeEditorPanel( 'post-link' );
					wp.data.dispatch( 'core/editor' ).removeEditorPanel( 'permalink' );
				}
			} );"
		);
	}

	/**
	 * Generate a fresh random slug.
	 *
	 * `wp_generate_password` with `$special_chars = false` gives `[a-zA-Z0-9]`;
	 * lowercasing yields a tidy URL-safe slug `[a-z0-9]{10}`.
	 */
	private function generate_slug(): string {
		return strtolower( wp_generate_password( self::SLUG_LENGTH, false, false ) );
	}
}
