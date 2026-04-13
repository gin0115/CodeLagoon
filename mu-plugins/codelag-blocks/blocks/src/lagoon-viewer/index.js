/**
 * CodeLagoon — Lagoon Viewer block registration.
 *
 * Dynamic block: save returns null, full render happens server-side in render.php.
 * The Edit component fetches and mutates files via the custom REST API.
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
