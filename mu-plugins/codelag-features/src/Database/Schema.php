<?php
/**
 * Custom table schema + installer for the lagoon files and fork history tables.
 *
 * @package Gin0115\Codelagoon\Features\Database
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Installs and upgrades the custom table that backs the lagoon file storage.
 *
 * The files table owns the raw file contents (with per-row denormalised owner and
 * visibility so search can run without joining `wp_posts`).
 *
 * Fork ancestry is NOT stored in a custom table. It lives in a `_lagoon_fork_history`
 * post meta field as a snapshotted JSON array — see {@see Gin0115\Codelagoon\Features\Meta\LagoonMeta}.
 *
 * Version-checked against an option so installation is a cheap `get_option` on
 * every other request. Bump {@see Schema::VERSION} to trigger an upgrade pass —
 * `dbDelta` will add any new columns or indexes without touching existing data.
 */
final class Schema {

	/**
	 * Current schema revision. Bump when adding columns or indexes.
	 */
	public const VERSION = '1';

	/**
	 * Option name storing the last installed schema version.
	 */
	private const OPTION_NAME = 'codelag_features_db_version';

	/**
	 * Unprefixed table name for the files table.
	 */
	public const TABLE_FILES = 'codelag_lagoon_files';

	/**
	 * Hook the schema installer onto `init` ahead of anything that queries the tables.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_install' ), 5 );
	}

	/**
	 * Prefixed table name for the files table.
	 *
	 * Declared `literal-string` so phpstan treats interpolated SQL like
	 * `"SELECT * FROM {$table}"` as safe to pass to `wpdb::prepare()` — the
	 * return value is composed from a class constant and `$wpdb->prefix`,
	 * never from user input.
	 *
	 * @return literal-string
	 */
	public static function files_table(): string {
		global $wpdb;
		// `$wpdb->prefix` is typed plain `string` in core stubs, so phpstan
		// infers `non-falsy-string` on the concat and can't prove literal-string.
		// The value is composed from `$wpdb->prefix` and our own class constant —
		// never user input — so the `@return literal-string` contract holds.
		// @phpstan-ignore return.type
		return $wpdb->prefix . self::TABLE_FILES;
	}

	/**
	 * Run the installer if the stored schema version is behind {@see Schema::VERSION}.
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		$current = (string) get_option( self::OPTION_NAME, '' );
		if ( self::VERSION === $current ) {
			return;
		}

		$this->install();
		update_option( self::OPTION_NAME, self::VERSION, false );
	}

	/**
	 * Create or upgrade the files table via `dbDelta`.
	 *
	 * @return void
	 */
	private function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$files_table     = self::files_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$files_sql = "CREATE TABLE {$files_table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	lagoon_id BIGINT UNSIGNED NOT NULL,
	owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	visibility ENUM('public','private','link') NOT NULL DEFAULT 'public',
	file_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	name VARCHAR(255) NOT NULL,
	description TEXT NOT NULL,
	language VARCHAR(32) NOT NULL DEFAULT '',
	content LONGTEXT NOT NULL,
	content_hash CHAR(64) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY lagoon_order (lagoon_id, file_order),
	KEY owner (owner_id),
	KEY language (language),
	KEY visibility (visibility),
	FULLTEXT KEY codelag_files_fulltext (name, description, content)
) {$charset_collate};";
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

		dbDelta( $files_sql );
	}
}
