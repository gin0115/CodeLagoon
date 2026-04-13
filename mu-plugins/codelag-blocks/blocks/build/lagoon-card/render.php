<?php
/**
 * Server render for the `codelag/lagoon-card` block.
 *
 * Renders a single lagoon summary card inside a Query Loop post-template.
 * The enclosing Query Loop passes `postId` via block context; we fall back
 * to the current global post when the block is rendered outside a loop
 * (e.g. a designer dropping it into a fixed template for mockups).
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 *
 * @package Gin0115\Codelagoon\Blocks
 */

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;

defined( 'ABSPATH' ) || exit;

( static function ( array $attributes, string $content, WP_Block $block ): void {
	unset( $content );

	$post_id = 0;
	if ( isset( $block->context['postId'] ) && (int) $block->context['postId'] > 0 ) {
		$post_id = (int) $block->context['postId'];
	} elseif ( get_the_ID() ) {
		$post_id = (int) get_the_ID();
	}

	if ( $post_id <= 0 || ! class_exists( FileRepository::class ) ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || LagoonPostType::POST_TYPE !== $post->post_type ) {
		return;
	}

	$preview_lines = isset( $attributes['previewLines'] ) ? max( 1, (int) $attributes['previewLines'] ) : 6;
	$max_chips     = isset( $attributes['maxTagChips'] ) ? max( 0, (int) $attributes['maxTagChips'] ) : 4;
	$show_fork     = ! isset( $attributes['showFork'] ) || (bool) $attributes['showFork'];
	$show_view     = ! isset( $attributes['showView'] ) || (bool) $attributes['showView'];

	$author_id      = (int) $post->post_author;
	$author         = $author_id > 0 ? get_userdata( $author_id ) : null;
	$author_name    = $author instanceof WP_User ? (string) $author->user_login : '';
	$author_display = $author instanceof WP_User ? (string) $author->display_name : '';
	$avatar_url     = $author_id > 0 ? (string) get_avatar_url( $author_id, array( 'size' => 40 ) ) : '';

	$languages = get_the_terms( $post_id, LagoonLanguageTaxonomy::TAXONOMY );
	$first_lang = is_array( $languages ) && array() !== $languages ? $languages[0] : null;

	$tags = get_the_terms( $post_id, LagoonTagTaxonomy::TAXONOMY );
	if ( ! is_array( $tags ) ) {
		$tags = array();
	}
	if ( $max_chips > 0 ) {
		$tags = array_slice( $tags, 0, $max_chips );
	} else {
		$tags = array();
	}

	$files   = ( new FileRepository() )->list_for_lagoon( $post_id );
	$preview = '';
	if ( array() !== $files ) {
		$first  = $files[0];
		$lines  = preg_split( "/\r\n|\r|\n/", (string) $first['content'] ) ?: array();
		$slice  = array_slice( $lines, 0, $preview_lines );
		$preview = implode( "\n", $slice );
	}

	$ago = human_time_diff( (int) get_post_timestamp( $post ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'codelag-blocks' );

	$nonce     = wp_create_nonce( 'wp_rest' );
	$rest_root = esc_url_raw( rest_url() );

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class'           => 'codelag-card',
			'data-lagoon-id'  => (string) $post_id,
		)
	);
	?>
	<article <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<header class="codelag-card__head">
			<?php if ( '' !== $avatar_url ) : ?>
				<img class="codelag-card__avatar" src="<?php echo esc_url( $avatar_url ); ?>" alt="" loading="lazy" width="40" height="40" />
			<?php else : ?>
				<div class="codelag-card__avatar" aria-hidden="true"></div>
			<?php endif; ?>

			<div class="codelag-card__ident">
				<a class="codelag-card__title" href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>">
					<?php
					$title = get_the_title( $post );
					echo esc_html( '' === trim( (string) $title ) ? (string) $post->post_name : $title );
					?>
				</a>
				<p class="codelag-card__meta">
					<?php
					if ( '' !== $author_name ) {
						printf(
							/* translators: 1: author username, 2: author URL, 3: relative time. */
							esc_html__( 'by %1$s · %2$s', 'codelag-blocks' ),
							sprintf(
								'<a class="codelag-card__author" href="%1$s">@%2$s</a>',
								esc_url( (string) get_author_posts_url( $author_id ) ),
								esc_html( $author_name )
							), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- printf args pre-escaped above.
							esc_html( $ago )
						);
					} else {
						echo esc_html( $ago );
					}
					?>
				</p>
			</div>

			<?php if ( $first_lang instanceof WP_Term ) : ?>
				<span class="codelag-chip codelag-chip--lang"><?php echo esc_html( $first_lang->name ); ?></span>
			<?php endif; ?>
		</header>

		<?php if ( '' !== $preview ) : ?>
			<pre class="codelag-card__preview"><code><?php echo esc_html( $preview ); ?></code></pre>
		<?php endif; ?>

		<?php if ( array() !== $tags ) : ?>
			<ul class="codelag-card__tags">
				<?php foreach ( $tags as $tag ) : ?>
					<li class="codelag-chip codelag-chip--tag">#<?php echo esc_html( $tag->name ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<footer class="codelag-card__actions">
			<?php if ( $show_view ) : ?>
				<a class="codelag-card__action codelag-card__action--ghost" href="<?php echo esc_url( (string) get_permalink( $post ) ); ?>">
					<span class="material-symbols-outlined" aria-hidden="true">visibility</span>
					<span><?php esc_html_e( 'View', 'codelag-blocks' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $show_fork && is_user_logged_in() ) : ?>
				<button
					type="button"
					class="codelag-card__action codelag-card__action--primary"
					data-codelag-fork-lagoon
					data-lagoon-id="<?php echo esc_attr( (string) $post_id ); ?>"
					data-fork-nonce="<?php echo esc_attr( $nonce ); ?>"
					data-fork-rest-root="<?php echo esc_attr( $rest_root ); ?>"
				>
					<span class="material-symbols-outlined" aria-hidden="true">fork_right</span>
					<span data-codelag-fork-default><?php esc_html_e( 'Fork', 'codelag-blocks' ); ?></span>
					<span data-codelag-fork-busy hidden><?php esc_html_e( 'Forking…', 'codelag-blocks' ); ?></span>
				</button>
			<?php endif; ?>
		</footer>
	</article>
	<?php
} )( $attributes, $content, $block );
