/**
 * Term Submenu — editor component.
 *
 * Sidebar lets the editor pick which taxonomy to list, how many terms to
 * show, the sort order, and the parent link (label + URL). The preview
 * inside the canvas fetches real terms from the REST API so the editor
 * sees what the frontend will render.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	RangeControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function Edit( { attributes, setAttributes } ) {
	const {
		taxonomy,
		count,
		orderBy,
		parentLabel,
		parentUrl,
		showCount,
	} = attributes;

	// Pull the list of available taxonomies so the picker always matches the
	// site's real registrations. Filter to public taxonomies so users don't
	// see internal-only ones (nav-menu/post-format/etc.).
	const taxonomies = useSelect(
		( select ) =>
			select( coreStore ).getTaxonomies( { per_page: -1 } ),
		[]
	);
	const taxonomyOptions = [
		{ value: '', label: __( '— Select a taxonomy —', 'codelag-blocks' ) },
		...( taxonomies || [] )
			.filter( ( t ) => t.visibility && t.visibility.public )
			.map( ( t ) => ( { value: t.slug, label: t.name } ) ),
	];

	// Preview: fetch terms for the selected taxonomy. Uses the REST taxonomy
	// route so we always get the canonical shape. Limited by `count` / order.
	const terms = useSelect(
		( select ) => {
			if ( ! taxonomy ) {
				return null;
			}
			return select( coreStore ).getEntityRecords( 'taxonomy', taxonomy, {
				per_page: Math.min( Math.max( 1, count ), 50 ),
				orderby: orderBy === 'name' ? 'name' : 'count',
				order: orderBy === 'name' ? 'asc' : 'desc',
				hide_empty: orderBy === 'count',
			} );
		},
		[ taxonomy, count, orderBy ]
	);

	const blockProps = useBlockProps( {
		className: 'wp-block-navigation-item wp-block-navigation-submenu codelag-term-submenu',
	} );

	const resolvedLabel =
		parentLabel ||
		( taxonomy &&
			taxonomies &&
			( taxonomies.find( ( t ) => t.slug === taxonomy )?.name || taxonomy ) ) ||
		__( 'Browse', 'codelag-blocks' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Source', 'codelag-blocks' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Taxonomy', 'codelag-blocks' ) }
						value={ taxonomy }
						options={ taxonomyOptions }
						onChange={ ( value ) => setAttributes( { taxonomy: value } ) }
					/>
					<RangeControl
						label={ __( 'Number of terms', 'codelag-blocks' ) }
						value={ count }
						min={ 1 }
						max={ 30 }
						onChange={ ( value ) => setAttributes( { count: value } ) }
					/>
					<SelectControl
						label={ __( 'Order by', 'codelag-blocks' ) }
						value={ orderBy }
						options={ [
							{ value: 'count', label: __( 'Most used', 'codelag-blocks' ) },
							{ value: 'name', label: __( 'Name (A–Z)', 'codelag-blocks' ) },
						] }
						onChange={ ( value ) => setAttributes( { orderBy: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show post counts', 'codelag-blocks' ) }
						checked={ showCount }
						onChange={ ( value ) => setAttributes( { showCount: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Parent link', 'codelag-blocks' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Label', 'codelag-blocks' ) }
						help={ __( 'Shown as the submenu trigger. Defaults to the taxonomy name.', 'codelag-blocks' ) }
						value={ parentLabel }
						onChange={ ( value ) => setAttributes( { parentLabel: value } ) }
					/>
					<TextControl
						label={ __( 'URL', 'codelag-blocks' ) }
						help={ __( 'Where the parent label links to. Leave blank to make the label a non-link toggle.', 'codelag-blocks' ) }
						value={ parentUrl }
						onChange={ ( value ) => setAttributes( { parentUrl: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<span className="wp-block-navigation-item__label codelag-term-submenu__parent">
					{ resolvedLabel }
					<span className="wp-block-navigation__submenu-icon" aria-hidden="true">
						<svg width="12" height="12" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" focusable="false">
							<path d="M1.5 4.5l4.5 4 4.5-4" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
						</svg>
					</span>
				</span>
				<ul className="wp-block-navigation__submenu-container codelag-term-submenu__list">
					{ ! taxonomy && (
						<li className="codelag-term-submenu__hint">
							{ __( 'Pick a taxonomy in the sidebar.', 'codelag-blocks' ) }
						</li>
					) }
					{ taxonomy && terms === null && (
						<li className="codelag-term-submenu__hint"><Spinner /></li>
					) }
					{ taxonomy && terms && terms.length === 0 && (
						<li className="codelag-term-submenu__hint">
							{ __( 'No terms found.', 'codelag-blocks' ) }
						</li>
					) }
					{ taxonomy && terms && terms.map( ( term ) => (
						<li key={ term.id } className="wp-block-navigation-item wp-block-navigation-link">
							<span className="wp-block-navigation-item__content">
								{ term.name }
								{ showCount && (
									<span className="codelag-term-submenu__count"> ({ term.count || 0 })</span>
								) }
							</span>
						</li>
					) ) }
				</ul>
			</div>
		</>
	);
}
