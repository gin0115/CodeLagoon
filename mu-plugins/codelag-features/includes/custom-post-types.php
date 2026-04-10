<?php

defined( 'ABSPATH' ) || exit;

/*
 * Scaffold leftover: dummy "book" CPT from the upstream project scaffold.
 * The `lagoon` CPT registered by Gin0115\Codelagoon\Features\PostType\LagoonPostType
 * replaces this. Kept commented out for reference while the new CPT is being wired up;
 * remove entirely once the new CPT is confirmed working.
 */

/*
function codelag_features_register_book_post_type(): void {
	// Set UI labels for Custom Post Type
	$labels = array(
		'name'               => _x( 'Books', 'Post Type General Name', 'codelag-features' ),
		'singular_name'      => __( 'Book', 'codelag-features' ),
		'menu_name'          => _x( 'Books', 'Admin Menu text', 'codelag-features' ),
		'all_items'          => __( 'All Books', 'codelag-features' ),
		'view_item'          => __( 'View Book', 'codelag-features' ),
		'add_new_item'       => __( 'Add New Book', 'codelag-features' ),
		'add_new'            => __( 'Add New', 'codelag-features' ),
		'edit_item'          => __( 'Edit Book', 'codelag-features' ),
		'update_item'        => __( 'Update Book', 'codelag-features' ),
		'search_items'       => __( 'Search Book', 'codelag-features' ),
		'not_found'          => __( 'Not Found', 'codelag-features' ),
		'not_found_in_trash' => __( 'Not found in Trash', 'codelag-features' ),
	);

	// Set other options for Custom Post Type
	$args = array(
		'label'               => __( 'Books', 'codelag-features' ),
		'description'         => __( 'Books catalogue', 'codelag-features' ),
		'labels'              => $labels,
		'supports'            => array(
			'title',
			'editor',
			'excerpt',
			'author',
			'thumbnail',
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
		'can_export'          => true,
		'has_archive'         => true,
		'rewrite'             => array(
			'slug'       => 'book',
			'with_front' => false,
		),
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'capability_type'     => 'post',
		'show_in_rest'        => true,
		'menu_icon'           => 'dashicons-book',
	);

	register_post_type( 'book', $args );
}
add_action( 'init_foobar', 'codelag_features_register_book_post_type' ); // `_foobar` so it doesn't actually display in production if the function is not removed.

function codelag_features_enqueue_book_post_type_frontend_assets(): void {
	$plugin_slug = codelag_features_get_slug();

	if ( is_post_type_archive( 'book' ) ) {
		$asset_meta = codelag_features_get_asset_meta( 'assets/css/build/book-archive.css' );
		if ( is_array( $asset_meta ) ) {
			wp_enqueue_style(
				"$plugin_slug-book-archive",
				constant( 'CODELAG_FEATURES_DIR_URL' ) . 'assets/css/build/book-archive.css',
				$asset_meta['dependencies'],
				$asset_meta['version']
			);
		}
	}
	if ( is_singular( 'book' ) ) {
		$asset_meta = codelag_features_get_asset_meta( 'assets/css/build/book-singular.css' );
		if ( is_array( $asset_meta ) ) {
			wp_enqueue_style(
				"$plugin_slug-book-singular",
				constant( 'CODELAG_FEATURES_DIR_URL' ) . 'assets/css/build/book-singular.css',
				$asset_meta['dependencies'],
				$asset_meta['version']
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'codelag_features_enqueue_book_post_type_frontend_assets' );
*/
