<?php
/**
 * Registers the `lagoon_language` hierarchical taxonomy.
 *
 * @package Gin0115\Codelagoon\Features\Taxonomy
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Taxonomy;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Hierarchical taxonomy for the programming languages used by a lagoon.
 *
 * Hierarchical so authors can add child terms under a parent (e.g. `php8.4+`
 * as a child of `php`). Querying by the parent term still catches everything
 * underneath thanks to WordPress's default `include_children => true`, so
 * searches like "all PHP lagoons" work unchanged.
 *
 * Terms are auto-suggested from the per-file language column on save
 * (wired up in Phase 2 once the files table exists). Authors can still
 * add or remove terms manually.
 */
final class LagoonLanguageTaxonomy {

	public const TAXONOMY = 'lagoon_language';

	/**
	 * Archive URL base, e.g. /snippets/language/php/.
	 */
	public const REWRITE_SLUG = 'snippets/language';

	/**
	 * Hook the taxonomy registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the `lagoon_language` taxonomy against the lagoon CPT.
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Languages', 'Taxonomy General Name', 'codelag-features' ),
			'singular_name'              => _x( 'Language', 'Taxonomy Singular Name', 'codelag-features' ),
			'menu_name'                  => __( 'Languages', 'codelag-features' ),
			'all_items'                  => __( 'All Languages', 'codelag-features' ),
			'parent_item'                => __( 'Parent Language', 'codelag-features' ),
			'parent_item_colon'          => __( 'Parent Language:', 'codelag-features' ),
			'new_item_name'              => __( 'New Language Name', 'codelag-features' ),
			'add_new_item'               => __( 'Add New Language', 'codelag-features' ),
			'edit_item'                  => __( 'Edit Language', 'codelag-features' ),
			'update_item'                => __( 'Update Language', 'codelag-features' ),
			'view_item'                  => __( 'View Language', 'codelag-features' ),
			'separate_items_with_commas' => __( 'Separate languages with commas', 'codelag-features' ),
			'add_or_remove_items'        => __( 'Add or remove languages', 'codelag-features' ),
			'choose_from_most_used'      => __( 'Choose from the most used languages', 'codelag-features' ),
			'popular_items'              => __( 'Popular Languages', 'codelag-features' ),
			'search_items'               => __( 'Search Languages', 'codelag-features' ),
			'not_found'                  => __( 'No languages found.', 'codelag-features' ),
			'no_terms'                   => __( 'No languages', 'codelag-features' ),
			'items_list'                 => __( 'Languages list', 'codelag-features' ),
			'items_list_navigation'      => __( 'Languages list navigation', 'codelag-features' ),
			'back_to_items'              => __( '&larr; Back to Languages', 'codelag-features' ),
		);

		$args = array(
			'labels'             => $labels,
			'hierarchical'       => true,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'show_in_rest'       => true,
			'rest_base'          => 'lagoon-languages',
			'rewrite'            => array(
				'slug'         => self::REWRITE_SLUG,
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_taxonomy( self::TAXONOMY, array( LagoonPostType::POST_TYPE ), $args );
	}
}
