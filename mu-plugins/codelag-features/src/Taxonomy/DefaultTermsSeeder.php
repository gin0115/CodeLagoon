<?php
/**
 * Seeds default terms into the lagoon taxonomies on first run.
 *
 * @package Gin0115\Codelagoon\Features\Taxonomy
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * One-shot seeder for lagoon taxonomy defaults.
 *
 * Seeds languages, purposes, and a small starter set of tags the first time it
 * runs, then bumps an option version so it does not run again. If you add new
 * default terms later, increment {@see DefaultTermsSeeder::VERSION} and the
 * seeder will insert only the missing ones the next time `init` fires — existing
 * terms are left alone via `wp_insert_term()`'s `term_exists` short-circuit.
 */
final class DefaultTermsSeeder {

	/**
	 * Option name storing the last version this seeder ran at.
	 */
	private const OPTION_NAME = 'codelag_features_default_terms_version';

	/**
	 * Current seed revision. Bump when adding new default terms.
	 */
	private const VERSION = 1;

	/**
	 * Hook the seeder onto init after taxonomies have registered.
	 */
	public function register(): void {
		// Priority 20 so taxonomies (priority 10 on init) are registered first.
		add_action( 'init', array( $this, 'maybe_seed' ), 20 );
	}

	/**
	 * Run the seeder if the stored version is behind the current version.
	 */
	public function maybe_seed(): void {
		$current = (int) get_option( self::OPTION_NAME, 0 );
		if ( $current >= self::VERSION ) {
			return;
		}

		$this->seed_taxonomy(
			LagoonLanguageTaxonomy::TAXONOMY,
			array(
				'php'        => 'PHP',
				'javascript' => 'JavaScript',
				'typescript' => 'TypeScript',
				'sql'        => 'SQL',
				'bash'       => 'Bash',
				'shell'      => 'Shell',
				'python'     => 'Python',
				'go'         => 'Go',
				'rust'       => 'Rust',
				'html'       => 'HTML',
				'css'        => 'CSS',
				'scss'       => 'SCSS',
				'json'       => 'JSON',
				'yaml'       => 'YAML',
				'markdown'   => 'Markdown',
				'diff'       => 'Diff',
			)
		);

		$this->seed_taxonomy(
			LagoonPurposeTaxonomy::TAXONOMY,
			array(
				'installer'     => 'Installer',
				'utility'       => 'Utility',
				'example'       => 'Example',
				'tutorial'      => 'Tutorial',
				'boilerplate'   => 'Boilerplate',
				'fix'           => 'Fix',
				'migration'     => 'Migration',
				'config'        => 'Config',
				'documentation' => 'Documentation',
				'test'          => 'Test',
			)
		);

		$this->seed_taxonomy(
			LagoonTagTaxonomy::TAXONOMY,
			array(
				'wordpress'   => 'WordPress',
				'api'         => 'API',
				'cli'         => 'CLI',
				'cron'        => 'Cron',
				'regex'       => 'Regex',
				'debug'       => 'Debug',
				'performance' => 'Performance',
				'database'    => 'Database',
			)
		);

		update_option( self::OPTION_NAME, self::VERSION, false );
	}

	/**
	 * Insert each term into the given taxonomy if it does not already exist.
	 *
	 * @param string               $taxonomy Taxonomy slug.
	 * @param array<string,string> $terms    Map of slug => display name.
	 */
	private function seed_taxonomy( string $taxonomy, array $terms ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		foreach ( $terms as $slug => $name ) {
			if ( null !== term_exists( $slug, $taxonomy ) ) {
				continue;
			}

			wp_insert_term(
				$name,
				$taxonomy,
				array(
					'slug' => $slug,
				)
			);
		}
	}
}
