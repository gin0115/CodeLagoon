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
		add_filter( 'default_content', array( $this, 'seed_default_content' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'visibility_post_states' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'visibility_icon_styles' ) );
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
			'capability_type'     => 'lagoon',
			'map_meta_cap'        => true,
			'show_in_rest'        => true,
			'rest_base'           => 'lagoons',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Adjust the post-state labels for lagoons:
	 *   - "Listed" for published lagoons (WP shows nothing by default).
	 *   - Keep WP's built-in "Private" and "Draft" labels.
	 *   - "Link only" is already registered via LinkStatus.
	 *
	 * @param string[] $states Current post states.
	 * @param \WP_Post $post   The post.
	 * @return string[]
	 */
	public function visibility_post_states( array $states, \WP_Post $post ): array {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $states;
		}

		if ( 'publish' === $post->post_status ) {
			$states['codelag_listed'] = __( 'Listed', 'codelag-features' );
		}

		return $states;
	}

	/**
	 * Add a visibility dashicon before lagoon titles in the admin list.
	 *
	 * Uses CSS `::before` pseudo-elements on row classes that WordPress
	 * already applies (`.status-publish`, `.status-private`, etc.).
	 */
	public function visibility_icon_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || 'edit-' . self::POST_TYPE !== $screen->id ) {
			return;
		}
		?>
		<style>
			.post-type-lagoon .type-lagoon .row-title::before {
				font-family: dashicons;
				font-size: 16px;
				vertical-align: text-bottom;
				margin-right: 4px;
			}
			.post-type-lagoon .type-lagoon.status-publish .row-title::before {
				content: "\f177"; /* dashicons-visibility */
				color: #1e7e34;
			}
			.post-type-lagoon .type-lagoon.status-<?php echo esc_attr( LinkStatus::STATUS ); ?> .row-title::before {
				content: "\f103"; /* dashicons-admin-links */
				color: #1a56db;
			}
			.post-type-lagoon .type-lagoon.status-private .row-title::before {
				content: "\f530"; /* dashicons-hidden */
				color: #c5221f;
			}
			.post-type-lagoon .type-lagoon.status-draft .row-title::before {
				content: "\f464"; /* dashicons-edit */
				color: #e37400;
			}
		</style>
		<?php
	}

	/**
	 * Seed new lagoon posts with the locked prose / viewer / prose skeleton.
	 *
	 * The `template` + `template_lock` CPT args were removed because the
	 * editor's `doBlocksMatchTemplate` check fails as soon as the user adds
	 * any inner block to a `lagoon-prose` slot (the CPT template has no
	 * inner-blocks template, so WP expects zero children). Instead we bake
	 * per-block `lock` attributes into the initial content so the three
	 * outer blocks cannot be moved or removed, while their inner blocks
	 * remain freely editable.
	 *
	 * @param string   $content Default post content.
	 * @param \WP_Post $post    Draft post being created.
	 */
	public function seed_default_content( string $content, \WP_Post $post ): string {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $content;
		}

		return <<<'HTML'
<!-- wp:codelag/lagoon-prose {"lock":{"move":true,"remove":true}} -->
<div class="wp-block-codelag-lagoon-prose lagoon-prose"></div>
<!-- /wp:codelag/lagoon-prose -->

<!-- wp:codelag/lagoon-viewer {"align":"full","lock":{"move":true,"remove":true}} /-->

<!-- wp:codelag/lagoon-prose {"lock":{"move":true,"remove":true}} -->
<div class="wp-block-codelag-lagoon-prose lagoon-prose"></div>
<!-- /wp:codelag/lagoon-prose -->
HTML;
	}
}
