/**
 * Lagoon Card — editor component.
 *
 * Static preview of the card shape — the real rendered output comes from
 * render.php at runtime. Shows placeholder content so block-theme editors
 * can visualise the layout before preview.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { previewLines, maxTagChips, showFork, showView } = attributes;
	const blockProps = useBlockProps( {
		className: 'codelag-card codelag-card--editor-preview',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Card', 'codelag-blocks' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Preview lines', 'codelag-blocks' ) }
						value={ previewLines }
						onChange={ ( value ) =>
							setAttributes( { previewLines: value } )
						}
						min={ 2 }
						max={ 12 }
					/>
					<RangeControl
						label={ __( 'Max tag chips', 'codelag-blocks' ) }
						value={ maxTagChips }
						onChange={ ( value ) =>
							setAttributes( { maxTagChips: value } )
						}
						min={ 0 }
						max={ 10 }
					/>
					<ToggleControl
						label={ __( 'Show View action', 'codelag-blocks' ) }
						checked={ showView }
						onChange={ ( value ) =>
							setAttributes( { showView: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show Fork action', 'codelag-blocks' ) }
						checked={ showFork }
						onChange={ ( value ) =>
							setAttributes( { showFork: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<article { ...blockProps }>
				<header className="codelag-card__head">
					<div className="codelag-card__avatar" aria-hidden="true" />
					<div className="codelag-card__ident">
						<span className="codelag-card__title">
							{ __( 'Lagoon title', 'codelag-blocks' ) }
						</span>
						<span className="codelag-card__meta">
							{ __( 'by @author · recently', 'codelag-blocks' ) }
						</span>
					</div>
					<span className="codelag-chip codelag-chip--lang">
						LANG
					</span>
				</header>
				<pre className="codelag-card__preview" aria-hidden="true">
					<code>
						{ Array.from( { length: Math.min( previewLines, 4 ) } )
							.map( ( _, i ) => `// preview line ${ i + 1 }` )
							.join( '\n' ) }
					</code>
				</pre>
				<ul className="codelag-card__tags" aria-hidden="true">
					{ Array.from( { length: Math.min( maxTagChips, 3 ) } ).map(
						( _, i ) => (
							<li
								key={ i }
								className="codelag-chip codelag-chip--tag"
							>
								#tag
							</li>
						)
					) }
				</ul>
				<footer className="codelag-card__actions">
					{ showView && (
						<span className="codelag-card__action codelag-card__action--ghost">
							<span className="material-symbols-outlined">
								visibility
							</span>
							{ __( 'View', 'codelag-blocks' ) }
						</span>
					) }
					{ showFork && (
						<span className="codelag-card__action codelag-card__action--primary">
							<span className="material-symbols-outlined">
								fork_right
							</span>
							{ __( 'Fork', 'codelag-blocks' ) }
						</span>
					) }
				</footer>
			</article>
		</>
	);
}
