<?php
/**
 * Applies lagoon archive URL filters to the main query.
 *
 * Without this hook the Query Loop block (which inherits the main query on
 * archive templates) ignores `?language[]=…&search=…` because those keys are
 * not registered as public query vars. We translate them here into tax_query /
 * date_query / s / post__in via the shared {@see LagoonSearchQuery} helper, so
 * the no-JS path narrows the same way the REST endpoint does.
 *
 * @package Gin0115\Codelagoon\Features\Query
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Query;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Search\LagoonSearchQuery;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonPurposeTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class ArchiveQueryFilter {

	private LagoonSearchQuery $search_query;

	public function __construct( LagoonSearchQuery $search_query ) {
		$this->search_query = $search_query;
	}

	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
	}

	public function apply( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$author_nicename = (string) $query->get( AuthorArchiveRewrite::QUERY_VAR );

		$is_lagoon_archive = $query->is_post_type_archive( LagoonPostType::POST_TYPE )
			|| $query->is_tax(
				array(
					LagoonLanguageTaxonomy::TAXONOMY,
					LagoonTagTaxonomy::TAXONOMY,
					LagoonPurposeTaxonomy::TAXONOMY,
				)
			)
			|| '' !== $author_nicename;
		if ( ! $is_lagoon_archive ) {
			return;
		}

		// Author archive: restrict the query to this user's public lagoons.
		// The rewrite has already routed us here and AuthorArchiveRewrite
		// 404s when the nicename doesn't match — so a user lookup here is
		// safe. Visibility = published only (self-exception TODO).
		if ( '' !== $author_nicename ) {
			$user = get_user_by( 'slug', $author_nicename );
			if ( $user instanceof \WP_User ) {
				$query->set( 'author', (int) $user->ID );
				$query->set( 'post_status', array( 'publish' ) );
			}
		}

		$input = $this->read_input();

		// Build a temporary args container so we can reuse the shared helper,
		// then push the resulting bits back onto the WP_Query via ->set().
		$args = array(
			'tax_query' => $query->get( 'tax_query' ) ?: array(),
		);
		$this->search_query->apply_filters( $args, $input );

		if ( array() !== $args['tax_query'] ) {
			$query->set( 'tax_query', $args['tax_query'] );
		}
		if ( isset( $args['date_query'] ) ) {
			$query->set( 'date_query', $args['date_query'] );
		}

		$search = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		if ( '' !== $search ) {
			$base_args = $this->search_query->base_args_for_pool(
				array(
					'tax_query'  => $args['tax_query'] ?? array(),
					'date_query' => $args['date_query'] ?? null,
				)
			);
			$ids = $this->search_query->resolve_search_post_ids( $search, $base_args );

			// Empty union → force zero results so the archive shows the
			// no-results state instead of falling back to "all posts".
			$query->set( 'post__in', array() === $ids ? array( 0 ) : $ids );
			if ( array() !== $ids ) {
				$query->set( 'orderby', 'post__in' );
			}
		}
	}

	/**
	 * Read filter input from $_GET. All values are sanitised by the shared
	 * helper downstream; this method only normalises the shape.
	 *
	 * @return array<string,mixed>
	 */
	private function read_input(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params.
		$get = wp_unslash( $_GET );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'language'   => $get['language']   ?? null,
			'tag'        => $get['tag']        ?? null,
			'purpose'    => $get['purpose']    ?? null,
			'date_range' => isset( $get['date_range'] ) ? (string) $get['date_range'] : '',
			'search'     => isset( $get['search'] ) ? (string) $get['search'] : '',
		);
	}
}
