/**
 * ASCII Art block registration.
 *
 * Dynamic block — render.php emits a <pre> with a random initial piece and
 * a JSON-serialised list of all pieces in a data-attribute; view.js cycles
 * through them on a timer at render-time.
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
