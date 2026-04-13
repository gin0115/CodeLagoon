<?php
/**
 * Custom `monkey` role — basic contributor-like role for lagoon authors.
 *
 * A monkey can create, edit, and publish their own lagoons. In wp-admin list
 * tables they see only their own lagoons plus publicly-listed lagoons from
 * other users (no private, no link-only, no drafts from others).
 *
 * Role is added via `add_role()` once (idempotent). Admin visibility limits
 * are enforced via `pre_get_posts` on the Lagoons list screen so a monkey
 * can't widen their view by manipulating the status filter in the URL.
 *
 * @package Gin0115\Codelagoon\Features\PostType
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\PostType;

use WP_Query;

// LagoonPostType lives in the same namespace; no use-statement required.

defined( 'ABSPATH' ) || exit;

final class MonkeyRole {

	public const ROLE = 'monkey';

	/**
	 * Capabilities a monkey gets. Deliberately minimal — own-posts only, no
	 * dashboard widgets, no other-user editing.
	 *
	 * @return array<string,bool>
	 */
	private function capabilities(): array {
		return array(
			'read'                   => true,
			'edit_posts'             => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_published_posts' => true,
			'upload_files'           => true,
		);
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_role' ) );
		add_action( 'pre_get_posts', array( $this, 'restrict_admin_list' ) );
	}

	/**
	 * Idempotent role registration. If the role already exists we don't
	 * re-create it — that would wipe any custom edits an admin made via a
	 * role editor plugin. A separate migration step handles cap changes.
	 */
	public function register_role(): void {
		if ( null !== get_role( self::ROLE ) ) {
			return;
		}
		add_role(
			self::ROLE,
			_x( 'Monkey', 'user role label', 'codelag-features' ),
			$this->capabilities()
		);
	}

	/**
	 * On the wp-admin Lagoons list, narrow the result set for monkeys to:
	 *   - their own posts (any status), OR
	 *   - publicly-listed posts from other users (post_status=publish).
	 *
	 * Implemented via the `post_status` + author filtering hooks on the
	 * main query. Admins / editors (anyone with `edit_others_posts`) are
	 * untouched so they see everything.
	 *
	 * @param WP_Query $query
	 */
	public function restrict_admin_list( $query ): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! $query->is_main_query() ) {
			return;
		}
		if ( current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . LagoonPostType::POST_TYPE !== $screen->id ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return;
		}

		// Two separate author pools can't be OR'd directly in WP_Query. Instead
		// we pre-compute the allowed post IDs — cheap because we cap the
		// candidate pool at a high but sane limit (admin list pagination
		// already naturally restricts what's displayed).
		$own_ids = get_posts(
			array(
				'post_type'      => LagoonPostType::POST_TYPE,
				'author'         => $user_id,
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		$others_published = get_posts(
			array(
				'post_type'      => LagoonPostType::POST_TYPE,
				'author__not_in' => array( $user_id ),
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		$allowed = array_values( array_unique( array_merge( $own_ids, $others_published ) ) );
		if ( array() === $allowed ) {
			$allowed = array( 0 );
		}
		$query->set( 'post__in', $allowed );
	}
}
