/**
 * Lagoon Filters — editor component.
 *
 * Static preview of the filter bar layout — the live filter UI (with real
 * term options pulled from the DB) renders from render.php on the frontend.
 * Here we just paint the shape so designers see the bar in the editor.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const {
		showSearch,
		showLanguage,
		showDate,
		showPurpose,
		showTags,
		showNewCta,
		newEntryUrl,
		autoHideCurrentTerm,
	} = attributes;
	const blockProps = useBlockProps( { className: 'codelag-filters codelag-filters--editor-preview' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filters', 'codelag-blocks' ) } initialOpen={ true }>
					<ToggleControl
						label={ __( 'Show search input', 'codelag-blocks' ) }
						checked={ showSearch }
						onChange={ ( value ) => setAttributes( { showSearch: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show language select', 'codelag-blocks' ) }
						checked={ showLanguage }
						onChange={ ( value ) => setAttributes( { showLanguage: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show date range', 'codelag-blocks' ) }
						checked={ showDate }
						onChange={ ( value ) => setAttributes( { showDate: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show purpose select', 'codelag-blocks' ) }
						checked={ showPurpose }
						onChange={ ( value ) => setAttributes( { showPurpose: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show tag multi-select', 'codelag-blocks' ) }
						checked={ showTags }
						onChange={ ( value ) => setAttributes( { showTags: value } ) }
					/>
					<ToggleControl
						label={ __( 'Auto-hide current taxonomy', 'codelag-blocks' ) }
						help={ __( 'On a taxonomy archive, hide the filter that matches the current term.', 'codelag-blocks' ) }
						checked={ autoHideCurrentTerm }
						onChange={ ( value ) => setAttributes( { autoHideCurrentTerm: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Call to action', 'codelag-blocks' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show "New entry" button', 'codelag-blocks' ) }
						checked={ showNewCta }
						onChange={ ( value ) => setAttributes( { showNewCta: value } ) }
					/>
					<TextControl
						label={ __( 'New entry URL', 'codelag-blocks' ) }
						help={ __( 'Leave blank to use wp-admin new-post URL.', 'codelag-blocks' ) }
						value={ newEntryUrl }
						onChange={ ( value ) => setAttributes( { newEntryUrl: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<form { ...blockProps } onSubmit={ ( e ) => e.preventDefault() }>
				{ showSearch && (
					<span className="codelag-filters__slot codelag-filters__slot--search">
						<span className="material-symbols-outlined" aria-hidden="true">search</span>
						<span className="codelag-filters__placeholder">{ __( 'Keyword, hash, or author…', 'codelag-blocks' ) }</span>
					</span>
				) }
				{ showLanguage && (
					<span className="codelag-filters__slot"><span className="codelag-filters__label">{ __( 'Language', 'codelag-blocks' ) }</span></span>
				) }
				{ showDate && (
					<span className="codelag-filters__slot"><span className="codelag-filters__label">{ __( 'Date', 'codelag-blocks' ) }</span></span>
				) }
				{ showPurpose && (
					<span className="codelag-filters__slot"><span className="codelag-filters__label">{ __( 'Purpose', 'codelag-blocks' ) }</span></span>
				) }
				{ showTags && (
					<span className="codelag-filters__slot"><span className="codelag-filters__label">{ __( 'Tags', 'codelag-blocks' ) }</span></span>
				) }
				{ showNewCta && (
					<span className="codelag-filters__cta">
						<span className="material-symbols-outlined" aria-hidden="true">add</span>
						<span>{ __( 'New entry', 'codelag-blocks' ) }</span>
					</span>
				) }
			</form>
		</>
	);
}
