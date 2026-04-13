<?php
/**
 * Server-side markdown renderer using league/commonmark with the GFM extension.
 *
 * @package Gin0115\Codelagoon\Features\Markdown
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton wrapper around `league/commonmark`. Handles the (relatively) heavy
 * environment setup once and reuses it for every render call. The output is
 * GitHub Flavored Markdown to match how snippets render on github.com:
 * tables, task lists, strikethrough, autolinks, disallowed raw HTML.
 */
final class Renderer {

	/**
	 * Cached MarkdownConverter instance.
	 *
	 * @var MarkdownConverter|null
	 */
	private static ?MarkdownConverter $converter = null;

	/**
	 * Render markdown source to HTML.
	 *
	 * @param string $markdown Raw markdown source.
	 *
	 * @return string Sanitised HTML output.
	 */
	public static function render( string $markdown ): string {
		if ( '' === trim( $markdown ) ) {
			return '';
		}

		$converter = self::converter();
		try {
			return (string) $converter->convert( $markdown );
		} catch ( \Throwable $e ) {
			// On any parser failure fall back to escaped raw — never blank a snippet.
			return '<pre>' . esc_html( $markdown ) . '</pre>';
		}
	}

	/**
	 * Build (or return cached) MarkdownConverter.
	 */
	private static function converter(): MarkdownConverter {
		if ( null !== self::$converter ) {
			return self::$converter;
		}

		$environment = new Environment(
			array(
				'html_input'         => 'escape',
				'allow_unsafe_links' => false,
				'max_nesting_level'  => 50,
			)
		);
		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new GithubFlavoredMarkdownExtension() );

		self::$converter = new MarkdownConverter( $environment );

		return self::$converter;
	}
}
