<?php
/**
 * Server render for the `codelag/lagoon-filters` block.
 *
 * Emits a plain GET form pre-filled from the current URL params, so the
 * filter bar works with JS disabled. `view.js` progressively enhances the
 * form into a fetch-and-replace experience.
 *
 * The block detects the current archive context via `get_queried_object()`:
 * on `/snippets/language/<slug>/` the language filter is hidden (because
 * the user is already scoped to that language); same for tag / purpose.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 *
 * @package Gin0115\Codelagoon\Blocks
 */

use Gin0115\Codelagoon\Features\PostType\LagoonPostType;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonLanguageTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonPurposeTaxonomy;
use Gin0115\Codelagoon\Features\Taxonomy\LagoonTagTaxonomy;

defined( 'ABSPATH' ) || exit;

( static function ( array $attributes, string $content, WP_Block $block ): void {
	unset( $content, $block );

	// Enqueue select2 — this block renders three multi-selects that view.js
	// progressively enhances into chip pickers. Shipping the dependency from
	// here (rather than the theme) means any page that renders the block
	// gets the assets, with zero coupling to specific routes. Handle is
	// shared so duplicate enqueues on pages that already had it are no-ops.
	$select2_version = '4.0.13';
	$select2_cdn     = 'https://cdn.jsdelivr.net/npm/select2@' . $select2_version . '/dist';
	wp_enqueue_style(
		'select2',
		$select2_cdn . '/css/select2.min.css',
		array(),
		$select2_version
	);
	wp_enqueue_script(
		'select2',
		$select2_cdn . '/js/select2.min.js',
		array( 'jquery' ),
		$select2_version,
		true
	);

	$show_search   = ! isset( $attributes['showSearch'] ) || (bool) $attributes['showSearch'];
	$show_language = ! isset( $attributes['showLanguage'] ) || (bool) $attributes['showLanguage'];
	$show_date     = ! isset( $attributes['showDate'] ) || (bool) $attributes['showDate'];
	$show_purpose  = ! isset( $attributes['showPurpose'] ) || (bool) $attributes['showPurpose'];
	$show_tags     = ! isset( $attributes['showTags'] ) || (bool) $attributes['showTags'];
	$show_new      = ! isset( $attributes['showNewCta'] ) || (bool) $attributes['showNewCta'];
	$auto_hide     = ! isset( $attributes['autoHideCurrentTerm'] ) || (bool) $attributes['autoHideCurrentTerm'];
	$new_url       = isset( $attributes['newEntryUrl'] ) ? (string) $attributes['newEntryUrl'] : '';
	if ( '' === $new_url ) {
		$new_url = admin_url( 'post-new.php?post_type=' . LagoonPostType::POST_TYPE );
	}

	$queried = get_queried_object();

	// Auto-hide the filter that matches the current taxonomy archive.
	if ( $auto_hide && $queried instanceof WP_Term ) {
		if ( LagoonLanguageTaxonomy::TAXONOMY === $queried->taxonomy ) {
			$show_language = false;
		} elseif ( LagoonTagTaxonomy::TAXONOMY === $queried->taxonomy ) {
			$show_tags = false;
		} elseif ( LagoonPurposeTaxonomy::TAXONOMY === $queried->taxonomy ) {
			$show_purpose = false;
		}
	}

	// Form action — default to the current request path so the form posts
	// back to the same route (CPT archive, taxonomy archive, author archive,
	// a Page that embeds the filters, etc.) instead of defaulting to the
	// CPT archive and losing non-taxonomy scope (e.g. the author slug).
	// Filterable via `codelag_filters_form_action` so consumers (e.g. a
	// custom listing page) can override without touching the block.
	$action_url = (string) get_post_type_archive_link( LagoonPostType::POST_TYPE );
	if ( $queried instanceof WP_Term ) {
		$term_link = get_term_link( $queried );
		if ( ! is_wp_error( $term_link ) ) {
			$action_url = (string) $term_link;
		}
	} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
		// Strip the query string so existing filter params don't get doubled
		// up when the form is resubmitted.
		$request = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
		if ( '' !== $path ) {
			$action_url = home_url( $path );
		}
	}

	/**
	 * Filter the `<form action>` URL used by the lagoon-filters block.
	 *
	 * @param string $action_url Default: current request path (or taxonomy
	 *                           archive URL when viewing a term).
	 */
	$action_url = (string) apply_filters( 'codelag_filters_form_action', $action_url );

	/**
	 * Filter the list of hidden inputs rendered inside the form. Used to
	 * pass scoping flags (e.g. `collection=1`) that view.js then forwards
	 * to the REST endpoint via the form's FormData.
	 *
	 * @param array<string,string> $hidden_inputs name => value map.
	 */
	$hidden_inputs = (array) apply_filters( 'codelag_filters_hidden_inputs', array() );

	// Author-archive scoping flag. When the main query is scoped to a
	// specific user, we forward the author ID to view.js as a form-level
	// data attribute. view.js appends `author={id}` to the REST fetch URL
	// only (not the pushState'd URL), keeping the scope applied without
	// adding a redundant query param to the visible URL.
	$scoped_author = (int) get_query_var( 'author' );

	/**
	 * Normalise a taxonomy $_GET param into an array of sanitised slugs.
	 * Accepts either `?tag[]=a&tag[]=b` (array) or `?tag=a,b` (CSV) forms.
	 *
	 * @param string $key $_GET key.
	 * @return array<int,string>
	 */
	$collect_multi = static function ( string $key ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $key ] ) ) {
			return array();
		}
		$raw = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- array-vs-string check + sanitize_key applied via array_map below.
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$list = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		return array_values( array_filter( array_map( 'sanitize_key', $list ) ) );
	};

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params; no state change.
	$current_search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '';
	$current_date      = isset( $_GET['date_range'] ) ? sanitize_key( wp_unslash( (string) $_GET['date_range'] ) ) : 'any';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$current_languages = $collect_multi( 'filter_language' );
	$current_purposes  = $collect_multi( 'filter_purpose' );
	$current_tags      = $collect_multi( 'filter_tag' );

	// "Clear all" is only useful when at least one filter/search is active.
	$filters_active = ( '' !== $current_search )
		|| ( '' !== $current_date && 'any' !== $current_date )
		|| array() !== $current_languages
		|| array() !== $current_purposes
		|| array() !== $current_tags;

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class' => 'codelag-filters',
		)
	);

	// Emit wpApiSettings so view.js's fetch() can attach the REST nonce.
	// Without this, REST sees the caller as anonymous and scoped responses
	// (e.g. `collection=1`) return zero results. Gated to logged-in users
	// so we don't leak a nonce to anons who don't need one.
	if ( is_user_logged_in() ) {
		$nonce = wp_create_nonce( 'wp_rest' );
		wp_print_inline_script_tag(
			sprintf(
				'window.wpApiSettings = window.wpApiSettings || {}; window.wpApiSettings.nonce = %s; window.wpApiSettings.root = window.wpApiSettings.root || %s;',
				wp_json_encode( $nonce ),
				wp_json_encode( esc_url_raw( rest_url() ) )
			)
		);
	}
	?>
	<form
		<?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		method="get"
		action="<?php echo esc_url( $action_url ); ?>"
		data-codelag-filters
		<?php if ( $scoped_author > 0 ) : ?>
		data-codelag-author="<?php echo esc_attr( (string) $scoped_author ); ?>"
		<?php endif; ?>
	>
		<?php foreach ( $hidden_inputs as $input_name => $input_value ) : ?>
			<input
				type="hidden"
				name="<?php echo esc_attr( (string) $input_name ); ?>"
				value="<?php echo esc_attr( (string) $input_value ); ?>"
			/>
		<?php endforeach; ?>
		<?php if ( $show_search ) : ?>
			<label class="codelag-filters__slot codelag-filters__slot--search">
				<span class="material-symbols-outlined" aria-hidden="true">search</span>
				<input
					type="search"
					name="search"
					value="<?php echo esc_attr( $current_search ); ?>"
					placeholder="<?php esc_attr_e( 'Keyword, hash, or author…', 'codelag-blocks' ); ?>"
				/>
			</label>
		<?php endif; ?>

		<?php if ( $show_date ) : ?>
			<label class="codelag-filters__slot">
				<span class="codelag-filters__label"><?php esc_html_e( 'Date', 'codelag-blocks' ); ?></span>
				<select name="date_range">
					<?php
					$date_options = array(
						'any'  => __( 'Any time', 'codelag-blocks' ),
						'7d'   => __( 'Last 7 days', 'codelag-blocks' ),
						'30d'  => __( 'Last 30 days', 'codelag-blocks' ),
						'90d'  => __( 'Last 90 days', 'codelag-blocks' ),
						'year' => __( 'Last year', 'codelag-blocks' ),
					);
					foreach ( $date_options as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_date, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>

		<?php if ( $show_language ) : ?>
			<?php
			$languages = get_terms(
				array(
					'taxonomy'   => LagoonLanguageTaxonomy::TAXONOMY,
					'hide_empty' => false,
				)
			);
			?>
			<?php if ( is_array( $languages ) && array() !== $languages ) : ?>
				<label class="codelag-filters__slot codelag-filters__slot--multi">
					<span class="codelag-filters__label"><?php esc_html_e( 'Language', 'codelag-blocks' ); ?></span>
					<select name="filter_language[]" multiple>
						<?php foreach ( $languages as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php echo in_array( $term->slug, $current_languages, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $show_purpose ) : ?>
			<?php
			$purposes = get_terms(
				array(
					'taxonomy'   => LagoonPurposeTaxonomy::TAXONOMY,
					'hide_empty' => false,
				)
			);
			?>
			<?php if ( is_array( $purposes ) && array() !== $purposes ) : ?>
				<label class="codelag-filters__slot codelag-filters__slot--multi">
					<span class="codelag-filters__label"><?php esc_html_e( 'Purpose', 'codelag-blocks' ); ?></span>
					<select name="filter_purpose[]" multiple>
						<?php foreach ( $purposes as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php echo in_array( $term->slug, $current_purposes, true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $show_tags ) : ?>
			<?php
			$all_tags = get_terms(
				array(
					'taxonomy'   => LagoonTagTaxonomy::TAXONOMY,
					'hide_empty' => false,
				)
			);
			?>
			<?php if ( is_array( $all_tags ) && array() !== $all_tags ) : ?>
				<label class="codelag-filters__slot codelag-filters__slot--tags codelag-filters__slot--multi">
					<span class="codelag-filters__label"><?php esc_html_e( 'Tags', 'codelag-blocks' ); ?></span>
					<select name="filter_tag[]" multiple>
						<?php foreach ( $all_tags as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php echo in_array( $term->slug, $current_tags, true ) ? 'selected' : ''; ?>>
								#<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
		<?php endif; ?>

		<button type="submit" class="codelag-filters__apply">
			<span class="material-symbols-outlined" aria-hidden="true">filter_list</span>
			<span><?php esc_html_e( 'Filter', 'codelag-blocks' ); ?></span>
		</button>

		<a
			class="codelag-filters__clear"
			href="<?php echo esc_url( $action_url ); ?>"
			data-codelag-filters-clear
			style="<?php echo $filters_active ? '' : 'display:none'; ?>"
		>
			<span class="material-symbols-outlined" aria-hidden="true">close</span>
			<span><?php esc_html_e( 'Clear', 'codelag-blocks' ); ?></span>
		</a>
	</form>
	<?php
} )( $attributes, $content, $block );
