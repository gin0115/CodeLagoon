<?php
/**
 * CRUD repository for the lagoon files table.
 *
 * @package Gin0115\Codelagoon\Features\Database
 *
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 *
 * Every SQL statement in this file is built with `$wpdb->prepare()` (or a direct
 * `$wpdb->insert`/`update`/`delete` with explicit format arrays). Table names are
 * interpolated from our own static helpers on Schema, never from user input.
 * The prepared-SQL sniffs cannot reason about interpolated table names, the splat
 * operator, or closures that build placeholder arrays, so they're disabled here.
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Database;

use Gin0115\Codelagoon\Features\Meta\LagoonMeta;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Prepared-statement CRUD layer over `{prefix}codelag_lagoon_files`.
 *
 * File `content` is treated as opaque bytes — it is never passed through
 * `wp_kses`, `sanitize_textarea_field`, `balanceTags`, or any similar filter.
 * `name`, `description`, and `language` are sanitized normally.
 *
 * `owner_id` and `visibility` are derived from the parent lagoon at write time;
 * callers cannot set them directly. The `Denormalisation` class keeps them in
 * sync when the lagoon's author or visibility meta later changes.
 *
 * FULLTEXT search lives in the sibling {@see FileSearch} class — this
 * repository is pure CRUD + counts.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Surface is a deliberate thin
 *   CRUD API — further splitting would scatter closely related wpdb calls
 *   across sibling classes for no readability gain.
 */
final class FileRepository {

	/**
	 * Shared wpdb instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Grab the global wpdb instance.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Insert a new file row for a lagoon. Returns the new file ID or 0 on failure.
	 *
	 * Accepted keys in `$data`:
	 *  - name       (string, required)
	 *  - description(string, optional)
	 *  - language   (string, optional)
	 *  - content    (string, optional, raw — never sanitized)
	 *  - file_order (int,    optional; defaults to append at end)
	 *
	 * @param int                 $lagoon_id Parent lagoon post ID.
	 * @param array<string,mixed> $data      File fields.
	 */
	public function create( int $lagoon_id, array $data ): int {
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return 0;
		}

		$content     = isset( $data['content'] ) ? (string) $data['content'] : '';
		$description = isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '';
		$language    = isset( $data['language'] ) ? self::sanitize_language( (string) $data['language'] ) : '';

		$file_order = isset( $data['file_order'] )
			? max( 0, (int) $data['file_order'] )
			: $this->next_file_order( $lagoon_id );

