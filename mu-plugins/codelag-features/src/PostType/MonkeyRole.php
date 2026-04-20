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

/**
 * Registers and policies the `monkey` user role — see file docblock above.
 */
final class MonkeyRole {

	public const ROLE = 'monkey';

	/**
	 * Capabilities a monkey gets. Uses lagoon-specific caps so monkeys can
	 * manage their own lagoons without gaining access to regular posts.
	 *
	 * Comment caps are deliberately absent here — comment moderation is
	 * handled via filters that scope access to the monkey's own lagoons
	 * and their own comments.
	 *
	 * @return array<string,bool>
	 */
	private function capabilities(): array {
		return array(
			'read'                     => true,

			// Lagoon caps (custom capability_type = 'lagoon').
			'edit_lagoons'             => true,
			'edit_published_lagoons'   => true,
			'publish_lagoons'          => true,
			'delete_lagoons'           => true,
			'delete_published_lagoons' => true,

			'upload_files'             => true,

			// Comment moderation on own lagoons only (enforced by filters).
			'edit_comment'             => true,
			'moderate_comments'        => true,
		);
	}

	/**
	 * Hook role registration + admin-list scoping into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_role' ) );
		add_action( 'pre_get_posts', array( $this, 'restrict_admin_list' ) );
		add_filter( 'map_meta_cap', array( $this, 'restrict_comment_caps' ), 10, 4 );
		add_action( 'pre_get_comments', array( $this, 'restrict_comment_list' ) );
		add_filter( 'user_has_cap', array( $this, 'grant_edit_posts_for_monkey' ), 10, 4 );
		add_action( 'admin_menu', array( $this, 'remove_posts_menu_for_monkey' ) );
		add_filter( 'wp_count_posts', array( $this, 'filter_lagoon_counts_for_monkey' ), 10, 3 );
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
	 * Dynamically grant `edit_posts` to monkeys in wp-admin.
	 *
	 * WordPress core requires `edit_posts` for the Comments menu and the
	 * edit-comments.php screen. We grant it dynamically so those work, then
	 * remove the Posts menu via `remove_posts_menu_for_monkey()` so monkeys
	 * still cannot access regular posts.
	 *
	 * @param array<string,bool> $allcaps All capabilities for the user.
	 * @param string[]           $caps    Required primitive caps.
	 * @param array              $args    Arguments (cap name, user ID, …).
	 * @param \WP_User           $user    The user object.
	 * @return array<string,bool>
	 */
	public function grant_edit_posts_for_monkey( array $allcaps, array $caps, array $args, $user ): array {
		if ( ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return $allcaps;
		}

		if ( ! is_admin() ) {
			return $allcaps;
		}

		$allcaps['edit_posts'] = true;

		return $allcaps;
	}

	/**
	 * Remove menu items monkeys should not access.
	 *
	 * Because we dynamically grant `edit_posts` (needed for comments),
	 * WordPress will show the Posts menu. Media is also hidden — monkeys
	 * can still upload files via the lagoon editor but don't need the
	 * standalone media library.
	 */
	public function remove_posts_menu_for_monkey(): void {
		$user = wp_get_current_user();
		if ( ! $user->exists() || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return;
		}

		remove_menu_page( 'edit.php' );
		remove_menu_page( 'upload.php' );
	}

