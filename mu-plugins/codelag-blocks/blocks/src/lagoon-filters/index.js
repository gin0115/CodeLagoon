/**
 * Lagoon Filters block registration.
 *
 * Dynamic block (render.php) — emits a filter bar whose behaviour depends
 * on the current archive context. View.js progressively enhances the plain
 * GET form into a fetch-and-replace client-side experience.
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
