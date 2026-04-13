/**
 * Lagoon Prose — editor component.
 *
 * Renders a wrapper that hosts a restricted set of writing blocks. The
 * top-level position of this block is locked by the CPT template; what
 * lives INSIDE it is freely editable thanks to `templateLock={ false }`
 * on the InnerBlocks instance, which overrides the parent template lock.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * Whitelist of blocks the user can drop into a prose slot. Kept deliberately
 * small — this is for surrounding context, not for arbitrary page-building.
 * core/list and core/list-item must be paired so the list block actually
 * accepts items as children.
 */
const ALLOWED_BLOCKS = [
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/list-item',
	'core/quote',
	'core/code',
	'core/separator',
	'core/image',
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'lagoon-prose' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks
				allowedBlocks={ ALLOWED_BLOCKS }
				templateLock={ false }
				renderAppender={ InnerBlocks.ButtonBlockAppender }
			/>
		</div>
	);
}
