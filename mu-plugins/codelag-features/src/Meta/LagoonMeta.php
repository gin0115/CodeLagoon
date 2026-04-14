<?php
/**
 * Registers post meta fields for the `lagoon` CPT.
 *
 * @package Gin0115\Codelagoon\Features\Meta
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Meta;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Registers (and exposes through REST) the post meta fields that back visibility
 * and fork lineage for a lagoon.
 *
 * - `_lagoon_visibility`   — enum public|private|link. Drives VisibilityManager in Phase 7.
 * - `_lagoon_link_token`   — unguessable share token. Generated on demand; scrubbed
 *                            from REST responses for non-authors in Phase 7.
 * - `_lagoon_forked_from`  — immediate parent lagoon ID (0 if this lagoon is an original).
 *                            Fast-path lookup; no array walk needed.
 * - `_lagoon_fork_root`    — top-of-chain ancestor ID (0 if this lagoon is an original).
 *                            Fast-path "find the root" without walking the chain.
 * - `_lagoon_fork_history` — Array of ancestor records, most recent first. Each entry is
 *                            `{ id: post_id, user: user_id, username: user_login }`. On fork
 *                            of X into Y, ForkService prepends a record for X to Y's list
 *                            (so Y's entry 0 is X, entry 1 is X's previous ancestor, etc.).
 *                            Snapshot is frozen at fork time so the chain survives deletion
 *                            of intermediate lagoons or users.
 */
final class LagoonMeta {

	public const META_VISIBILITY   = '_lagoon_visibility';
	public const META_LINK_TOKEN   = '_lagoon_link_token';
	public const META_FORKED_FROM  = '_lagoon_forked_from';
	public const META_FORK_ROOT    = '_lagoon_fork_root';
	public const META_FORK_HISTORY = '_lagoon_fork_history';

	public const VISIBILITY_PUBLIC  = 'public';
	public const VISIBILITY_PRIVATE = 'private';
	public const VISIBILITY_LINK    = 'link';

	/**
	 * All valid values for `_lagoon_visibility`.
	 *
	 * @var array<int,string>
	 */
	public const VISIBILITY_VALUES = array(
		self::VISIBILITY_PUBLIC,
		self::VISIBILITY_PRIVATE,
		self::VISIBILITY_LINK,
	);

	/**
	 * Hook meta registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Register every meta key against the `lagoon` CPT. Each meta has its
	 * own private helper below so the individual `register_post_meta()`
	 * config blocks stay readable at ~15 lines each.
	 */
	public function register_meta(): void {
		$this->register_visibility_meta();
		$this->register_link_token_meta();
		$this->register_forked_from_meta();
		$this->register_fork_root_meta();
		$this->register_fork_history_meta();
	}

	/**
	 * Register the `visibility` meta — public / private / link.
	 */
	private function register_visibility_meta(): void {
		register_post_meta(
			LagoonPostType::POST_TYPE,
			self::META_VISIBILITY,
			array(
				'type'              => 'string',
				'description'       => __( 'Visibility state of the lagoon: public, private, or link.', 'codelag-features' ),
				'single'            => true,
				'default'           => self::VISIBILITY_PUBLIC,
				'sanitize_callback' => array( self::class, 'sanitize_visibility' ),
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'enum'    => self::VISIBILITY_VALUES,
						'default' => self::VISIBILITY_PUBLIC,
					),
				),
			)
		);
	}

	/**
	 * Register the `link_token` meta — unguessable share token for
	 * link-only visibility.
	 */
	private function register_link_token_meta(): void {
		register_post_meta(
			LagoonPostType::POST_TYPE,
			self::META_LINK_TOKEN,
			array(
				'type'              => 'string',
				'description'       => __( 'Unguessable share token for link-only visibility.', 'codelag-features' ),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Register the `forked_from` meta — immediate parent's post ID.
	 */
	private function register_forked_from_meta(): void {
		register_post_meta(
			LagoonPostType::POST_TYPE,
			self::META_FORKED_FROM,
			array(
				'type'              => 'integer',
				'description'       => __( 'Post ID of the immediate lagoon this lagoon was forked from, or 0 if original.', 'codelag-features' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Register the `fork_root` meta — top-of-chain ancestor post ID.
	 */
	private function register_fork_root_meta(): void {
		register_post_meta(
			LagoonPostType::POST_TYPE,
			self::META_FORK_ROOT,
			array(
				'type'              => 'integer',
				'description'       => __( 'Post ID of the top-of-chain ancestor lagoon, or 0 if original.', 'codelag-features' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Register the `fork_history` meta — ordered list of ancestor snapshots.
	 */
	private function register_fork_history_meta(): void {
		register_post_meta(
			LagoonPostType::POST_TYPE,
			self::META_FORK_HISTORY,
			array(
				'type'              => 'array',
				'description'       => __( 'Ancestry of this lagoon, most recent first. One entry per ancestor: { id, user, username, forked_at }.', 'codelag-features' ),
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_fork_history' ),
				'auth_callback'     => array( self::class, 'can_edit_post' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'        => array(
									'type'        => 'integer',
									'minimum'     => 0,
									'description' => __( 'Ancestor lagoon post ID.', 'codelag-features' ),
								),
								'user'      => array(
									'type'        => 'integer',
									'minimum'     => 0,
									'description' => __( 'Ancestor author user ID (may be 0 if the user was later deleted).', 'codelag-features' ),
								),
								'username'  => array(
									'type'        => 'string',
									'description' => __( 'Ancestor author user_login captured at fork time.', 'codelag-features' ),
								),
								'forked_at' => array(
									'type'        => 'string',
									'format'      => 'date-time',
									'description' => __( 'Timestamp this ancestor was forked (ISO 8601 / MySQL DATETIME).', 'codelag-features' ),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitize the fork history array. Each entry is coerced into the expected shape;
	 * any unknown fields are dropped.
	 *
	 * @param mixed $value Raw value from REST / direct meta update.
	 *
	 * @return array<int,array{id:int,user:int,username:string,forked_at:string}>
	 */
	public static function sanitize_fork_history( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$clean[] = array(
				'id'        => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
				'user'      => isset( $entry['user'] ) ? (int) $entry['user'] : 0,
				'username'  => isset( $entry['username'] ) ? sanitize_user( (string) $entry['username'], true ) : '',
				'forked_at' => isset( $entry['forked_at'] ) ? sanitize_text_field( (string) $entry['forked_at'] ) : '',
			);
		}

		return $clean;
	}

	/**
	 * Sanitize `_lagoon_visibility` values, falling back to "public" on anything invalid.
	 *
	 * @param mixed $value Raw value from $_POST / REST / direct meta update.
	 *
	 * @return string
	 */
	public static function sanitize_visibility( $value ): string {
		$value = is_string( $value ) ? $value : '';

		return in_array( $value, self::VISIBILITY_VALUES, true ) ? $value : self::VISIBILITY_PUBLIC;
	}

	/**
	 * Auth callback for every lagoon meta field.
	 *
	 * WordPress passes the current-user capability, the meta key, and the object id
	 * when evaluating meta auth. We only need the post id here.
	 *
	 * @param bool   $allowed  Whether the user is currently allowed (unused).
	 * @param string $meta_key Meta key being checked (unused).
	 * @param int    $post_id  Post id the meta is attached to.
	 *
	 * @return bool
	 */
	public static function can_edit_post( bool $allowed, string $meta_key, int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}
}
