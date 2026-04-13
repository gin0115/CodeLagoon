/**
 * Term Index block registration.
 *
 * Dynamic block — render.php builds the list server-side so counts /
 * ordering always reflect live data without a client fetch.
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
