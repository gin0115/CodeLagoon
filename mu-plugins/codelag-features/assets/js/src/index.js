/**
 * Lagoon editor sidebar — visibility picker.
 *
 * Registers a PluginDocumentSettingPanel with a three-way radio that drives
 * the post's `status` directly:
 *   - publish      → Listed (shown in every archive, search, REST list)
 *   - codelag_link → Link only (permalink works; excluded from listings)
 *   - private      → Private (author-only, hidden from everyone else)
 *
 * The lagoon CPT uses a locked block template so the canvas is thin — this
 * panel is the author's main per-post control surface beyond the title and
 * the block template slots.
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { RadioControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

const POST_TYPE = 'lagoon';

function VisibilityPanel() {
	const { postType, status } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postType: editor.getCurrentPostType(),
			status: editor.getEditedPostAttribute( 'status' ) || 'publish',
		};
	}, [] );

	const { editPost } = useDispatch( editorStore );

	if ( postType !== POST_TYPE ) {
		return null;
	}

	// Private can be represented as either `private` or sometimes surfaces as
	// `private` alongside visibility=password in core; we only care about the
	// three custom states here.
	const current =
		status === 'codelag_link' ? 'codelag_link' :
		status === 'private'      ? 'private'      :
		                             'publish';

	return (
		<PluginDocumentSettingPanel
			name="codelag-visibility"
			title={ __( 'Visibility', 'codelag-features' ) }
			className="codelag-visibility-panel"
		>
			<RadioControl
				selected={ current }
				onChange={ ( value ) => editPost( { status: value } ) }
				options={ [
					{
						label: __( 'Listed — shown in archives, search, and the API', 'codelag-features' ),
						value: 'publish',
					},
					{
						label: __( 'Link only — anyone with the link can view; hidden from listings', 'codelag-features' ),
						value: 'codelag_link',
					},
					{
						label: __( 'Private — only you can view', 'codelag-features' ),
						value: 'private',
					},
				] }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'codelag-visibility', { render: VisibilityPanel } );
