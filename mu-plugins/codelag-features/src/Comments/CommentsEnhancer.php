<?php
/**
 * Enhances the comment experience on lagoon pages.
 *
 * @package Gin0115\Codelagoon\Features\Comments
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Comments;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Adjusts WordPress comment behaviour for the lagoon CPT:
 *
 *  - Swaps the plain textarea in the comment form for a `wp_editor()`
 *    TinyMCE instance with a trimmed toolbar, so users can write rich
 *    comments (bold, italic, lists, links, blockquotes, code).
 *  - Enqueues the editor assets on lagoon singular pages for logged-in
 *    users so TinyMCE is available when the form renders.
 *  - Closes comments entirely for logged-out visitors on lagoon pages —
 *    the section is also CSS-hidden in the theme as a belt-and-braces
 *    measure, but this short-circuits on the server so bots can't POST.
 *  - Expands the allowed HTML tag list for lagoon comments so the tags
 *    TinyMCE produces (strong, em, ul/ol/li, a, blockquote, code, pre,
 *    br, p) survive wp_filter_kses on save instead of being stripped.
 *  - Cleans up the comment form defaults (labels, notes) so it looks
 *    less bureaucratic.
 *
 * Non-lagoon post types are left completely alone.
 */
final class CommentsEnhancer {

	/**
	 * Hook all comment filters/actions.
	 */
	public function register(): void {
		add_filter( 'comments_open', array( $this, 'restrict_to_logged_in' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'comment_form_field_comment', array( $this, 'replace_with_editor' ) );
		add_filter( 'comment_form_defaults', array( $this, 'clean_form_defaults' ) );
		add_filter( 'preprocess_comment', array( $this, 'allow_tinymce_tags' ) );
	}

	/**
	 * Deny the comment form entirely for logged-out users on lagoon pages.
	 * Returning `false` here makes WordPress treat comments as closed, so
	 * the form isn't rendered and direct POSTs are rejected.
	 *
	 * @param bool $open    Whether comments are currently open.
	 * @param int  $post_id Post being queried.
	 * @return bool
	 */
	public function restrict_to_logged_in( bool $open, int $post_id ): bool {
		if ( LagoonPostType::POST_TYPE !== get_post_type( $post_id ) ) {
			return $open;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return $open;
	}

	/**
	 * Ensure TinyMCE and its dependencies are available on the frontend of
	 * lagoon singular pages for logged-in users. `wp_enqueue_editor()` is a
	 * no-op elsewhere, so scoping by `is_singular` keeps the page weight
	 * consistent.
	 */
	public function enqueue_editor_assets(): void {
		if ( ! $this->should_enhance() ) {
			return;
		}
		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}
	}

	/**
	 * Swap the default plain `<textarea>` in the comment form for a
	 * `wp_editor()` instance configured in "teeny" mode with a trimmed
	 * toolbar that matches the allowed HTML.
	 *
	 * @param string $field Default comment form field markup.
	 * @return string
	 */
	public function replace_with_editor( string $field ): string {
		if ( ! $this->should_enhance() ) {
			return $field;
		}

		ob_start();
		wp_editor(
			'',
			'comment',
			array(
				'media_buttons' => false,
				// Teeny gives us a compact toolbar. Buttons we can include without
				// extra plugin loading: bold, italic, blockquote, bullist, numlist,
				// link, undo, redo. `code` and `unlink` aren't wired in teeny mode
				// so they're dropped.
				'teeny'         => true,
				'textarea_name' => 'comment',
				'textarea_rows' => 6,
				'quicktags'     => array(
					'buttons' => 'strong,em,link,block,del,ul,ol,li,code,close',
				),
				'tinymce'       => array(
					'toolbar1'          => 'bold,italic,bullist,numlist,blockquote,link,undo,redo',
					'toolbar2'          => '',
					'toolbar3'          => '',
					'toolbar4'          => '',
					'wpautop'           => true,
					'remove_linebreaks' => false,
				),
			)
		);
		$editor = (string) ob_get_clean();

		return '<p class="comment-form-comment codelag-comment-form-editor">' . $editor . '</p>';
	}

	/**
	 * Trim the default WordPress comment form chrome: title, notes before /
	 * after, logged-in reply line etc.
	 *
	 * @param array<string,mixed> $defaults Current comment_form defaults.
	 * @return array<string,mixed>
	 */
	public function clean_form_defaults( array $defaults ): array {
		if ( ! $this->should_enhance() ) {
			return $defaults;
		}

		// Strip the "Leave a comment" heading and the "Logged in as… / Required
		// fields are marked *" chrome. The form is self-explanatory — the
		// editor itself plus its submit button are the only chrome we want.
		// `title_reply_to` stays so inline replies still carry a label.
		$defaults['title_reply']        = '';
		$defaults['title_reply_before'] = '';
		$defaults['title_reply_after']  = '';
		/* translators: %s: name of the parent comment's author. */
		$defaults['title_reply_to']       = __( 'Reply to %s', 'codelag-features' );
		$defaults['comment_notes_before'] = '';
		$defaults['comment_notes_after']  = '';
		$defaults['logged_in_as']         = '';
		$defaults['label_submit']         = __( 'Post comment', 'codelag-features' );
		$defaults['class_submit']         = 'codelag-comment-submit';

		return $defaults;
	}

	/**
	 * Expand the kses allow-list so the HTML tags TinyMCE emits survive
	 * comment saving. Without this, WordPress's default comment kses strips
	 * most formatting (kept conservative — no inline styles, no images,
	 * no iframes, nothing that could carry XSS).
	 *
	 * Filter runs on every comment submission; scoped to the lagoon post
	 * type so regular blog comments keep the default behaviour.
	 *
	 * @param array<string,mixed> $commentdata Raw comment data before save.
	 * @return array<string,mixed>
	 */
	public function allow_tinymce_tags( array $commentdata ): array {
		$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
		if ( $post_id <= 0 || LagoonPostType::POST_TYPE !== get_post_type( $post_id ) ) {
			return $commentdata;
		}

		if ( isset( $commentdata['comment_content'] ) && is_string( $commentdata['comment_content'] ) ) {
			$allowed = array(
				'p'          => array(),
				'br'         => array(),
				'strong'     => array(),
				'b'          => array(),
				'em'         => array(),
				'i'          => array(),
				'u'          => array(),
				'del'        => array(),
				's'          => array(),
				'a'          => array(
					'href'   => array(),
					'title'  => array(),
					'rel'    => array(),
					'target' => array(),
				),
				'ul'         => array(),
				'ol'         => array(),
				'li'         => array(),
				'blockquote' => array(
					'cite' => array(),
				),
				'code'       => array(),
				'pre'        => array(),
				'h3'         => array(),
				'h4'         => array(),
			);

			$commentdata['comment_content'] = wp_kses( $commentdata['comment_content'], $allowed );
		}

		return $commentdata;
	}

	/**
	 * Shared gate: are we currently rendering a lagoon singular page for a
	 * logged-in viewer? Most enhancements key off this.
	 */
	private function should_enhance(): bool {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( LagoonPostType::POST_TYPE ) ) {
			return false;
		}
		return is_user_logged_in();
	}
}
