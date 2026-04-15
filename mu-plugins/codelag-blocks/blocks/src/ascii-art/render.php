<?php
/**
 * Server render for `codelag/ascii-art`.
 *
 * Emits a centered <pre> with one random ASCII art piece as the initial
 * paint, plus the full list serialised into a data attribute for view.js
 * to rotate through on the frontend.
 *
 * @var array<string,mixed> $attributes
 * @var string              $content
 * @var WP_Block            $block
 *
 * @package Gin0115\Codelagoon\Blocks
 */

defined( 'ABSPATH' ) || exit;

( static function ( array $attributes ): void {
	$raw_items = isset( $attributes['items'] ) && is_array( $attributes['items'] )
		? $attributes['items']
		: array();

	// Drop empties (common when an author adds a piece then changes their
	// mind) so view.js never lands on a blank frame.
	$items = array();
	foreach ( $raw_items as $item ) {
		if ( is_string( $item ) && '' !== trim( $item ) ) {
			$items[] = $item;
		}
	}

	if ( array() === $items ) {
		return;
	}

	$interval = isset( $attributes['intervalMs'] ) ? (int) $attributes['intervalMs'] : 5000;
	if ( $interval < 500 ) {
		$interval = 500;
	}

	// Pick the initial frame at random so every page load starts differently.
	$initial = $items[ array_rand( $items ) ];

	$items_json = wp_json_encode( $items );
	if ( false === $items_json ) {
		$items_json = '[]';
	}

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class'              => 'codelag-ascii-art',
			'data-codelag-ascii' => '1',
			'data-interval'      => (string) $interval,
			'data-items'         => $items_json,
		)
	);

	$show_next = count( $items ) > 1;
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-built attribute string. ?>>
		<pre class="codelag-ascii-art__stage" data-codelag-ascii-stage><?php echo esc_html( $initial ); ?></pre>
		<?php if ( $show_next ) : ?>
			<button
				type="button"
				class="codelag-ascii-art__next"
				data-codelag-ascii-next
				aria-label="<?php esc_attr_e( 'Show next ASCII art', 'codelag-blocks' ); ?>"
			>
				<?php esc_html_e( 'next', 'codelag-blocks' ); ?>
			</button>
		<?php endif; ?>
	</div>
	<?php
} )( $attributes );