	/**
	 * Override `wp_count_posts` for the lagoon post type so monkeys only
	 * see counts for their own lagoons. Also removes the "Mine" view link
	 * since all lagoons shown are theirs.
	 *
	 * @param object $counts  Post count object keyed by status.
	 * @param string $type    Post type.
	 * @param string $perm    Permission filter ('readable' or empty).
	 * @return object
	 */
	public function filter_lagoon_counts_for_monkey( $counts, string $type, string $perm ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- required by wp_count_posts filter signature.
		if ( LagoonPostType::POST_TYPE !== $type ) {
			return $counts;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return $counts;
		}

		global $wpdb;

		// Count only this monkey's lagoons per status.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) AS num_posts
				FROM {$wpdb->posts}
				WHERE post_type = %s AND post_author = %d
				GROUP BY post_status",
				LagoonPostType::POST_TYPE,
				$user->ID
			)
		);

		// Zero out all statuses then fill from query.
		foreach ( get_post_stati() as $status ) {
			$counts->$status = 0;
		}
		if ( $results ) {
			foreach ( $results as $row ) {
				$counts->{$row->post_status} = (int) $row->num_posts;
			}
		}

		// Remove the "Mine" view link — everything shown is theirs.
		add_filter(
			'views_edit-' . LagoonPostType::POST_TYPE,
			function ( array $views ): array {
				unset( $views['mine'] );
				return $views;
			}
		);

		return $counts;
	}

	/**
	 * On the wp-admin Lagoons list, restrict monkeys to only their own
	 * lagoons (any status). Admins / editors (anyone with
	 * `edit_others_lagoons`) see everything.
	 *
	 * @param WP_Query $query Admin-list main query.
	 */
	public function restrict_admin_list( $query ): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! $query->is_main_query() ) {
			return;
		}
		if ( current_user_can( 'edit_others_lagoons' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- custom cap from capability_type 'lagoon'.
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || 'edit-' . LagoonPostType::POST_TYPE !== $screen->id ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return;
		}

		$query->set( 'author', $user_id );
	}

	/**
	 * Restrict comment editing/moderation for monkeys so they can only touch:
	 *   - comments on their own lagoons, OR
	 *   - their own comments (on any lagoon).
	 *
	 * Uses `map_meta_cap` to convert the requested meta-cap into
	 * `do_not_allow` when neither condition is met.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Meta capability being checked.
	 * @param int      $user_id User being checked.
	 * @param mixed[]  $args    Additional args — $args[0] is the comment ID.
	 * @return string[]
	 */
	public function restrict_comment_caps( array $caps, string $cap, int $user_id, array $args ): array {
		$comment_caps = array( 'edit_comment', 'delete_comment', 'moderate_comments' );
		if ( ! in_array( $cap, $comment_caps, true ) ) {
			return $caps;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return $caps;
		}

		// moderate_comments is a general cap without a specific comment ID.
		// Allow it through — the comment list filter handles scoping.
		if ( 'moderate_comments' === $cap ) {
			return $caps;
		}

		$comment_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( 0 === $comment_id ) {
			return array( 'do_not_allow' );
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof \WP_Comment ) {
			return array( 'do_not_allow' );
		}

		// Allow if this is the monkey's own comment.
		if ( (int) $comment->user_id === $user_id ) {
			return $caps;
		}

		// Allow if the comment is on one of the monkey's own lagoons.
		$post = get_post( $comment->comment_post_ID );
		if (
			$post instanceof \WP_Post
			&& LagoonPostType::POST_TYPE === $post->post_type
			&& (int) $post->post_author === $user_id
		) {
			return $caps;
		}

		return array( 'do_not_allow' );
	}

	/**
	 * On the wp-admin Comments list, narrow results for monkeys to only show
	 * comments on their own lagoons plus their own comments on any lagoon.
	 *
	 * @param \WP_Comment_Query $query Comment query being executed.
	 */
	public function restrict_comment_list( $query ): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( current_user_can( 'edit_others_lagoons' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- custom cap from capability_type 'lagoon'.
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return;
		}

		$user_id = $user->ID;

		// Unhook ourselves to prevent infinite recursion — get_comments()
		// triggers pre_get_comments which would call this method again.
		remove_action( 'pre_get_comments', array( $this, 'restrict_comment_list' ) );

		// Gather IDs of lagoons this monkey owns.
		$own_lagoon_ids = get_posts(
			array(
				'post_type'      => LagoonPostType::POST_TYPE,
				'author'         => $user_id,
				'post_status'    => 'any',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- deliberate ceiling.
				'fields'         => 'ids',
			)
		);

		// Gather IDs of the monkey's own comments on lagoons.
		$own_comment_ids = get_comments(
			array(
				'user_id'   => $user_id,
				'post_type' => LagoonPostType::POST_TYPE,
				'fields'    => 'ids',
				'number'    => 500,
			)
		);

		// Build a list of comment IDs the monkey may see: all comments on
		// their own lagoons + their own comments elsewhere.
		$allowed_ids = array();
		if ( array() !== $own_lagoon_ids ) {
			$lagoon_comments = get_comments(
				array(
					'post__in' => $own_lagoon_ids,
					'fields'   => 'ids',
					'number'   => 2000,
				)
			);
			$allowed_ids     = array_map( 'intval', $lagoon_comments );
		}

		$allowed_ids = array_values(
			array_unique(
				array_merge( $allowed_ids, array_map( 'intval', $own_comment_ids ) )
			)
		);

		// Re-hook now that our internal queries are done.
		add_action( 'pre_get_comments', array( $this, 'restrict_comment_list' ) );

		if ( array() === $allowed_ids ) {
			$allowed_ids = array( 0 );
		}

		$query->query_vars['comment__in'] = $allowed_ids;
	}
}
