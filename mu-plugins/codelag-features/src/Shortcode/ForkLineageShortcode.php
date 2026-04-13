<?php
/**
 * Renders the fork lineage of the current lagoon.
 *
 * @package Gin0115\Codelagoon\Features\Shortcode
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Shortcode;

use Gin0115\Codelagoon\Features\Meta\LagoonMeta;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode `[codelag_fork_lineage]`.
 *
 * Outputs the fork chain for the current lagoon. Designed to be dropped into
 * the singular template via `wp:shortcode`. Behaviour:
 *
 *  - If the current post isn't a lagoon, or has no `_lagoon_forked_from`
 *    meta, returns an empty string (silent — safe to leave in templates that
 *    render originals as well as forks).
 *  - With one ancestor: renders a single inline line "Forked from <link>".
 *  - With more than one ancestor: renders the inline parent line plus a
 *    `<details>` block listing the full chain (most-recent first).
 *
 * Each ancestor entry is rehydrated from the live post when possible (so the
 * link/title stay current); deleted ancestors fall back to the frozen
 * snapshot stored in `_lagoon_fork_history` so the chain still tells a story.
 */
final class ForkLineageShortcode {

	public const TAG = 'codelag_fork_lineage';

	/**
	 * Hook the shortcode registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Register the shortcode tag with WordPress.
	 */
	public function register_shortcode(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes (unused).
	 * @return string Rendered HTML or empty string when there's nothing to show.
	 */
	public function render( $atts = array() ): string {
		unset( $atts );

		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( null === $post || LagoonPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$forked_from = (int) get_post_meta( $post_id, LagoonMeta::META_FORKED_FROM, true );
		if ( $forked_from <= 0 ) {
			return '';
		}

		$history = get_post_meta( $post_id, LagoonMeta::META_FORK_HISTORY, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Build the immediate-parent line. We hydrate from the live post so
		// the link/title reflect any post-fork edits; if the parent has been
		// deleted we fall back to the frozen snapshot in history[0].
		$parent_html = $this->render_ancestor_inline( $forked_from, $history[0] ?? null );

		$chain_html = '';
		if ( count( $history ) > 1 ) {
			$chain_html = $this->render_chain( $history );
		}

		$fork_glyph = '<svg class="codelag-lineage__icon" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<path d="M5 3.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm0 2.122a2.25 2.25 0 1 0-1.5 0v.878A2.25 2.25 0 0 0 5.75 8.5h1.5v2.128a2.251 2.251 0 1 0 1.5 0V8.5h1.5a2.25 2.25 0 0 0 2.25-2.25v-.878a2.25 2.25 0 1 0-1.5 0v.878a.75.75 0 0 1-.75.75h-4.5A.75.75 0 0 1 5 6.25v-.878ZM8.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm3-8.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"/>'
			. '</svg>';

		return sprintf(
			'<div class="codelag-lineage">'
				. '<p class="codelag-lineage__parent">%1$s %2$s %3$s</p>'
				. '%4$s'
				. '</div>',
			$fork_glyph,
			esc_html__( 'Forked from', 'codelag-features' ),
			$parent_html,
			$chain_html
		);
	}

	/**
	 * Render a single ancestor as an inline link (or plain text fallback).
	 *
	 * @param int                      $ancestor_id Live post ID, may no longer exist.
	 * @param array<string,mixed>|null $snapshot    Frozen history record, used as fallback.
	 * @return string Safe HTML.
	 */
	private function render_ancestor_inline( int $ancestor_id, $snapshot ): string {
		$live = $ancestor_id > 0 ? get_post( $ancestor_id ) : null;
		if ( $live instanceof WP_Post && LagoonPostType::POST_TYPE === $live->post_type ) {
			$label = trim( (string) $live->post_title );
			if ( '' === $label ) {
				$label = (string) $live->post_name;
			}
			if ( '' === $label ) {
				$label = sprintf( '#%d', (int) $live->ID );
			}

			return sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( (string) get_permalink( $live ) ),
				esc_html( $label )
			);
		}

		// Live post is gone — use the frozen snapshot if we have one.
		if ( is_array( $snapshot ) ) {
			$username    = isset( $snapshot['username'] ) ? (string) $snapshot['username'] : '';
			$snapshot_id = isset( $snapshot['id'] ) ? (int) $snapshot['id'] : 0;

			$label = '' !== $username ? $username : ( $snapshot_id > 0 ? sprintf( '#%d', $snapshot_id ) : '' );
			if ( '' === $label ) {
				return esc_html__( 'a deleted lagoon', 'codelag-features' );
			}

			return sprintf(
				'<span class="codelag-lineage__deleted">%s</span>',
				esc_html(
					sprintf(
						/* translators: %s: original author username */
						__( '%s (deleted)', 'codelag-features' ),
						$label
					)
				)
			);
		}

		return esc_html__( 'a deleted lagoon', 'codelag-features' );
	}

	/**
	 * Render the full ancestor chain as a collapsible <details> block.
	 *
	 * @param array<int,array<string,mixed>> $history Frozen history list, most-recent first.
	 * @return string
	 */
	private function render_chain( array $history ): string {
		$items = '';
		foreach ( $history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id   = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
			$line = $this->render_ancestor_inline( $id, $entry );

			$meta_bits = array();
			if ( ! empty( $entry['username'] ) ) {
				$meta_bits[] = '@' . (string) $entry['username'];
			}
			if ( ! empty( $entry['forked_at'] ) ) {
				$meta_bits[] = (string) $entry['forked_at'];
			}
			$meta = '' !== implode( '', $meta_bits )
				? ' <span class="codelag-lineage__meta">· ' . esc_html( implode( ' · ', $meta_bits ) ) . '</span>'
				: '';

			$items .= '<li>' . $line . $meta . '</li>';
		}

		return sprintf(
			'<details class="codelag-lineage__chain"><summary>%1$s</summary><ol class="codelag-lineage__list">%2$s</ol></details>',
			esc_html__( 'View full fork lineage', 'codelag-features' ),
			$items
		);
	}
}
