/**
 * CodeLagoon — Lagoon Prose block registration.
 *
 * Static block: a thin wrapper around InnerBlocks with a restricted
 * `allowedBlocks` list. Used by the lagoon CPT template to provide an
 * editable intro / outro slot above and below the lagoon-viewer block,
 * while the rest of the document structure stays locked.
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
