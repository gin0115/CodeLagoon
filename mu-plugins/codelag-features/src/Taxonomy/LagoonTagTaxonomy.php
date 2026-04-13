<?php
/**
 * Registers the `lagoon_tag` custom taxonomy.
 *
 * @package Gin0115\Codelagoon\Features\Taxonomy
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Taxonomy;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Non-hierarchical tag taxonomy for lagoons.
 *
 * Separate from core `post_tag` so that snippet tags live in their own namespace
 * and can have their own archive at /snippets/tag/<slug>/ without polluting
 * regular post tags.
 */
final class LagoonTagTaxonomy {

	public const TAXONOMY = 'lagoon_tag';

	/**
	 * Archive URL base, e.g. /snippets/tag/bash/.
	 */
	public const REWRITE_SLUG = 'snippets/tag';

	/**
	 * Hook the taxonomy registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the `lagoon_tag` taxonomy against the lagoon CPT.
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Lagoon Tags', 'Taxonomy General Name', 'codelag-features' ),
			'singular_name'              => _x( 'Lagoon Tag', 'Taxonomy Singular Name', 'codelag-features' ),
			'menu_name'                  => __( 'Tags', 'codelag-features' ),
			'all_items'                  => __( 'All Tags', 'codelag-features' ),
			'new_item_name'              => __( 'New Tag Name', 'codelag-features' ),
			'add_new_item'               => __( 'Add New Tag', 'codelag-features' ),
			'edit_item'                  => __( 'Edit Tag', 'codelag-features' ),
			'update_item'                => __( 'Update Tag', 'codelag-features' ),
			'view_item'                  => __( 'View Tag', 'codelag-features' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'codelag-features' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'codelag-features' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'codelag-features' ),
			'popular_items'              => __( 'Popular Tags', 'codelag-features' ),
			'search_items'               => __( 'Search Tags', 'codelag-features' ),
			'not_found'                  => __( 'No tags found.', 'codelag-features' ),
			'no_terms'                   => __( 'No tags', 'codelag-features' ),
			'items_list'                 => __( 'Tags list', 'codelag-features' ),
			'items_list_navigation'      => __( 'Tags list navigation', 'codelag-features' ),
			'back_to_items'              => __( '&larr; Back to Tags', 'codelag-features' ),
		);

		$args = array(
			'labels'             => $labels,
			'hierarchical'       => false,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'show_in_rest'       => true,
			'rest_base'          => 'lagoon-tags',
			'rewrite'            => array(
				'slug'         => self::REWRITE_SLUG,
				'with_front'   => false,
				'hierarchical' => false,
			),
		);

		register_taxonomy( self::TAXONOMY, array( LagoonPostType::POST_TYPE ), $args );
	}
}
