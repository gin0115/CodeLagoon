/**
 * Term Index — editor component.
 *
 * In the editor we show a live preview of the term list. When no taxonomy
 * is explicitly set we default to the first lagoon taxonomy so authors see
 * something useful; on the frontend, render.php picks the taxonomy from
 * the current term-index page's query var instead.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function Edit( { attributes, setAttributes } ) {
	const { taxonomy, orderBy, hideEmpty, showCount } = attributes;

	const taxonomies = useSelect(
		( select ) => select( coreStore ).getTaxonomies( { per_page: -1 } ),
		[]
	);
	const taxonomyOptions = [
		{
			value: '',
			label: __( '— Auto (use page context) —', 'codelag-blocks' ),
		},
		...( taxonomies || [] )
			.filter( ( t ) => t.visibility && t.visibility.public )
			.map( ( t ) => ( { value: t.slug, label: t.name } ) ),
	];

	const previewTaxonomy =
		taxonomy ||
		( taxonomies || [] ).find( ( t ) => t.slug.startsWith( 'lagoon_' ) )
			?.slug ||
		'';

	const terms = useSelect(
		( select ) => {
			if ( ! previewTaxonomy ) {
				return null;
			}
			return select( coreStore ).getEntityRecords(
				'taxonomy',
				previewTaxonomy,
				{
					per_page: 50,
					orderby: orderBy === 'count' ? 'count' : 'name',
					order: orderBy === 'count' ? 'desc' : 'asc',
					hide_empty: hideEmpty,
				}
			);
		},
		[ previewTaxonomy, orderBy, hideEmpty ]
	);

	const blockProps = useBlockProps( { className: 'codelag-term-index' } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Source', 'codelag-blocks' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Taxonomy', 'codelag-blocks' ) }
						help={ __(
							'Leave on Auto to follow the current term-index page (e.g. /snippets/language/).',
							'codelag-blocks'
						) }
						value={ taxonomy }
						options={ taxonomyOptions }
						onChange={ ( value ) =>
							setAttributes( { taxonomy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Order by', 'codelag-blocks' ) }
						value={ orderBy }
						options={ [
							{
								value: 'name',
								label: __( 'Name (A–Z)', 'codelag-blocks' ),
							},
							{
								value: 'count',
								label: __( 'Most used', 'codelag-blocks' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { orderBy: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Hide empty terms', 'codelag-blocks' ) }
						checked={ hideEmpty }
						onChange={ ( value ) =>
							setAttributes( { hideEmpty: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show post counts', 'codelag-blocks' ) }
						checked={ showCount }
						onChange={ ( value ) =>
							setAttributes( { showCount: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! previewTaxonomy && (
					<p className="codelag-term-index__hint">
						{ __(
							'No taxonomy selected and none detected.',
							'codelag-blocks'
						) }
					</p>
				) }
				{ previewTaxonomy && terms === null && <Spinner /> }
				{ previewTaxonomy && terms && terms.length === 0 && (
					<p className="codelag-term-index__hint">
						{ __( 'No terms to show.', 'codelag-blocks' ) }
					</p>
				) }
				{ previewTaxonomy && terms && terms.length > 0 && (
					<ul className="codelag-term-index__list">
						{ terms.map( ( term ) => (
							<li
								key={ term.id }
								className="codelag-term-index__item"
							>
								<span className="codelag-term-index__name">
									{ term.name }
								</span>
								{ showCount && (
									<span className="codelag-term-index__count">
										{ term.count || 0 }
									</span>
								) }
							</li>
						) ) }
					</ul>
				) }
			</div>
		</>
	);
}
