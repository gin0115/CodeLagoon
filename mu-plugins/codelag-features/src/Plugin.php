<?php
/**
 * Main plugin class for the CodeLagoon Features mu-plugin.
 *
 * @package Gin0115\Codelagoon\Features
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton bootstrap for the features mu-plugin.
 *
 * Responsible for wiring up every service the mu-plugin exposes — CPTs, taxonomies,
 * the custom tables and repositories, REST routes, visibility, and forking. Services
 * will be registered in later phases; for now this is a no-op placeholder that proves
 * the PSR-4 autoloader and bootstrap are working end-to-end.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Guards against double-initialisation.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Retrieve the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use {@see Plugin::instance()}.
	 */
	private function __construct() {}

	/**
	 * Wire the plugin into WordPress.
	 *
	 * Safe to call more than once; subsequent calls are ignored.
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		( new Database\Schema() )->register();
		// Taxonomies register BEFORE the CPT so their permastructs (e.g.
		// `snippets/purpose/<term>`) generate rewrite rules ahead of the CPT's
		// broader `snippets/<slug>` single-post rule. Otherwise WP matches the
		// CPT single rule first and 404s on taxonomy archives.
		( new Taxonomy\LagoonLanguageTaxonomy() )->register();
		( new Taxonomy\LagoonTagTaxonomy() )->register();
		( new Taxonomy\LagoonPurposeTaxonomy() )->register();
		( new PostType\LagoonPostType() )->register();
		( new PostType\SlugGenerator() )->register();
		( new PostType\LinkStatus() )->register();
		( new PostType\MonkeyRole() )->register();
		( new PostType\EditorAssets() )->register();
		( new Taxonomy\DefaultTermsSeeder() )->register();
		( new Meta\LagoonMeta() )->register();
		( new Meta\UserPreferences() )->register();

		$file_repository = new Database\FileRepository();
		( new Database\Denormalisation( $file_repository ) )->register();

		$fork_service = new Fork\ForkService( $file_repository );
		$search_query = new Search\LagoonSearchQuery( $file_repository );

		( new Query\ArchiveQueryFilter( $search_query ) )->register();
		( new Query\TermIndexRewrite() )->register();
		( new Query\AuthorArchiveRewrite() )->register();

		( new Rest\LagoonsController( $file_repository, $search_query ) )->register();
		( new Rest\FilesController( $file_repository ) )->register();
		( new Rest\FileSearchController( $file_repository ) )->register();
		( new Rest\ForkController( $fork_service ) )->register();
		( new Rest\TermsController( Taxonomy\LagoonLanguageTaxonomy::TAXONOMY, 'lagoon-languages' ) )->register();
		( new Rest\TermsController( Taxonomy\LagoonTagTaxonomy::TAXONOMY, 'lagoon-tags' ) )->register();
		( new Rest\TermsController( Taxonomy\LagoonPurposeTaxonomy::TAXONOMY, 'lagoon-purposes' ) )->register();

		( new Shortcode\ForkButtonShortcode() )->register();
		( new Shortcode\ForkLineageShortcode() )->register();
		( new Shortcode\ThemePickerShortcode() )->register();
		( new Shortcode\ShareButtonShortcode() )->register();
		( new Shortcode\DownloadButtonShortcode() )->register();
		( new Shortcode\EditButtonShortcode() )->register();

		( new Download\ZipDownloadHandler( $file_repository ) )->register();

		( new Comments\CommentsEnhancer() )->register();

		// Future phases will register services here:
		// - Taxonomy auto-sync: populate lagoon_language from files.language on save (manual button, Phase 5)
		// - Rest\ForkController / VisibilityController
		// - Visibility\VisibilityManager
		// - Fork\ForkService
	}
}
