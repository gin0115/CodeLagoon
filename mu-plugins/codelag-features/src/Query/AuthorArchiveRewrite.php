<?php
/**
 * Custom author archive at /snippets/by/<user_nicename>/.
 *
 * Owns the rewrite rule, the query var, the template selection, and the
 * sitewide `author_link` filter that re-routes WP's default author URLs onto
 * this custom slug. Author *scoping* of the main query (and 404 handling for
 * unknown users) is done by {@see ArchiveQueryFilter} so the pre_get_posts
 * logic stays in one place.
 *
 * @package Gin0115\Codelagoon\Features\Query
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Query;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_User;

defined( 'ABSPATH' ) || exit;

final class AuthorArchiveRewrite {

	/**
	 * Query var carrying the author's user_nicename when this archive matches.
	 */
	public const QUERY_VAR = 'codelag_author';

	/**
	 * URL prefix under the CPT base, e.g. /snippets/by/<nicename>/.
	 */
	public const URL_PREFIX = 'snippets/by';

	public function register(): void {
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		// Block-theme-safe template override: swap in our author-lagoon block
		// template directly via the template canvas globals.
		add_filter( 'template_include', array( $this, 'override_template' ), 20 );
		// Issue a 404 when the nicename doesn't correspond to any user.
		add_action( 'parse_query', array( $this, 'enforce_author_exists' ) );
		add_filter( 'author_link', array( $this, 'filter_author_link' ), 10, 3 );
		add_filter( 'get_the_archive_title', array( $this, 'filter_archive_title' ) );
	}

	/**
	 * Replace the default archive title on our custom author archive. WP has
	 * no idea about this route so `query-title` falls back to the CPT label
	 * ("Archives: Lagoons"). We emit "Lagoons by @username" instead.
	 *
	 * @param string $title
	 */
	public function filter_archive_title( string $title ): string {
		$nicename = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $nicename ) {
			return $title;
		}
		$user = get_user_by( 'slug', $nicename );
		if ( ! $user instanceof WP_User ) {
			return $title;
		}
		$label = '' !== $user->display_name ? $user->display_name : $user->user_login;
		return sprintf(
			/* translators: %s: author display name. */
			esc_html__( 'Lagoons by @%s', 'codelag-features' ),
			$label
		);
	}

	public function register_rewrite(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&/]+)' );

		add_rewrite_rule(
			'^' . self::URL_PREFIX . '/([^/]+)/page/([0-9]+)/?$',
			'index.php?post_type=' . LagoonPostType::POST_TYPE . '&' . self::QUERY_VAR . '=$matches[1]&paged=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^' . self::URL_PREFIX . '/([^/]+)/?$',
			'index.php?post_type=' . LagoonPostType::POST_TYPE . '&' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param array<int,string> $vars
	 * @return array<int,string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Swap the selected template for our `author-lagoon` block template when
	 * the author-archive query var is present. Mirrors the approach used by
	 * {@see TermIndexRewrite::override_template()}: override the two template
	 * canvas globals directly and return the generic canvas file.
	 *
	 * @param string $template Full path of the default selected template.
	 * @return string
	 */
	public function override_template( string $template ): string {
		if ( '' === (string) get_query_var( self::QUERY_VAR ) ) {
			return $template;
		}

		$theme     = get_stylesheet();
		$block_tpl = get_block_template( $theme . '//author-lagoon', 'wp_template' );
		if ( null === $block_tpl || empty( $block_tpl->content ) ) {
			return $template;
		}

		global $_wp_current_template_id, $_wp_current_template_content;
		$_wp_current_template_id      = $block_tpl->id;
		$_wp_current_template_content = $block_tpl->content;

		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * Translate the nicename query var into an actual user lookup and mark
	 * the request as 404 when there's no matching user. Runs on parse_query
	 * so WP still treats the missing-user case as a canonical 404 (correct
	 * status code + 404 template), per the user's decision.
	 *
	 * @param \WP_Query $query
	 */
	public function enforce_author_exists( $query ): void {
		if ( ! $query->is_main_query() ) {
			return;
		}
		$nicename = (string) $query->get( self::QUERY_VAR );
		if ( '' === $nicename ) {
			return;
		}
		$user = get_user_by( 'slug', $nicename );
		if ( ! $user instanceof WP_User ) {
			$query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Re-route WP's default author URL onto our custom slug. Falls back to the
	 * original URL whenever we can't resolve a nicename (cap loss, deleted
	 * user, etc.) so we never emit a broken link.
	 *
	 * @param string $link    Default author posts URL.
	 * @param int    $user_id Author user id.
	 * @param string $nicename Author nicename (already URL-safe).
	 */
	public function filter_author_link( string $link, int $user_id, string $nicename ): string {
		$slug = $nicename;
		if ( '' === $slug ) {
			$user = $user_id > 0 ? get_userdata( $user_id ) : false;
			if ( $user instanceof WP_User ) {
				$slug = (string) $user->user_nicename;
			}
		}
		if ( '' === $slug ) {
			return $link;
		}
		return home_url( '/' . self::URL_PREFIX . '/' . rawurlencode( $slug ) . '/' );
	}
}
