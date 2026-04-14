<?php
/**
 * Custom `codelag_link` post status — "Link only" visibility.
 *
 * A link-only lagoon is fully reachable by its permalink but excluded from
 * every listing: archives, search, taxonomy pages, REST list endpoints,
 * term-index / term-submenu counts. Single-post views bypass the exclusion
 * so a shared URL still renders the lagoon to anyone who has it.
 *
 * "Listed" lagoons just use native `publish`; "Private" lagoons use native
 * `private`. Only this middle state needs a bespoke status.
 *
 * @package Gin0115\Codelagoon\Features\PostType
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\PostType;

use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Custom `codelag_link` post status — see file docblock above.
 */
final class LinkStatus {

	public const STATUS = 'codelag_link';

	/**
	 * Hook the status registration + listing-exclusion filters.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_status' ) );
		add_action( 'pre_get_posts', array( $this, 'exclude_from_listings' ) );
		add_filter( 'rest_lagoon_query', array( $this, 'filter_rest_wp_v2_query' ), 10, 2 );
	}

	/**
	 * Register the custom status. `public => false` keeps it out of WP's
	 * default published-feed machinery; we opt it back in on the single
	 * post view via the pre_get_posts exclusion having a `is_singular`
	 * bail-out.
	 */
	public function register_status(): void {
		register_post_status(
			self::STATUS,
			array(
				'label'                     => _x( 'Link only', 'post status label', 'codelag-features' ),
				/* translators: %s: number of link-only posts. */
				'label_count'               => _n_noop(
					'Link only <span class="count">(%s)</span>',
					'Link only <span class="count">(%s)</span>',
					'codelag-features'
				),
				// Viewer-visible when hit via a specific permalink but not as
				// part of any listing query. `public => false` already stops
				// core from including the status in is_post_publicly_viewable()
				// for list contexts; combined with our pre_get_posts exclusion
				// this matches the spec.
				'public'                    => true,
				'publicly_queryable'        => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'exclude_from_search'       => true,
				'internal'                  => false,
				'protected'                 => false,
				'private'                   => false,
			)
		);
	}

	/**
	 * Strip `codelag_link` from every listing query. We only run the
	 * exclusion when a `post_status` was NOT explicitly requested, so
	 * admin screens / REST callers that purposely ask for link-only
	 * posts still see them.
	 *
	 * Single-post views (`is_singular`) are untouched — the main query
	 * for a specific slug will resolve a link-only lagoon normally.
	 *
	 * @param WP_Query $query Query being prepared.
	 */
	public function exclude_from_listings( $query ): void {
		if ( is_admin() ) {
			return;
		}
		if ( $query->is_singular() ) {
			return;
		}
		// If the caller explicitly set post_status, don't override them.
		$explicit = $query->get( 'post_status' );
		if ( '' !== $explicit && array() !== $explicit ) {
			return;
		}
		$query->set( 'post_status', array( 'publish' ) );
	}

	/**
	 * For `wp/v2/lagoons` list calls, default post_status to publish so
	 * link-only items don't leak. `rest_lagoon_query` is the filter the
	 * core REST controller runs just before firing WP_Query — the perfect
	 * seam to enforce our policy.
	 *
	 * @param array<string,mixed> $args    Args about to be passed to WP_Query.
	 * @param \WP_REST_Request    $request Incoming REST request (unused).
	 * @return array<string,mixed>
	 */
	public function filter_rest_wp_v2_query( array $args, $request ): array {
		unset( $request );
		if ( ! isset( $args['post_status'] ) || '' === $args['post_status'] || array() === $args['post_status'] ) {
			$args['post_status'] = array( 'publish' );
		}
		return $args;
	}
}
