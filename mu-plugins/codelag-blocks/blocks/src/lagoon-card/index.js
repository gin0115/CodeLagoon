/**
 * Lagoon Card block registration.
 *
 * Dynamic block (server-rendered via render.php). Sits inside a core Query
 * Loop's post-template and renders one lagoon summary card per post.
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
