<?php
/**
 * Registers the `lagoon` custom post type.
 *
 * @package Gin0115\Codelagoon\Features\PostType
 */

declare(strict_types=1);

namespace Gin0115\Codelagoon\Features\PostType;

defined( 'ABSPATH' ) || exit;

/**
 * The `lagoon` CPT — one post per code snippet ("lagoon").
 *
 * Files belonging to a lagoon are stored in a dedicated custom table
 * (see Phase 2). The post itself owns title, author, comments, revisions,
 * and visibility/fork metadata. A locked block template containing a
 * single `codelag/lagoon-viewer` block is attached in Phase 4 once the
 * block is built; for now the post uses the default editor shell so the
 * CPT is fully testable on its own.
 */
final class LagoonPostType {

	public const POST_TYPE = 'lagoon';

	/**
	 * Archive and singular URL base, e.g. /snippets/my-lagoon/.
	 */
	public const REWRITE_SLUG = 'snippets';

	/**
	 * Hook the CPT registration into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the `lagoon` post type.
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Lagoons', 'Post Type General Name', 'codelag-features' ),
			'singular_name'         => _x( 'Lagoon', 'Post Type Singular Name', 'codelag-features' ),
			'menu_name'             => _x( 'Lagoons', 'Admin Menu text', 'codelag-features' ),
			'name_admin_bar'        => _x( 'Lagoon', 'Add New on Toolbar', 'codelag-features' ),
			'archives'              => __( 'Lagoon Archives', 'codelag-features' ),
			'attributes'            => __( 'Lagoon Attributes', 'codelag-features' ),
			'parent_item_colon'     => __( 'Parent Lagoon:', 'codelag-features' ),
			'all_items'             => __( 'All Lagoons', 'codelag-features' ),
			'add_new_item'          => __( 'Add New Lagoon', 'codelag-features' ),
			'add_new'               => __( 'Add New', 'codelag-features' ),
			'new_item'              => __( 'New Lagoon', 'codelag-features' ),
			'edit_item'             => __( 'Edit Lagoon', 'codelag-features' ),
			'update_item'           => __( 'Update Lagoon', 'codelag-features' ),
			'view_item'             => __( 'View Lagoon', 'codelag-features' ),
			'view_items'            => __( 'View Lagoons', 'codelag-features' ),
			'search_items'          => __( 'Search Lagoons', 'codelag-features' ),
			'not_found'             => __( 'Not found', 'codelag-features' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'codelag-features' ),
			'featured_image'        => __( 'Cover Image', 'codelag-features' ),
			'set_featured_image'    => __( 'Set cover image', 'codelag-features' ),
			'remove_featured_image' => __( 'Remove cover image', 'codelag-features' ),
			'use_featured_image'    => __( 'Use as cover image', 'codelag-features' ),
			'insert_into_item'      => __( 'Insert into lagoon', 'codelag-features' ),
			'uploaded_to_this_item' => __( 'Uploaded to this lagoon', 'codelag-features' ),
			'items_list'            => __( 'Lagoons list', 'codelag-features' ),
			'items_list_navigation' => __( 'Lagoons list navigation', 'codelag-features' ),
			'filter_items_list'     => __( 'Filter lagoons list', 'codelag-features' ),
		);

		$args = array(
			'label'               => __( 'Lagoon', 'codelag-features' ),
			'description'         => __( 'A shared code snippet (lagoon) containing one or more files.', 'codelag-features' ),
			'labels'              => $labels,
			'supports'            => array(
				'title',
				'editor',
				'author',
				'comments',
				'revisions',
				'custom-fields',
			),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-editor-code',
			'can_export'          => true,
			'has_archive'         => self::REWRITE_SLUG,
			'rewrite'             => array(
				'slug'       => self::REWRITE_SLUG,
				'with_front' => false,
			),
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'lagoons',
			'template'            => array(
				array( 'codelag/lagoon-prose' ),
				array(
					'codelag/lagoon-viewer',
					array( 'align' => 'full' ),
				),
				array( 'codelag/lagoon-prose' ),
			),
			'template_lock'       => 'all',
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
