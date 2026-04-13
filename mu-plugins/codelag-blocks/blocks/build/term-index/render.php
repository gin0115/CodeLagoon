<?php
/**
 * Server render for `codelag/term-index`.
 *
 * Emits a flat <ul> of terms — name + count + link — for the given
 * taxonomy. When `taxonomy` attribute is empty we pick the taxonomy from
 * the current term-index page's query var so a single block can be dropped
 * into all three (language / tag / purpose) index templates.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 *
 * @package Gin0115\Codelagoon\Blocks
 */

use Gin0115\Codelagoon\Features\Query\TermIndexRewrite;

defined( 'ABSPATH' ) || exit;

( static function ( array $attributes, string $content, WP_Block $block ): void {
	unset( $content, $block );

	$taxonomy_slug   = isset( $attributes['taxonomy'] ) ? (string) $attributes['taxonomy'] : '';
	$scope_parent_id = null; // null = list all terms of the taxonomy; int = list only children of that term_id.

	// Resolution order when no explicit taxonomy is set:
	// 1) Term-index pages (e.g. /snippets/language/) — list all top-level terms
	//    of the taxonomy carried by the query var.
	// 2) Single-term archives of hierarchical taxonomies — list the queried
	//    term's direct children as a sub-navigation.
	if ( '' === $taxonomy_slug && class_exists( TermIndexRewrite::class ) ) {
		$taxonomy_slug = TermIndexRewrite::current_taxonomy();
	}
	if ( '' === $taxonomy_slug ) {
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Term ) {
			$queried_tax = get_taxonomy( $queried->taxonomy );
			if ( $queried_tax && $queried_tax->hierarchical ) {
				$taxonomy_slug   = $queried->taxonomy;
				$scope_parent_id = (int) $queried->term_id;
			}
		}
	}
	if ( '' === $taxonomy_slug ) {
		return;
	}

	$taxonomy = get_taxonomy( $taxonomy_slug );
	if ( false === $taxonomy ) {
		return;
	}

	$order_by   = isset( $attributes['orderBy'] ) && 'count' === $attributes['orderBy'] ? 'count' : 'name';
	$hide_empty = ! empty( $attributes['hideEmpty'] );
	$show_count = ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'];

	$term_args = array(
		'taxonomy'   => $taxonomy_slug,
		'orderby'    => $order_by,
		'order'      => 'count' === $order_by ? 'DESC' : 'ASC',
		'hide_empty' => $hide_empty,
	);
	// When we're scoped to a specific parent (single-term archive), only pull
	// that term's direct children. If it has none, render nothing — the block
	// should disappear on leaf-term archives so the page isn't cluttered.
	if ( null !== $scope_parent_id ) {
		$term_args['parent'] = $scope_parent_id;
	}

	$terms = get_terms( $term_args );
	if ( is_wp_error( $terms ) || array() === $terms ) {
		return;
	}

	// Hierarchical taxonomies render as a nested tree on index pages (top-
	// level terms act as group headings with children as a sub-list). When
	// we're scoped to one parent's direct children (single-term archive
	// context) we render flat because there's only one level to show.
	$is_hierarchical = (bool) $taxonomy->hierarchical && null === $scope_parent_id;
	$children_map    = array();
	if ( $is_hierarchical ) {
		foreach ( $terms as $term ) {
			$parent_id = (int) $term->parent;
			if ( ! isset( $children_map[ $parent_id ] ) ) {
				$children_map[ $parent_id ] = array();
			}
			$children_map[ $parent_id ][] = $term;
		}
	}

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class' => 'codelag-term-index' . ( $is_hierarchical ? ' codelag-term-index--tree' : ' codelag-term-index--flat' ),
		)
	);

	/**
	 * Count publish-status posts for a term. `$term->count` includes every
	 * status (link-only / private / draft), which would leak those states
	 * through the count badge. Restrict to publish so only listed lagoons
	 * contribute to the numbers users see.
	 */
	$publish_count_cache = array();
	$publish_count       = static function ( \WP_Term $term ) use ( $taxonomy, &$publish_count_cache ): int {
		$cache_key = $term->taxonomy . ':' . (int) $term->term_id;
		if ( isset( $publish_count_cache[ $cache_key ] ) ) {
			return $publish_count_cache[ $cache_key ];
		}
		$query = new \WP_Query(
			array(
				'post_type'              => $taxonomy->object_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => $term->taxonomy,
						'field'            => 'term_id',
						'terms'            => array( (int) $term->term_id ),
						'include_children' => false,
					),
				),
			)
		);
		$publish_count_cache[ $cache_key ] = (int) $query->found_posts;
		return $publish_count_cache[ $cache_key ];
	};

	/**
	 * Render a single term tile (link + name + optional count). Extracted so
	 * both the flat list and the hierarchical tree use identical markup for
	 * the leaf nodes.
	 */
	$render_term_tile = static function ( \WP_Term $term, bool $show_count ) use ( $publish_count ): void {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return;
		}
		?>
		<li class="codelag-term-index__item">
			<a class="codelag-term-index__link" href="<?php echo esc_url( $link ); ?>">
				<span class="codelag-term-index__name"><?php echo esc_html( $term->name ); ?></span>
				<?php if ( $show_count ) : ?>
					<span class="codelag-term-index__count"><?php echo esc_html( (string) $publish_count( $term ) ); ?></span>
				<?php endif; ?>
			</a>
		</li>
		<?php
	};
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( ! $is_hierarchical ) : ?>
			<ul class="codelag-term-index__list">
				<?php foreach ( $terms as $term ) : ?>
					<?php $render_term_tile( $term, $show_count ); ?>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<?php
			$top_level = $children_map[0] ?? array();
			foreach ( $top_level as $parent ) :
				$children = $children_map[ (int) $parent->term_id ] ?? array();
				?>
				<section class="codelag-term-index__group">
					<header class="codelag-term-index__group-head">
						<?php
						$parent_link = get_term_link( $parent );
						if ( ! is_wp_error( $parent_link ) ) :
							?>
							<a class="codelag-term-index__group-title" href="<?php echo esc_url( $parent_link ); ?>">
								<span><?php echo esc_html( $parent->name ); ?></span>
								<?php if ( $show_count ) : ?>
									<span class="codelag-term-index__count"><?php echo esc_html( (string) $publish_count( $parent ) ); ?></span>
								<?php endif; ?>
							</a>
						<?php else : ?>
							<span class="codelag-term-index__group-title"><?php echo esc_html( $parent->name ); ?></span>
						<?php endif; ?>
					</header>
					<?php if ( array() !== $children ) : ?>
						<ul class="codelag-term-index__list">
							<?php foreach ( $children as $child ) : ?>
								<?php $render_term_tile( $child, $show_count ); ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
} )( $attributes, $content, $block );