		$result = $this->wpdb->insert(
			Schema::files_table(),
			array(
				'lagoon_id'    => $lagoon_id,
				'owner_id'     => self::resolve_owner_id( $lagoon_id ),
				'visibility'   => self::resolve_visibility( $lagoon_id ),
				'file_order'   => $file_order,
				'name'         => $name,
				'description'  => $description,
				'language'     => $language,
				'content'      => $content,
				'content_hash' => hash( 'sha256', $content ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Partially update a file row. Only keys present in `$data` are written.
	 *
	 * @param int                 $file_id File ID.
	 * @param array<string,mixed> $data    Subset of { name, description, language, content, file_order }.
	 */
	public function update( int $file_id, array $data ): bool {
		$fields  = array();
		$formats = array();

		if ( array_key_exists( 'name', $data ) ) {
			$fields['name'] = sanitize_text_field( (string) $data['name'] );
			$formats[]      = '%s';
		}
		if ( array_key_exists( 'description', $data ) ) {
			$fields['description'] = sanitize_textarea_field( (string) $data['description'] );
			$formats[]             = '%s';
		}
		if ( array_key_exists( 'language', $data ) ) {
			$fields['language'] = self::sanitize_language( (string) $data['language'] );
			$formats[]          = '%s';
		}
		if ( array_key_exists( 'content', $data ) ) {
			$content                = (string) $data['content'];
			$fields['content']      = $content;
			$fields['content_hash'] = hash( 'sha256', $content );
			$formats[]              = '%s';
			$formats[]              = '%s';
		}
		if ( array_key_exists( 'file_order', $data ) ) {
			$fields['file_order'] = max( 0, (int) $data['file_order'] );
			$formats[]            = '%d';
		}

		if ( array() === $fields ) {
			return true;
		}

		$result = $this->wpdb->update(
			Schema::files_table(),
			$fields,
			array( 'id' => $file_id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a single file row.
	 *
	 * @param int $file_id File ID.
	 */
	public function delete( int $file_id ): bool {
		$result = $this->wpdb->delete(
			Schema::files_table(),
			array( 'id' => $file_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Hard-delete every file row belonging to a lagoon. Returns the number of rows deleted.
	 * Called from the `before_delete_post` hook when a lagoon is deleted.
	 *
	 * @param int $lagoon_id Parent lagoon post ID.
	 */
	public function delete_all_for_lagoon( int $lagoon_id ): int {
		$result = $this->wpdb->delete(
			Schema::files_table(),
			array( 'lagoon_id' => $lagoon_id ),
			array( '%d' )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Fetch a single file row by ID, or null if not found.
	 *
	 * @param int $file_id File ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find( int $file_id ): ?array {
		$table = Schema::files_table();

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $file_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch every file for a lagoon, ordered by `file_order` ascending, then `id`.
	 *
	 * @param int $lagoon_id Parent lagoon post ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_for_lagoon( int $lagoon_id ): array {
		$table = Schema::files_table();

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE lagoon_id = %d ORDER BY file_order ASC, id ASC",
				$lagoon_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Bulk reorder files belonging to a lagoon. Only rows whose IDs appear in
	 * `$ordered_ids` AND belong to `$lagoon_id` are updated. The array index (0-based)
	 * becomes the new `file_order`.
	 *
	 * @param int                   $lagoon_id   Parent lagoon post ID.
	 * @param array<int,int|string> $ordered_ids Ordered list of file IDs.
	 */
	public function reorder( int $lagoon_id, array $ordered_ids ): bool {
		$table   = Schema::files_table();
		$success = true;

		foreach ( array_values( $ordered_ids ) as $index => $file_id ) {
			$file_id = (int) $file_id;
			if ( $file_id <= 0 ) {
				continue;
			}

			$result = $this->wpdb->update(
				$table,
				array( 'file_order' => $index ),
				array(
					'id'        => $file_id,
					'lagoon_id' => $lagoon_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);

			if ( false === $result ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Clone every file from `$source_lagoon_id` to `$new_lagoon_id` in a single
	 * `INSERT ... SELECT`, overriding `lagoon_id`, `owner_id`, and `visibility` for
	 * the new rows. Returns the number of files copied.
	 *
	 * @param int $source_lagoon_id Source lagoon post ID.
	 * @param int $new_lagoon_id    Destination lagoon post ID.
	 */
	public function clone_files( int $source_lagoon_id, int $new_lagoon_id ): int {
		$table      = Schema::files_table();
		$owner_id   = self::resolve_owner_id( $new_lagoon_id );
		$visibility = self::resolve_visibility( $new_lagoon_id );

		$sql = "INSERT INTO {$table}
				(lagoon_id, owner_id, visibility, file_order, name, description, language, content, content_hash)
			SELECT %d, %d, %s, file_order, name, description, language, content, content_hash
			FROM {$table}
			WHERE lagoon_id = %d
			ORDER BY file_order ASC, id ASC";

		$prepared = $this->wpdb->prepare( $sql, $new_lagoon_id, $owner_id, $visibility, $source_lagoon_id );
		if ( null === $prepared ) {
			return 0;
		}
		$result = $this->wpdb->query( $prepared );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Update the denormalised `owner_id` column for every file row of a lagoon.
	 * Called from `Denormalisation` when the lagoon's `post_author` changes.
	 *
	 * @param int $lagoon_id Parent lagoon post ID.
	 * @param int $owner_id  New owner user ID.
	 */
	public function sync_owner( int $lagoon_id, int $owner_id ): int {
		$result = $this->wpdb->update(
			Schema::files_table(),
			array( 'owner_id' => $owner_id ),
			array( 'lagoon_id' => $lagoon_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Update the denormalised `visibility` column for every file row of a lagoon.
	 * Called from `Denormalisation` when `_lagoon_visibility` meta changes.
	 *
	 * @param int    $lagoon_id  Parent lagoon post ID.
	 * @param string $visibility New visibility value.
	 */
	public function sync_visibility( int $lagoon_id, string $visibility ): int {
		if ( ! in_array( $visibility, LagoonMeta::VISIBILITY_VALUES, true ) ) {
			$visibility = LagoonMeta::VISIBILITY_PUBLIC;
		}

		$result = $this->wpdb->update(
			Schema::files_table(),
			array( 'visibility' => $visibility ),
			array( 'lagoon_id' => $lagoon_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Return the next `file_order` value for a lagoon (max + 1, or 0 on an empty set).
	 *
	 * @param int $lagoon_id Parent lagoon post ID.
	 */
	private function next_file_order( int $lagoon_id ): int {
		$table = Schema::files_table();

		$max = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MAX(file_order) FROM {$table} WHERE lagoon_id = %d",
				$lagoon_id
			)
		);

		return null === $max ? 0 : ( (int) $max ) + 1;
	}

	/**
	 * Look up the current `post_author` of a lagoon, or 0 if not found.
	 *
	 * @param int $lagoon_id Parent lagoon post ID.
	 */
	private static function resolve_owner_id( int $lagoon_id ): int {
		$post = get_post( $lagoon_id );
		if ( ! $post instanceof \WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return 0;
		}

		return (int) $post->post_author;
	}

	/**
	 * Look up the current visibility meta of a lagoon, defaulting to "public".
	 *
	 * @param int $lagoon_id Parent lagoon post ID.
	 */
	private static function resolve_visibility( int $lagoon_id ): string {
		$raw = (string) get_post_meta( $lagoon_id, LagoonMeta::META_VISIBILITY, true );

		return in_array( $raw, LagoonMeta::VISIBILITY_VALUES, true ) ? $raw : LagoonMeta::VISIBILITY_PUBLIC;
	}

	/**
	 * Coerce a language identifier into a URL-safe, lowercase slug.
	 *
	 * @param string $language Raw language value.
	 */
	private static function sanitize_language( string $language ): string {
		return substr( sanitize_key( $language ), 0, 32 );
	}
}
