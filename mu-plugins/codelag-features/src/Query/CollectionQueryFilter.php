<?php
/**
 * Scopes the lagoon query loop on the "My Collection" page to the current
 * user's saved lagoons.
 *
 * The collection page is any regular WP Page using the
 * `page-my-collection.html` block template. The page's template includes a
 * non-inheriting Query Loop block whose `post_type` is `lagoon` — we hook
 * `pre_get_posts` on that secondary query and inject `post__in` from the
 * caller's user meta, plus honour the same `?language=…&search=…` filters the
 * main lagoon archive supports, by delegating to {@see LagoonSearchQuery}.
 *
 * Kept entirely separate from {@see ArchiveQueryFilter} so archive behaviour
 * is untouched — this filter only fires on the collection page.
 *
 * @package Gin0115\Codelagoon\Features\Query
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Query;

use Gin0115\Codelagoon\Features\Meta\UserCollection;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Search\LagoonSearchQuery;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Applies "my collection" scoping + filter/search to the lagoon query loop on
 * the My Collection page.
 */
final class CollectionQueryFilter {

	/**
	 * Template filename that identifies a Page as the collection view. This
	 * is the single source of truth — we never key off the page's slug,
	 * because the slug is user-editable in wp-admin and renaming the URL
	 * must not silently break scoping. wp-admin's block-theme template
	 * picker stores the assignment as either the bare slug or the full
	 * filename depending on WP version/flow; we accept both.
	 */
	private const PAGE_TEMPLATE_SLUG = 'page-my-collection';
	private const PAGE_TEMPLATE_FILE = 'page-my-collection.html';

	/**
	 * Shared filter/search helper (reused by the archive path).
	 *
	 * @var LagoonSearchQuery
	 */
	private LagoonSearchQuery $search_query;

	/**
	 * Wire in the shared search/filter helper.
	 *
	 * @param LagoonSearchQuery $search_query Shared filter/search helper.
	 */
	public function __construct( LagoonSearchQuery $search_query ) {
		$this->search_query = $search_query;
	}

	/**
	 * Hook pre_get_posts plus the lagoon-filters form-action hook that keeps
	 * the filter bar posting back to this page (no-JS path).
	 */
	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
		add_filter( 'codelag_filters_form_action', array( $this, 'filter_form_action' ) );
	}

	/**
	 * Keep the filter-bar form posting back to the collection page itself
	 * (rather than the CPT archive) so the no-JS submit stays in scope.
	 *
	 * @param string $action_url Default action URL from the block.
	 */
	public function filter_form_action( string $action_url ): string {
		if ( ! $this->is_collection_page_request() ) {
			return $action_url;
		}
		$page = get_queried_object();
		if ( ! $page instanceof WP_Post ) {
			return $action_url;
		}
		$permalink = get_permalink( $page );
		return is_string( $permalink ) && '' !== $permalink ? $permalink : $action_url;
	}

	/**
	 * Filter secondary lagoon queries while the main query is on the
	 * collection page. See class docblock for the gating rationale.
	 */
	public function apply( WP_Query $query ): void {
		if ( is_admin() || $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		if ( LagoonPostType::POST_TYPE !== $post_type ) {
			return;
		}

		if ( ! $this->is_collection_page_request() ) {
			return;
		}

		// Restrict to the user's collected lagoons. Logged-out / empty → [0]
		// so the query's no-results path fires instead of falling back to
		// "every lagoon".
		$collection_ids = is_user_logged_in()
			? UserCollection::get_ids( get_current_user_id() )
			: array();
		$query->set( 'post__in', array() === $collection_ids ? array( 0 ) : $collection_ids );

		// Apply the same filter/search grammar the archive supports, so the
		// filter bar on the collection page narrows within the collection.
		$input = $this->read_input();

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
			$matched = $this->search_query->resolve_search_post_ids( $search, $base_args );

			// Intersect search hits with the collection so we always narrow
			// within the user's saved set. Empty intersection → [0].
			$collection_set = array() === $collection_ids ? array( 0 ) : $collection_ids;
			$narrowed       = array_values( array_intersect( $collection_set, $matched ) );

			$query->set( 'post__in', array() === $narrowed ? array( 0 ) : $narrowed );
			if ( array() !== $narrowed ) {
				$query->set( 'orderby', 'post__in' );
			}
		}
	}

	/**
	 * True when the current main query is a Page whose assigned template is
	 * our collection template. Slug is deliberately ignored — see the
	 * PAGE_TEMPLATE_* constants for the reasoning.
	 */
	private function is_collection_page_request(): bool {
		if ( ! is_page() ) {
			return false;
		}
		$page = get_queried_object();
		if ( ! $page instanceof WP_Post ) {
			return false;
		}
		$tpl = (string) get_page_template_slug( $page );
		return self::PAGE_TEMPLATE_SLUG === $tpl || self::PAGE_TEMPLATE_FILE === $tpl;
	}

	/**
	 * Read filter input from $_GET. Matches the shape ArchiveQueryFilter uses
	 * so the downstream helper treats both paths identically.
	 *
	 * @return array<string,mixed>
	 */
	private function read_input(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params.
		$get = wp_unslash( $_GET );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'filter_language' => $get['filter_language'] ?? null,
			'filter_tag'      => $get['filter_tag'] ?? null,
			'filter_purpose'  => $get['filter_purpose'] ?? null,
			'date_range'      => isset( $get['date_range'] ) ? (string) $get['date_range'] : '',
			'search'          => isset( $get['search'] ) ? (string) $get['search'] : '',
		);
	}
}
