<?php
/**
 * Server render for the `codelag/lagoon-viewer` block.
 *
 * Stacked file cards. For each file:
 *  - Header (filename + language label + copy button)
 *  - Optional description
 *  - Body:
 *      - Markdown files: rendered HTML inside `.markdown-body` (GitHub-styled),
 *        with a hidden raw `<pre>` for the toggle to reveal
 *      - All other files: `<pre><code class="language-X">` for Prism to highlight
 *
 * @var array<string,mixed> $attributes
 * @var string              $content
 * @var WP_Block            $block
 *
 * @package Gin0115\Codelagoon\Blocks
 */

use Gin0115\Codelagoon\Features\Database\FileRepository;
use Gin0115\Codelagoon\Features\Markdown\Renderer as MarkdownRenderer;

defined( 'ABSPATH' ) || exit;

// Wrap the whole template in a closure so all locals stay scoped to the render
// call. Avoids leaking $post_id / $files / $file etc. into the global symbol
// table and keeps WPCS PrefixAllGlobals happy without uglifying every name.
( static function ( array $attributes, string $content, WP_Block $block ): void {
	unset( $attributes, $content );

	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
	if ( 0 === $post_id ) {
		$post_id = (int) get_the_ID();
	}

	if ( 0 === $post_id || ! class_exists( FileRepository::class ) ) {
		return;
	}

	$files = ( new FileRepository() )->list_for_lagoon( $post_id );

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class'             => 'lagoon-viewer',
			'data-lagoon-id'    => (string) $post_id,
			'data-lagoon-files' => (string) count( $files ),
		)
	);

	/**
	 * Map our internal language slug to a Prism language identifier so the
	 * `<code class="language-x">` element matches whatever the Prism build
	 * provides.
	 *
	 * @param string $slug Internal language slug.
	 * @return string Prism language id.
	 */
	$to_prism_lang = static function ( string $slug ): string {
		$map = array(
			'plaintext'  => 'none',
			'php'        => 'php',
			'js'         => 'javascript',
			'javascript' => 'javascript',
			'ts'         => 'typescript',
			'typescript' => 'typescript',
			'html'       => 'markup',
			'xml'        => 'markup',
			'css'        => 'css',
			'scss'       => 'scss',
			'sass'       => 'scss',
			'json'       => 'json',
			'yaml'       => 'yaml',
			'yml'        => 'yaml',
			'markdown'   => 'markdown',
			'md'         => 'markdown',
			'bash'       => 'bash',
			'sh'         => 'bash',
			'shell'      => 'bash',
			'sql'        => 'sql',
			'python'     => 'python',
			'py'         => 'python',
			'go'         => 'go',
			'rust'       => 'rust',
			'rs'         => 'rust',
			'diff'       => 'diff',
		);
		$key = strtolower( $slug );
		return isset( $map[ $key ] ) ? $map[ $key ] : 'none';
	};
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( array() === $files ) : ?>
			<p class="lagoon-viewer__empty"><?php esc_html_e( 'No files in this lagoon yet.', 'codelag-blocks' ); ?></p>
		<?php else : ?>
			<?php
			foreach ( $files as $file ) :
				$lang        = strtolower( (string) $file['language'] );
				$prism_lang  = $to_prism_lang( $lang );
				$is_markdown = ( 'markdown' === $lang || 'md' === $lang );
				$file_body   = (string) $file['content'];
				$file_desc   = (string) $file['description'];
				?>
				<article class="lagoon-file" data-file-id="<?php echo esc_attr( (string) $file['id'] ); ?>">
					<header class="lagoon-file__header">
						<span class="lagoon-file__name"><?php echo esc_html( (string) $file['name'] ); ?></span>
						<span class="lagoon-file__lang"><?php echo esc_html( $lang ); ?></span>
						<?php if ( $is_markdown ) : ?>
							<button
								type="button"
								class="lagoon-file__action lagoon-file__toggle-md"
								aria-label="<?php esc_attr_e( 'Toggle markdown source', 'codelag-blocks' ); ?>"
								data-codelag-md-toggle
							>
								<span data-codelag-md-toggle-label-preview><?php esc_html_e( 'Source', 'codelag-blocks' ); ?></span>
								<span data-codelag-md-toggle-label-source hidden><?php esc_html_e( 'Preview', 'codelag-blocks' ); ?></span>
							</button>
						<?php endif; ?>
						<button
							type="button"
							class="lagoon-file__action lagoon-file__copy"
							aria-label="<?php esc_attr_e( 'Copy file contents', 'codelag-blocks' ); ?>"
							data-codelag-copy
						>
							<span data-codelag-copy-default><?php esc_html_e( 'Copy', 'codelag-blocks' ); ?></span>
							<span data-codelag-copy-success hidden><?php esc_html_e( 'Copied!', 'codelag-blocks' ); ?></span>
						</button>
					</header>

					<?php if ( '' !== trim( $file_desc ) ) : ?>
						<p class="lagoon-file__description">
							<?php echo esc_html( $file_desc ); ?>
						</p>
					<?php endif; ?>

					<?php if ( $is_markdown ) : ?>
						<div class="lagoon-file__body lagoon-file__body--markdown" data-codelag-md-body>
							<div class="lagoon-file__md markdown-body" data-codelag-md-preview>
								<?php
								// MarkdownRenderer::render returns sanitised HTML via
								// league/commonmark with `html_input => escape` and
								// `allow_unsafe_links => false`.
								echo class_exists( MarkdownRenderer::class )
									? MarkdownRenderer::render( $file_body ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									: '<pre>' . esc_html( $file_body ) . '</pre>';
								?>
							</div>
							<pre class="lagoon-file__code lagoon-file__code--md-source language-markdown" hidden data-codelag-md-source><code class="language-markdown"><?php echo esc_html( $file_body ); ?></code></pre>
						</div>
					<?php else : ?>
						<pre class="lagoon-file__code language-<?php echo esc_attr( $prism_lang ); ?>"><code class="language-<?php echo esc_attr( $prism_lang ); ?>" data-codelag-code><?php echo esc_html( $file_body ); ?></code></pre>
					<?php endif; ?>

					<textarea class="lagoon-file__raw" data-codelag-raw aria-hidden="true" hidden><?php echo esc_textarea( $file_body ); ?></textarea>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
} )( $attributes, $content, $block );
