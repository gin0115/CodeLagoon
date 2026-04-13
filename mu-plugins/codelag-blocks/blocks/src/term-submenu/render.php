<?php
/**
 * Server render for `codelag/term-submenu`.
 *
 * Emits a <li> that plugs into a core/navigation block and exposes a
 * dropdown of dynamically-fetched taxonomy terms. Markup deliberately
 * mirrors core/navigation-submenu so the Navigation block's CSS (including
 * overlay/submenu styles from theme.json) applies without any extra work.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 *
 * @package Gin0115\Codelagoon\Blocks
 */

defined( 'ABSPATH' ) || exit;

( static function ( array $attributes, string $content, WP_Block $block ): void {
	unset( $content );

	$taxonomy_slug = isset( $attributes['taxonomy'] ) ? (string) $attributes['taxonomy'] : '';
	if ( '' === $taxonomy_slug ) {
		return;
	}

	$taxonomy = get_taxonomy( $taxonomy_slug );
	if ( false === $taxonomy ) {
		return;
	}

	$count        = isset( $attributes['count'] ) ? max( 1, min( 30, (int) $attributes['count'] ) ) : 5;
	$order_by     = isset( $attributes['orderBy'] ) && 'name' === $attributes['orderBy'] ? 'name' : 'count';
	$show_count   = ! empty( $attributes['showCount'] );
	$parent_label = isset( $attributes['parentLabel'] ) ? trim( (string) $attributes['parentLabel'] ) : '';
	$parent_url   = isset( $attributes['parentUrl'] ) ? trim( (string) $attributes['parentUrl'] ) : '';

	if ( '' === $parent_label ) {
		$parent_label = $taxonomy->labels->name;
	}

	// For hierarchical taxonomies, only show top-level terms; children appear
	// on the taxonomy archive pages themselves. `parent => 0` is ignored by
	// flat taxonomies so this is safe for all cases.
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy_slug,
			'number'     => $count,
			'orderby'    => $order_by,
			'order'      => 'name' === $order_by ? 'ASC' : 'DESC',
			'hide_empty' => 'count' === $order_by,
			'parent'     => 0,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return;
	}

	/**
	 * Sum the post counts of a term plus all its descendants so the submenu
	 * shows an accurate roll-up (e.g. "PHP (6)" when 5 posts live under the
	 * PHP 8+ child term and 1 is tagged directly with PHP). Non-hierarchical
	 * taxonomies skip the walk and just return the term's own count.
	 */
	/**
	 * Count publish-status posts attached to a term (and its descendants for
	 * hierarchical taxonomies). `$term->count` includes every status, which
	 * would expose link-only / private lagoons via the submenu badge — we
	 * restrict to `publish` so listed lagoons are the only ones that tick the
	 * count up.
	 */
	$term_ids_counted_cache = array();
	$rollup_count           = static function ( \WP_Term $term ) use ( $taxonomy, &$term_ids_counted_cache ): int {
		$ids = array( (int) $term->term_id );
		if ( $taxonomy->hierarchical ) {
			$descendants = get_term_children( (int) $term->term_id, $term->taxonomy );
			if ( ! is_wp_error( $descendants ) && ! empty( $descendants ) ) {
				foreach ( $descendants as $child_id ) {
					$ids[] = (int) $child_id;
				}
			}
		}
		$cache_key = $term->taxonomy . ':' . implode( ',', $ids );
		if ( isset( $term_ids_counted_cache[ $cache_key ] ) ) {
			return $term_ids_counted_cache[ $cache_key ];
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
						'terms'            => $ids,
						'include_children' => false,
					),
				),
			)
		);
		$term_ids_counted_cache[ $cache_key ] = (int) $query->found_posts;
		return $term_ids_counted_cache[ $cache_key ];
	};

	// If the parent navigation block is set to open submenus on click, the
	// parent "link" must be a <button> per core's a11y pattern. Otherwise it
	// may be an <a>, and when no URL is configured we fall back to a
	// non-interactive <span> with the toggle button alongside.
	$open_on_click = ! empty( $block->context['openSubmenusOnClick'] );

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class' => 'wp-block-navigation-item wp-block-navigation-submenu codelag-term-submenu has-child',
		)
	);

	$submenu_icon = '<span class="wp-block-navigation__submenu-icon" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M1.5 4.5l4.5 4 4.5-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></span>';

	ob_start();
	?>
	<li <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( $open_on_click || '' === $parent_url ) : ?>
			<button
				class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle"
				aria-expanded="false"
			>
				<span class="wp-block-navigation-item__label"><?php echo esc_html( $parent_label ); ?></span>
				<?php echo $submenu_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
			</button>
		<?php else : ?>
			<a class="wp-block-navigation-item__content" href="<?php echo esc_url( $parent_url ); ?>">
				<span class="wp-block-navigation-item__label"><?php echo esc_html( $parent_label ); ?></span>
			</a>
			<button
				class="wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle"
				aria-label="<?php echo esc_attr( $parent_label ); ?>"
				aria-expanded="false"
			>
				<?php echo $submenu_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
			</button>
		<?php endif; ?>

		<ul class="wp-block-navigation__submenu-container codelag-term-submenu__list">
			<?php foreach ( $terms as $term ) : ?>
				<?php
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				?>
				<li class="wp-block-navigation-item wp-block-navigation-link">
					<a class="wp-block-navigation-item__content" href="<?php echo esc_url( $link ); ?>">
						<span class="wp-block-navigation-item__label"><?php echo esc_html( $term->name ); ?></span>
						<?php if ( $show_count ) : ?>
							<span class="codelag-term-submenu__count"> (<?php echo esc_html( (string) $rollup_count( $term ) ); ?>)</span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</li>
	<?php
	echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped inline.
} )( $attributes, $content, $block );
