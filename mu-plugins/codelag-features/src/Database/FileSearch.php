<?php
/**
 * FULLTEXT search over the lagoon files table.
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
 * Extracted from {@see FileRepository} so the repository stays focused on CRUD.
 * All SQL here is `$wpdb->prepare()`'d; the prepared-SQL sniffs can't reason
 * about interpolated table names or splatted placeholder arrays, so they're
 * disabled at file scope.
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Database;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Paginated FULLTEXT search over the `codelag_files_fulltext` index, scoped by
 * the caller's visibility capabilities and optional owner/lagoon/language
 * filters. Callers treat this as the source of truth for "which files match
 * this term?" — the REST file-search route and LagoonSearchQuery both use it.
 */
final class FileSearch {

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
	 * Full-text search across file name / description / content.
	 *
	 * Uses the dedicated `codelag_files_fulltext` FULLTEXT index in BOOLEAN MODE,
	 * so callers can pass operator queries like `+php -wordpress "wp_insert_post"`.
	 * Results are scoped by the caller's current capabilities — see
	 * {@see self::build_visibility_where()}.
	 *
	 * Accepted keys in `$args`:
	 *  - q          (string, required) search term
	 *  - language   (string, optional) filter by language slug
	 *  - owner_id   (int,    optional) restrict to a single owner
	 *  - lagoon_id  (int,    optional) restrict to a single lagoon
	 *  - page       (int,    optional, default 1)
	 *  - per_page   (int,    optional, default 20, max 100)
	 *
	 * @param array<string,mixed> $args Search parameters.
	 *
	 * @return array{files:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function search( array $args ): array {
		$query_string = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		$page         = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page     = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;

		$empty = array(
			'files'    => array(),
			'total'    => 0,
			'page'     => $page,
			'per_page' => $per_page,
		);

		if ( '' === $query_string ) {
			return $empty;
		}

		[ $base_sql, $base_params ] = $this->build_search_base( $query_string, $args );

		$total = (int) $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT COUNT(*) {$base_sql}", $base_params )
		);

		if ( 0 === $total ) {
			return $empty;
		}

		$rows = $this->run_search_list( $base_sql, $base_params, $query_string, $page, $per_page );

		return array(
			'files'    => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Build the shared `FROM … JOIN … WHERE …` fragment used by both the
	 * count query and the list query, plus the placeholder values that
	 * hydrate it.
	 *
	 * @param string              $query_string Search term, already trimmed.
	 * @param array<string,mixed> $args         Full search args (for optional filters).
	 * @return array{0:literal-string,1:array<int,mixed>} SQL fragment + placeholder values.
	 */
	private function build_search_base( string $query_string, array $args ): array {
		$files_table = Schema::files_table();
		$posts_table = $this->wpdb->posts;

		$where  = array( 'MATCH(f.name, f.description, f.content) AGAINST (%s IN BOOLEAN MODE)' );
		$params = array( $query_string );

		$where[] = $this->build_visibility_where( $params );

		$this->append_optional_filters( $args, $where, $params );

		$where_sql = implode( ' AND ', $where );
		/**
		 * `$files_table` is `literal-string` (Schema), but `$wpdb->posts` is
		 * typed plain `string` in core stubs and `$where_sql` is built from a
		 * runtime accumulator. Asserted as a whole so the composed SQL can be
		 * passed straight into `wpdb::prepare()`.
		 *
		 * @var literal-string $base_sql
		 */
		$base_sql = "FROM {$files_table} f
				INNER JOIN {$posts_table} p ON p.ID = f.lagoon_id AND p.post_type = %s
				WHERE {$where_sql}";

		$base_params = array_merge( array( LagoonPostType::POST_TYPE ), $params );

		return array( $base_sql, $base_params );
	}

	/**
	 * Append language / owner_id / lagoon_id WHERE clauses when the caller
	 * asked for them, with params pushed onto the accumulator in the same order.
	 *
	 * @param array<string,mixed> $args   Raw search args.
	 * @param array<int,string>   $where  WHERE clause accumulator.
	 * @param array<int,mixed>    $params Placeholder value accumulator.
	 */
	private function append_optional_filters( array $args, array &$where, array &$params ): void {
		if ( isset( $args['language'] ) && '' !== (string) $args['language'] ) {
			$where[]  = 'f.language = %s';
			$params[] = substr( sanitize_key( (string) $args['language'] ), 0, 32 );
		}
		if ( isset( $args['owner_id'] ) && (int) $args['owner_id'] > 0 ) {
			$where[]  = 'f.owner_id = %d';
			$params[] = (int) $args['owner_id'];
		}
		if ( isset( $args['lagoon_id'] ) && (int) $args['lagoon_id'] > 0 ) {
			$where[]  = 'f.lagoon_id = %d';
			$params[] = (int) $args['lagoon_id'];
		}
	}

	/**
	 * Run the paginated list query using the already-built base fragment.
	 *
	 * @param literal-string   $base_sql     `FROM … JOIN … WHERE …` fragment.
	 * @param array<int,mixed> $base_params  Placeholder values for $base_sql.
	 * @param string           $query_string Search term.
	 * @param int              $page         1-indexed page number.
	 * @param int              $per_page     Page size.
	 * @return array<int,array<string,mixed>>
	 */
	private function run_search_list( string $base_sql, array $base_params, string $query_string, int $page, int $per_page ): array {
		$offset      = ( $page - 1 ) * $per_page;
		$list_sql    = "SELECT f.*, MATCH(f.name, f.description, f.content) AGAINST (%s IN BOOLEAN MODE) AS score
			{$base_sql}
			ORDER BY score DESC, f.updated_at DESC
			LIMIT %d OFFSET %d";
		$list_params = array_merge( array( $query_string ), $base_params, array( $per_page, $offset ) );

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( $list_sql, $list_params ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build a WHERE fragment enforcing visibility rules for the current user.
	 * Appends placeholder values to `$params` by reference.
	 *
	 *  - `read_private_posts` caps → no filter (admins/editors see everything).
	 *  - Logged-in non-admins → public published files OR any of their own files.
	 *  - Anonymous → public published files only.
	 *
	 * @param array<int,mixed> $params Placeholder values accumulator.
	 */
	private function build_visibility_where( array &$params ): string {
		if ( current_user_can( 'read_private_posts' ) ) {
			return '1=1';
		}

		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			$params[] = 'public';
			return "( f.visibility = %s AND p.post_status = 'publish' )";
		}

		$params[] = 'public';
		$params[] = $user_id;
		return "( ( f.visibility = %s AND p.post_status = 'publish' ) OR f.owner_id = %d )";
	}
}
