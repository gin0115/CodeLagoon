<?php
/**
 * Parent index pages for lagoon taxonomies.
 *
 * WordPress only auto-registers archives for individual terms, not for the
 * taxonomy root. This class adds rewrite rules for the three lagoon
 * taxonomies so `/snippets/language/`, `/snippets/tag/`, and
 * `/snippets/purpose/` resolve to dedicated index pages instead of 404-ing.
 *
 * Each URL sets a query var (`codelag_term_index`) carrying the taxonomy
 * slug, which the theme uses to select the right template and the
 * `codelag/term-index` block uses to list terms.
 *
 * @package Gin0115\Codelagoon\Features\Query
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Query;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonPurposeTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Adds `/snippets/{language|tag|purpose}/` parent index pages for the lagoon
 * taxonomies — see file docblock above for the full rationale.
 */
final class TermIndexRewrite {

	/**
	 * Query var carrying the taxonomy slug of the current index page.
	 */
	public const QUERY_VAR = 'codelag_term_index';

	/**
	 * URL prefix → taxonomy slug map. The prefix is relative to home_url()
	 * and is the same base the taxonomies themselves rewrite into — picking
	 * up the parent by visiting the base without a term.
	 *
	 * @return array<string,string>
	 */
	private function routes(): array {
		return array(
			'snippets/language' => LagoonLanguageTaxonomy::TAXONOMY,
			'snippets/tag'      => LagoonTagTaxonomy::TAXONOMY,
			'snippets/purpose'  => LagoonPurposeTaxonomy::TAXONOMY,
		);
	}

	/**
	 * Hook the rewrite + query var + template override into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		// Block-theme-safe template override: intercept the final selected
		// template file and swap in our `taxonomy-index-<taxonomy>` block
		// template. This works regardless of which hierarchy WP chose first
		// (archive-lagoon wins by default because the rewrite sets
		// `post_type=lagoon` to keep the request resolvable).
		add_filter( 'template_include', array( $this, 'override_template' ), 20 );
		// Also shut off the "is this a post-type archive?" flag so core doesn't
		// try to build an archive queryset, and so conditional tags in the
		// theme (e.g. inside blocks) don't mis-report.
		add_action( 'parse_query', array( $this, 'neutralise_archive_flags' ) );
	}

	/**
	 * Register the rewrite tag + one rewrite rule per taxonomy index page.
	 */
	public function register_rewrite(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([a-z0-9_-]+)' );

		foreach ( $this->routes() as $prefix => $taxonomy ) {
			// Force a harmless query so WP resolves the request without 404.
			// `post_type=lagoon` anchors us in the lagoon context; the real
			// page content comes from the matching block theme template.
			add_rewrite_rule(
				'^' . $prefix . '/?$',
				'index.php?post_type=' . LagoonPostType::POST_TYPE . '&' . self::QUERY_VAR . '=' . $taxonomy,
				'top'
			);
		}
	}

	/**
	 * Add our query var to the public query-var list so WP exposes it.
	 *
	 * @param array<int,string> $vars Existing public query vars.
	 * @return array<int,string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Swap the selected template for our `taxonomy-index-<taxonomy>` block
	 * template on the fly. Block themes render whichever template was
	 * identified by `locate_block_template()` via the globals
	 * `$_wp_current_template_id` / `_content`; we look our template up
	 * directly and override those globals before core's template canvas
	 * renders. Final path returned is the generic template-canvas.php.
	 *
	 * @param string $template Full path of the default selected template.
	 * @return string
	 *
	 * @SuppressWarnings("PHPMD.LongVariable")
	 */
	public function override_template( string $template ): string {
		$taxonomy = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $taxonomy ) {
			return $template;
		}

		$theme     = get_stylesheet();
		$slug      = 'taxonomy-index-' . $taxonomy;
		$block_tpl = get_block_template( $theme . '//' . $slug, 'wp_template' );
		if ( null === $block_tpl || '' === (string) $block_tpl->content ) {
			return $template;
		}

		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP core globals; we don't get to rename them.
		global $_wp_current_template_id, $_wp_current_template_content;
		$_wp_current_template_id      = $block_tpl->id;
		$_wp_current_template_content = $block_tpl->content;
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * Prevent core from treating term-index pages as CPT archives. Without
	 * this, is_post_type_archive() returns true and blocks / filters that
	 * branch on it (e.g. `lagoon-filters` hiding via archive context) fire
	 * incorrectly, and the main query fetches lagoon posts we don't want.
	 *
	 * @param \WP_Query $query Main query being parsed.
	 */
	public function neutralise_archive_flags( $query ): void {
		if ( ! $query->is_main_query() ) {
			return;
		}
		if ( '' === (string) $query->get( self::QUERY_VAR ) ) {
			return;
		}
		$query->is_post_type_archive = false;
		$query->is_archive           = false;
		$query->is_singular          = false;
		// Nothing to loop — keep the query empty so the main template parts
		// don't render a stray post list below our index block.
		$query->set( 'posts_per_page', 0 );
	}

	/**
	 * Resolve the current request's taxonomy slug from the query var, or an
	 * empty string if we're not on a term index page. Blocks / shortcodes
	 * call this to decide what to list.
	 */
	public static function current_taxonomy(): string {
		return (string) get_query_var( self::QUERY_VAR );
	}
}
