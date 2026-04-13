/**
 * Term Submenu block registration.
 *
 * Dynamic block — render.php emits a navigation-submenu that dynamically
 * lists top-N terms of a chosen taxonomy, with a parent link to a landing
 * page. Only insertable inside a core/navigation block.
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
