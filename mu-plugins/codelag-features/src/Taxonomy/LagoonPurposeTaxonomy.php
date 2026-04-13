<?php
/**
 * Registers the `lagoon_purpose` flat taxonomy.
 *
 * @package Gin0115\Codelagoon\Features\Taxonomy
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\Taxonomy;

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Non-hierarchical taxonomy for what a lagoon is FOR.
 *
 * Separate from tags so authors can describe a snippet's purpose (installer,
 * migration, utility, example, ...) independently of free-form tags. Seeded
 * with a default vocabulary by {@see DefaultTermsSeeder}.
 */
final class LagoonPurposeTaxonomy {

	public const TAXONOMY = 'lagoon_purpose';

	/**
	 * Archive URL base, e.g. /snippets/purpose/installer/.
	 */
	public const REWRITE_SLUG = 'snippets/purpose';

	/**
	 * Hook the taxonomy registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the `lagoon_purpose` taxonomy against the lagoon CPT.
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Purposes', 'Taxonomy General Name', 'codelag-features' ),
			'singular_name'              => _x( 'Purpose', 'Taxonomy Singular Name', 'codelag-features' ),
			'menu_name'                  => __( 'Purposes', 'codelag-features' ),
			'all_items'                  => __( 'All Purposes', 'codelag-features' ),
			'new_item_name'              => __( 'New Purpose Name', 'codelag-features' ),
			'add_new_item'               => __( 'Add New Purpose', 'codelag-features' ),
			'edit_item'                  => __( 'Edit Purpose', 'codelag-features' ),
			'update_item'                => __( 'Update Purpose', 'codelag-features' ),
			'view_item'                  => __( 'View Purpose', 'codelag-features' ),
			'separate_items_with_commas' => __( 'Separate purposes with commas', 'codelag-features' ),
			'add_or_remove_items'        => __( 'Add or remove purposes', 'codelag-features' ),
			'choose_from_most_used'      => __( 'Choose from the most used purposes', 'codelag-features' ),
			'popular_items'              => __( 'Popular Purposes', 'codelag-features' ),
			'search_items'               => __( 'Search Purposes', 'codelag-features' ),
			'not_found'                  => __( 'No purposes found.', 'codelag-features' ),
			'no_terms'                   => __( 'No purposes', 'codelag-features' ),
			'items_list'                 => __( 'Purposes list', 'codelag-features' ),
			'items_list_navigation'      => __( 'Purposes list navigation', 'codelag-features' ),
			'back_to_items'              => __( '&larr; Back to Purposes', 'codelag-features' ),
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
			'rest_base'          => 'lagoon-purposes',
			'rewrite'            => array(
				'slug'         => self::REWRITE_SLUG,
				'with_front'   => false,
				'hierarchical' => false,
			),
		);

		register_taxonomy( self::TAXONOMY, array( LagoonPostType::POST_TYPE ), $args );
	}
}
