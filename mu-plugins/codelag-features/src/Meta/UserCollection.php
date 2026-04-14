<?php
/**
 * Per-user collection of saved lagoons.
 *
 * @package Gin0115\Codelagoon\Features\Meta
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the list of lagoon post IDs a user has "collected".
 *
 * Backed by a single user-meta row holding an array of integers. The array
 * is intentionally kept de-duplicated and re-indexed on every write so the
 * stored value stays a plain list (predictable JSON shape if ever exposed).
 */
final class UserCollection {

	public const META_KEY = 'codelag_collected_lagoons';

	/**
	 * Hook meta registration.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Register the user meta. Not exposed in REST — toggling is done via the
	 * dedicated CollectionController which enforces per-lagoon read access.
	 */
	public function register_meta(): void {
		register_meta(
			'user',
			self::META_KEY,
			array(
				'type'              => 'array',
				'description'       => __( 'IDs of lagoons the user has added to their collection.', 'codelag-features' ),
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'auth_callback'     => array( self::class, 'can_edit_own_meta' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Return the collected lagoon IDs for a user, oldest-first.
	 *
	 * @param int $user_id The user whose collection to look up.
	 * @return int[]
	 */
	public static function get_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return self::sanitize( $raw );
	}

	/**
	 * True when the given lagoon is in the user's collection.
	 *
	 * @param int $user_id User to check.
	 * @param int $post_id Lagoon post ID to check for.
	 */
	public static function has( int $user_id, int $post_id ): bool {
		return in_array( $post_id, self::get_ids( $user_id ), true );
	}

	/**
	 * Add a lagoon to the user's collection. No-op if already present.
	 *
	 * @param int $user_id User adding to their collection.
	 * @param int $post_id Lagoon post ID to add.
	 */
	public static function add( int $user_id, int $post_id ): void {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return;
		}
		$ids = self::get_ids( $user_id );
		if ( in_array( $post_id, $ids, true ) ) {
			return;
		}
		$ids[] = $post_id;
		update_user_meta( $user_id, self::META_KEY, $ids );
	}

	/**
	 * Remove a lagoon from the user's collection. No-op if absent.
	 *
	 * @param int $user_id User removing from their collection.
	 * @param int $post_id Lagoon post ID to remove.
	 */
	public static function remove( int $user_id, int $post_id ): void {
		if ( $user_id <= 0 || $post_id <= 0 ) {
			return;
		}
		$ids      = self::get_ids( $user_id );
		$filtered = array_values( array_filter( $ids, static fn( int $candidate ): bool => $candidate !== $post_id ) );
		if ( $filtered === $ids ) {
			return;
		}
		update_user_meta( $user_id, self::META_KEY, $filtered );
	}

	/**
	 * Coerce raw stored value into a list of unique positive ints.
	 *
	 * @param mixed $value Raw meta value.
	 * @return int[]
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array();
		foreach ( $value as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Auth callback — users may only edit their own collection meta;
	 * administrators may edit anyone's.
	 *
	 * @param bool   $allowed  Whether the user is currently allowed (unused).
	 * @param string $meta_key Meta key being checked (unused).
	 * @param int    $user_id  Target user id.
	 */
	public static function can_edit_own_meta( bool $allowed, string $meta_key, int $user_id ): bool {
		unset( $allowed, $meta_key );
		return get_current_user_id() === $user_id || current_user_can( 'edit_users' );
	}
}
