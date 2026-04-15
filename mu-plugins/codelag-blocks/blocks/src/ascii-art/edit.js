/**
 * ASCII Art — editor component.
 *
 * The art pieces are shipped as a fixed list in block.json's attribute
 * defaults, so authors can't add/remove/edit entries — they only control
 * how often the frontend rotates between them. Preview shows one piece so
 * the author still sees a real render of the block.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { items, intervalMs } = attributes;

	const pieces = Array.isArray( items ) ? items : [];
	const preview = pieces.length > 0 ? pieces[ 0 ] : '';
	const count = pieces.length;

	const blockProps = useBlockProps( {
		className: 'codelag-ascii-art codelag-ascii-art--editor',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Rotation', 'codelag-blocks' ) }
					initialOpen={ true }
				>
					<RangeControl
						label={ __( 'Interval (ms)', 'codelag-blocks' ) }
						help={ __(
							'How long each piece stays on screen before a new random piece is picked.',
							'codelag-blocks'
						) }
						value={ intervalMs }
						min={ 500 }
						max={ 30000 }
						step={ 250 }
						onChange={ ( value ) =>
							setAttributes( {
								intervalMs:
									typeof value === 'number' ? value : 5000,
							} )
						}
					/>
					<p className="codelag-ascii-art__count">
						{ sprintf(
							/* translators: %d: number of ascii art pieces bundled with the block. */
							__(
								'%d pieces bundled with this block.',
								'codelag-blocks'
							),
							count
						) }
					</p>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<pre className="codelag-ascii-art__stage" aria-hidden="true">
					{ preview ||
						__( 'No ASCII art bundled.', 'codelag-blocks' ) }
				</pre>
			</div>
		</>
	);
}
