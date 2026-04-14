<?php
/**
 * Keeps the files table's denormalised columns in sync with the parent lagoon,
 * and cascades file deletion when a lagoon is removed.
 *
 * @package Gin0115\Codelagoon\Features\Database
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Database;

use Gin0115\Codelagoon\Features\Meta\LagoonMeta;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up three responsibilities:
 *
 * 1. `save_post_lagoon` — whenever a lagoon is saved, sync `files.owner_id` from
 *    the lagoon's current `post_author`. Idempotent UPDATE; cheap if unchanged.
 * 2. `updated_post_meta` / `added_post_meta` for `_lagoon_visibility` — whenever
 *    a lagoon's visibility changes (including via REST, which bypasses save_post),
 *    sync `files.visibility` for every file owned by that lagoon.
 * 3. `before_delete_post` — when a lagoon is permanently deleted, cascade-remove
 *    every matching row in the files table. (Trashed posts are not touched; only
 *    permanent deletion cascades, matching how WP handles `wp_postmeta`.)
 */
final class Denormalisation {

	/**
	 * File repository used for sync + cascade operations.
	 *
	 * @var FileRepository
	 */
	private FileRepository $files;

	/**
	 * Wire in the shared file repository.
	 *
	 * @param FileRepository $files Repository the denormaliser writes through.
	 */
	public function __construct( FileRepository $files ) {
		$this->files = $files;
	}

	/**
	 * Hook everything into WordPress.
	 */
	public function register(): void {
		add_action( 'save_post_' . LagoonPostType::POST_TYPE, array( $this, 'on_save_lagoon' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post' ), 10, 2 );
	}

	/**
	 * Sync `files.owner_id` on every lagoon save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update (unused).
	 */
	public function on_save_lagoon( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( false !== wp_is_post_revision( $post_id ) || false !== wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->files->sync_owner( $post_id, (int) $post->post_author );
	}

	/**
	 * Sync `files.visibility` whenever `_lagoon_visibility` meta is written.
	 *
	 * Fires on both `updated_post_meta` and `added_post_meta` so the first-ever
	 * visibility write is also captured. Ignores any other meta key.
	 *
	 * @param int    $meta_id     Meta ID (unused).
	 * @param int    $object_id   Post ID.
	 * @param string $meta_key    Meta key being written.
	 * @param mixed  $meta_value  New meta value.
	 */
	public function on_meta_changed( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		unset( $meta_id );

		if ( LagoonMeta::META_VISIBILITY !== $meta_key ) {
			return;
		}

		$post = get_post( $object_id );
		if ( ! $post instanceof WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		$this->files->sync_visibility( $object_id, (string) $meta_value );
	}

	/**
	 * Cascade-delete every file row when a lagoon is permanently deleted.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_before_delete_post( int $post_id, WP_Post $post ): void {
		if ( LagoonPostType::POST_TYPE !== $post->post_type ) {
			return;
		}

		$this->files->delete_all_for_lagoon( $post_id );
	}
}
