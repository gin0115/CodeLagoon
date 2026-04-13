/**
 * Lagoon Prose — save component.
 *
 * Outputs the wrapper plus the inner blocks' static markup so the prose
 * persists in post_content like any other static block.
 */

import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save( { className: 'lagoon-prose' } );

	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}
