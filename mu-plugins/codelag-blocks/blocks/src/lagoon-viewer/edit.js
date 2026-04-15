/**
 * Lagoon Viewer — block editor component.
 *
 * Fetches the files belonging to the current lagoon via the custom REST API,
 * renders each as a collapsible draggable card, and wires up create / update
 * / delete / reorder actions back to the same endpoints. Monaco is the
 * editor, lazy-loaded from jsDelivr on first use.
 */

import {
	useEffect,
	useState,
	useMemo,
	useCallback,
	useRef,
} from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect, subscribe, select, dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	TextareaControl,
	SelectControl,
	Notice,
	Spinner,
	Modal,
	Dropdown,
} from '@wordpress/components';
// eslint-disable-next-line import/no-extraneous-dependencies -- installed transitively via @wordpress/components; listing it explicitly would require a package-lock refresh we can't run from CI.
import {
	search,
	code,
	commentContent,
	chevronUp,
	chevronDown,
	arrowUp,
	arrowDown,
	copy,
	closeSmall,
	formatIndent,
	formatOutdent,
	dragHandle,
	trash,
	plus,
	aspectRatio,
	seen,
} from '@wordpress/icons';

import Editor, { loader } from '@monaco-editor/react';
// Standard monaco-editor import. monaco-editor-webpack-plugin (registered in
// the project's webpack.config.js) intercepts this import and bundles only the
// languages + features we asked for, sets up workers correctly, and crucially
// initialises the theme service so it actually emits the `monaco-colors`
// stylesheet with `.mtk*` token rules. None of the manual overrides we used
// before (deep editor.main import, worker shim, parent-doc style mirroring,
// internal-API tokenColorMap extraction) are needed anymore.
import * as monaco from 'monaco-editor';

import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

import {
	SiPhp,
	SiJavascript,
	SiTypescript,
	SiHtml5,
	SiCss3,
	SiSass,
	SiJson,
	SiYaml,
	SiMarkdown,
	SiGnubash,
	SiMysql,
	SiPython,
	SiGo,
	SiRust,
} from 'react-icons/si';

/**
 * Inline "page-with-extension-label" SVG icon. Used as a generic notepad-style
 * fallback icon for languages that don't have a brand logo (plaintext, xml,
 * future custom types). Pass an optional `label` to draw a coloured band with
 * the file type text — leave it blank for the plain notepad look.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label Optional band label (empty = plain notepad look).
 * @param {string} props.color Band colour.
 * @param {number} props.size  Icon side length in px.
 */
function FileTypeIcon( { label = '', color = '#7dd3c0', size = 22 } ) {
	return (
		<svg
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
		>
			<path
				d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
				fill="#fff5ee"
				stroke="#1a1a1a"
				strokeWidth="1.4"
				strokeLinejoin="round"
			/>
			<path
				d="M14 2v6h6"
				fill="none"
				stroke="#1a1a1a"
				strokeWidth="1.4"
				strokeLinejoin="round"
			/>
			{ ! label && (
				<>
					<line
						x1="7.5"
						y1="11"
						x2="16.5"
						y2="11"
						stroke="#1a1a1a"
						strokeWidth="1.2"
						strokeLinecap="round"
					/>
					<line
						x1="7.5"
						y1="13.5"
						x2="16.5"
						y2="13.5"
						stroke="#1a1a1a"
						strokeWidth="1.2"
						strokeLinecap="round"
					/>
					<line
						x1="7.5"
						y1="16"
						x2="13"
						y2="16"
						stroke="#1a1a1a"
						strokeWidth="1.2"
						strokeLinecap="round"
					/>
				</>
			) }
			{ !! label && (
				<>
					<rect
						x="3"
						y="13"
						width="18"
						height="6.5"
						rx="0.4"
						fill={ color }
						stroke="#1a1a1a"
						strokeWidth="1.2"
					/>
					<text
						x="12"
						y="17.6"
						textAnchor="middle"
						fontFamily="Arial Black, Arial, sans-serif"
						fontSize="4.5"
						fontWeight="900"
						fill="#1a1a1a"
					>
						{ label }
					</text>
				</>
			) }
		</svg>
	);
}

// Map our language slugs to (icon component, brand colour) so the file-card
// language badge shows the actual logo for the language instead of plain text.
// Languages without an SI logo fall through to FileTypeIcon below.
const LANGUAGE_ICONS = {
	php: { Icon: SiPhp, color: '#777BB4' },
	javascript: { Icon: SiJavascript, color: '#F7DF1E' },
	typescript: { Icon: SiTypescript, color: '#3178C6' },
	html: { Icon: SiHtml5, color: '#E34F26' },
	css: { Icon: SiCss3, color: '#1572B6' },
	scss: { Icon: SiSass, color: '#CC6699' },
	json: { Icon: SiJson, color: '#000000' },
	yaml: { Icon: SiYaml, color: '#CB171E' },
	markdown: { Icon: SiMarkdown, color: '#000000' },
	shell: { Icon: SiGnubash, color: '#4EAA25' },
	sql: { Icon: SiMysql, color: '#4479A1' },
	python: { Icon: SiPython, color: '#3776AB' },
	go: { Icon: SiGo, color: '#00ADD8' },
	rust: { Icon: SiRust, color: '#000000' },
};

import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	arrayMove,
	SortableContext,
	sortableKeyboardCoordinates,
	useSortable,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import { LANGUAGE_OPTIONS, slugToMonacoLanguage } from './language-map';

// Custom Monaco theme JSON files. Originally from the `monaco-themes` package
// but copied into our source directory because that package's `exports` field
// blocks deep sub-path imports. Each is fed to `monaco.editor.defineTheme()`
// once on first use. The two built-ins (`vs`, `vs-dark`) need no JSON.
import draculaTheme from './themes/Dracula.json';
import monokaiTheme from './themes/Monokai.json';
import nightOwlTheme from './themes/Night Owl.json';
import tomorrowNightTheme from './themes/Tomorrow-Night.json';
import solarizedDarkTheme from './themes/Solarized-dark.json';
import cobaltTheme from './themes/Cobalt.json';
import twilightTheme from './themes/Twilight.json';

// Explicit color sets for the built-in vs / vs-dark themes. Monaco's bundled
// theme service does not emit these as inline CSS variables in this build, so
// we provide them here to override the SCSS dark defaults on the wrapper. The
// shapes match the `colors` field of the JSON themes loaded from monaco-themes.
const VS_DARK_DATA = {
	colors: {
		'editor.background': '#1e1e1e',
		'editor.foreground': '#d4d4d4',
		'editorWidget.background': '#252526',
		'editorWidget.foreground': '#cccccc',
		'editorWidget.border': '#454545',
		'editorCursor.foreground': '#aeafad',
		'editorLineNumber.foreground': '#858585',
		'editorLineNumber.activeForeground': '#c6c6c6',
		'editor.lineHighlightBackground': '#ffffff0a',
		'editor.lineHighlightBorder': '#ffffff0a',
		'editor.selectionBackground': '#264f78',
		'editor.inactiveSelectionBackground': '#3a3d41',
		'editor.selectionHighlightBackground': '#add6ff26',
		'editorIndentGuide.background': '#404040',
		'editorIndentGuide.activeBackground': '#707070',
		'editorGutter.background': '#1e1e1e',
		'editorWhitespace.foreground': '#3b3a32',
		'editorBracketMatch.background': '#0064001a',
		'editorBracketMatch.border': '#888888',
		'minimap.background': '#1e1e1e',
		foreground: '#cccccc',
	},
};

const VS_LIGHT_DATA = {
	colors: {
		'editor.background': '#ffffff',
		'editor.foreground': '#000000',
		'editorWidget.background': '#f3f3f3',
		'editorWidget.foreground': '#616161',
		'editorWidget.border': '#c8c8c8',
		'editorCursor.foreground': '#000000',
		'editorLineNumber.foreground': '#237893',
		'editorLineNumber.activeForeground': '#0b216f',
		'editor.lineHighlightBackground': '#0000000a',
		'editor.lineHighlightBorder': '#0000000a',
		'editor.selectionBackground': '#add6ff',
		'editor.inactiveSelectionBackground': '#e5ebf1',
		'editor.selectionHighlightBackground': '#add6ff80',
		'editorIndentGuide.background': '#d3d3d3',
		'editorIndentGuide.activeBackground': '#939393',
		'editorGutter.background': '#ffffff',
		'editorWhitespace.foreground': '#33333333',
		'editorBracketMatch.background': '#0064001a',
		'editorBracketMatch.border': '#b9b9b9',
		'minimap.background': '#ffffff',
		foreground: '#616161',
	},
};

const MONACO_THEMES = {
	'vs-dark': { label: 'Visual Studio Dark (built-in)', data: VS_DARK_DATA },
	vs: { label: 'Visual Studio Light (built-in)', data: VS_LIGHT_DATA },
	'night-owl': { label: 'Night Owl', data: nightOwlTheme },
	dracula: { label: 'Dracula', data: draculaTheme },
	monokai: { label: 'Monokai', data: monokaiTheme },
	'tomorrow-night': { label: 'Tomorrow Night', data: tomorrowNightTheme },
	'solarized-dark': { label: 'Solarized Dark', data: solarizedDarkTheme },
	cobalt: { label: 'Cobalt', data: cobaltTheme },
	twilight: { label: 'Twilight', data: twilightTheme },
};

const DEFAULT_THEME = 'vs-dark';

/**
 * Sanitize a theme JSON before handing it to Monaco. Some themes from the
 * monaco-themes package contain malformed rule entries — leading whitespace
 * in `fontStyle` values, empty objects, even null entries — which crash
 * Monaco's `defineTheme` parser ("Cannot read properties of undefined").
 *
 * @param {Object} data Raw Monaco theme JSON.
 */
function sanitizeThemeData( data ) {
	if ( ! data ) {
		return data;
	}
	const cleaned = { ...data };
	if ( Array.isArray( cleaned.rules ) ) {
		cleaned.rules = cleaned.rules
			.filter( ( rule ) => rule && typeof rule === 'object' )
			.map( ( rule ) => {
				const r = { ...rule };
				if ( typeof r.fontStyle === 'string' ) {
					r.fontStyle = r.fontStyle.trim();
					if ( '' === r.fontStyle ) {
						delete r.fontStyle;
					}
				}
				if ( typeof r.foreground === 'string' ) {
					r.foreground = r.foreground.trim();
				}
				if ( typeof r.background === 'string' ) {
					r.background = r.background.trim();
				}
				return r;
			} );
	}
	return cleaned;
}

/**
 * Register every custom theme with Monaco's theme service. Must run BEFORE any
 * editor mounts (via @monaco-editor/react's `beforeMount` hook) so the theme
 * names are known when the Editor component sets its `theme` prop. Each theme
 * is wrapped in try/catch so a single bad theme can't bring down the editor.
 *
 * Built-in themes (`vs`, `vs-dark`, `hc-black`, `hc-light`) are skipped — they
 * are already registered by Monaco internally, and our entries for them only
 * carry colour data for the React inline-style override path.
 */
const BUILTIN_THEME_KEYS = new Set( [
	'vs',
	'vs-dark',
	'hc-black',
	'hc-light',
] );

function defineAllThemes( monacoLib ) {
	if ( ! monacoLib ) {
		return;
	}
	Object.entries( MONACO_THEMES ).forEach( ( [ key, theme ] ) => {
		if ( BUILTIN_THEME_KEYS.has( key ) ) {
			return;
		}
		if ( ! theme.data || ! theme.data.base ) {
			return;
		}
		try {
			monacoLib.editor.defineTheme(
				key,
				sanitizeThemeData( theme.data )
			);
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn(
				'[codelag] Failed to register Monaco theme:',
				key,
				err
			);
		}
	} );
}

/**
 * Switch every existing Monaco editor to the given theme. `setTheme` is global
 * so one call updates all editors on the page in lockstep. With the webpack
 * plugin in place this is all the wiring we need — Monaco's theme service
 * handles colour CSS emission natively now.
 *
 * @param {Object} monacoLib The live monaco-editor module instance.
 * @param {string} themeKey  Monaco theme id to activate.
 */
function applyMonacoTheme( monacoLib, themeKey ) {
	if ( ! monacoLib || ! MONACO_THEMES[ themeKey ] ) {
		return;
	}
	try {
		monacoLib.editor.setTheme( themeKey );
	} catch ( err ) {
		// noop
	}
}

// Tell @monaco-editor/react to use the locally bundled monaco instance instead
// of lazy-loading from jsDelivr. monaco-editor-webpack-plugin handles the rest
// (worker registration, theme service, language tokenizers, CSS extraction).
loader.config( { monaco } );

/**
 * Curated list of Monaco actions to expose in every editor's right-click
 * context menu. Most of these actions are already registered internally by
 * Monaco but with no `contextMenuGroupId`, so they never appear when the
 * user right-clicks. The wrapper actions registered in
 * `registerContextMenuActions()` give them a group + label and delegate to
 * the original action's `run()`.
 *
 * Order within each group sets the visual order in the menu. Groups are
 * Monaco's standard sort keys — `1_` first, `9_` last — so our entries land
 * cleanly under the built-in clipboard items.
 *
 * Edit this list to prune (or rename / regroup) any items you don't want.
 */
const CONTEXT_MENU_ACTIONS = [
	// --- Line operations -----------------------------------------------
	{
		id: 'editor.action.moveLinesUpAction',
		label: 'Move Line Up',
		group: '5_lines',
		order: 1,
	},
	{
		id: 'editor.action.moveLinesDownAction',
		label: 'Move Line Down',
		group: '5_lines',
		order: 2,
	},
	{
		id: 'editor.action.copyLinesUpAction',
		label: 'Copy Line Up',
		group: '5_lines',
		order: 3,
	},
	{
		id: 'editor.action.copyLinesDownAction',
		label: 'Copy Line Down',
		group: '5_lines',
		order: 4,
	},
	{
		id: 'editor.action.deleteLines',
		label: 'Delete Line',
		group: '5_lines',
		order: 5,
	},
	{
		id: 'editor.action.joinLines',
		label: 'Join Lines',
		group: '5_lines',
		order: 6,
	},
	{
		id: 'editor.action.insertLineAfter',
		label: 'Insert Line Below',
		group: '5_lines',
		order: 7,
	},
	{
		id: 'editor.action.insertLineBefore',
		label: 'Insert Line Above',
		group: '5_lines',
		order: 8,
	},

	// --- Selection / multi-cursor -------------------------------------
	{
		id: 'editor.action.addSelectionToNextFindMatch',
		label: 'Select Next Occurrence',
		group: '6_select',
		order: 1,
	},
	{
		id: 'editor.action.selectHighlights',
		label: 'Select All Occurrences',
		group: '6_select',
		order: 2,
	},
	{
		id: 'editor.action.changeAll',
		label: 'Change All Occurrences',
		group: '6_select',
		order: 3,
	},

	// --- Text transforms ----------------------------------------------
	{
		id: 'editor.action.transformToUppercase',
		label: 'Transform to Uppercase',
		group: '7_transform',
		order: 1,
	},
	{
		id: 'editor.action.transformToLowercase',
		label: 'Transform to Lowercase',
		group: '7_transform',
		order: 2,
	},
	{
		id: 'editor.action.transformToTitlecase',
		label: 'Transform to Title Case',
		group: '7_transform',
		order: 3,
	},
	{
		id: 'editor.action.sortLinesAscending',
		label: 'Sort Lines Ascending',
		group: '7_transform',
		order: 4,
	},
	{
		id: 'editor.action.sortLinesDescending',
		label: 'Sort Lines Descending',
		group: '7_transform',
		order: 5,
	},
	{
		id: 'editor.action.removeDuplicateLines',
		label: 'Remove Duplicate Lines',
		group: '7_transform',
		order: 6,
	},
	{
		id: 'editor.action.trimTrailingWhitespace',
		label: 'Trim Trailing Whitespace',
		group: '7_transform',
		order: 7,
	},

	// --- Navigation / view --------------------------------------------
	{
		id: 'editor.action.gotoLine',
		label: 'Go to Line\u2026',
		group: '8_view',
		order: 1,
	},
	{
		id: 'editor.action.toggleWordWrap',
		label: 'Toggle Word Wrap',
		group: '8_view',
		order: 2,
	},
	{
		id: 'editor.action.jumpToBracket',
		label: 'Jump to Matching Bracket',
		group: '8_view',
		order: 3,
	},

	// --- Code-aware (no-op on languages without a service) ------------
	{
		id: 'editor.action.formatSelection',
		label: 'Format Selection',
		group: '9_code',
		order: 1,
	},
	{
		id: 'editor.action.quickFix',
		label: 'Quick Fix\u2026',
		group: '9_code',
		order: 2,
	},
	{
		id: 'editor.action.rename',
		label: 'Rename Symbol',
		group: '9_code',
		order: 3,
	},
];

/**
 * Register every action in CONTEXT_MENU_ACTIONS against the given editor
 * instance so they appear in the right-click menu. Each wrapper just
 * delegates to the original Monaco action — that way we don't reimplement
 * any behaviour, we just lift it into a context-menu-visible position.
 *
 * Wrapped in a try/catch per action so a missing action (e.g. one whose
 * underlying contribution was dropped from the webpack bundle) can't break
 * the rest of the registration.
 *
 * @param {Object} editor Monaco editor instance.
 */
function registerContextMenuActions( editor ) {
	if ( ! editor || typeof editor.addAction !== 'function' ) {
		return;
	}

	CONTEXT_MENU_ACTIONS.forEach( ( spec ) => {
		try {
			editor.addAction( {
				id: 'codelag.ctx.' + spec.id,
				label: spec.label,
				contextMenuGroupId: spec.group,
				contextMenuOrder: spec.order,
				keybindings: [],
				run: ( ed ) => {
					const action = ed.getAction( spec.id );
					if ( action ) {
						action.run();
					}
				},
			} );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.warn(
				'[lagoon-viewer] failed to add context-menu action',
				spec.id,
				err
			);
		}
	} );
}

/**
 * Mirror Monaco's `style.monaco-colors` from the parent document into the
 * Gutenberg editor-canvas iframe.
 *
 * Monaco's StandaloneThemeService creates a single `<style class="monaco-colors">`
 * element when it first runs and appends it to whichever document `globalThis`
 * resolved to at module-load time. In our setup that's the **parent** wp-admin
 * document (where webpack initially evaluated the chunk), but every Gutenberg
 * block — including our editor — is rendered inside `<iframe name="editor-canvas">`.
 * The `.mtk*` rules are in the parent's `<head>`; the `<span class="mtk*">` tokens
 * live in the iframe. Different documents = no styling.
 *
 * Fix: copy the parent's `style.monaco-colors` element into the iframe's `<head>`,
 * and keep them in sync via a MutationObserver so theme switches and lazy
 * tokenizer loads also propagate.
 */
function mirrorMonacoColorsToEditorIframes() {
	if ( typeof document === 'undefined' ) {
		return () => {};
	}

	const sourceDoc = document;
	const cloned = new WeakMap();

	const findSourceStyles = () => {
		const main = sourceDoc.querySelector( 'style.monaco-colors' );
		const extras = Array.from(
			sourceDoc.querySelectorAll( 'style' )
		).filter( ( s ) => /\.mtk\d+\s*\{/.test( s.textContent || '' ) );
		const all = new Set();
		if ( main ) {
			all.add( main );
		}
		extras.forEach( ( e ) => all.add( e ) );
		return Array.from( all );
	};

	const cloneInto = ( style, targetDoc ) => {
		try {
			const key = style;
			const existing = cloned.get( key );
			if ( existing && existing.has( targetDoc ) ) {
				const node = existing.get( targetDoc );
				node.textContent = style.textContent || '';
				return;
			}
			const clone = sourceDoc.createElement( 'style' );
			clone.setAttribute( 'data-codelag-monaco-mirror', '1' );
			clone.textContent = style.textContent || '';
			targetDoc.head.appendChild( clone );
			let map = cloned.get( key );
			if ( ! map ) {
				map = new Map();
				cloned.set( key, map );
			}
			map.set( targetDoc, clone );
		} catch ( err ) {
			// noop
		}
	};

	const editorIframes = () => {
		return Array.from(
			sourceDoc.querySelectorAll( 'iframe[name="editor-canvas"]' )
		)
			.map( ( f ) => {
				try {
					return f.contentDocument;
				} catch ( e ) {
					return null;
				}
			} )
			.filter( Boolean );
	};

	const syncAll = () => {
		const styles = findSourceStyles();
		const iframes = editorIframes();
		styles.forEach( ( style ) => {
			iframes.forEach( ( idoc ) => cloneInto( style, idoc ) );
		} );
	};

	syncAll();

	const observers = [];

	if ( sourceDoc.head ) {
		const headObserver = new window.MutationObserver( () => syncAll() );
		headObserver.observe( sourceDoc.head, {
			childList: true,
			subtree: true,
			characterData: true,
		} );
		observers.push( headObserver );
	}

	const installBodyObserver = () => {
		if ( ! sourceDoc.body ) {
			return;
		}
		const bodyObserver = new window.MutationObserver( () => syncAll() );
		bodyObserver.observe( sourceDoc.body, {
			childList: true,
			subtree: true,
		} );
		observers.push( bodyObserver );
	};

	if ( sourceDoc.body ) {
		installBodyObserver();
	} else if ( sourceDoc.addEventListener ) {
		sourceDoc.addEventListener( 'DOMContentLoaded', installBodyObserver, {
			once: true,
		} );
	}

	return () => {
		observers.forEach( ( o ) => o.disconnect() );
	};
}

// Kick off mirroring once at module load. The React component re-runs it on
// mount via useEffect as well, but doing it here too catches the case where
// monaco-colors already exists by the time React first renders.
if ( typeof document !== 'undefined' ) {
	mirrorMonacoColorsToEditorIframes();
}

/**
 * Shape of the in-memory file model. `id` is the DB row ID once saved; new
 * unsaved files have id = null and a client-side `_key` for sortable tracking.
 *
 * @param {number} order    Target `file_order` index.
 * @param {string} name     Initial filename.
 * @param {string} language Initial Monaco language id.
 */
function makeEmptyFile( order, name = 'untitled.txt', language = 'plaintext' ) {
	return {
		id: null,
		_key:
			'new-' +
			Date.now() +
			'-' +
			Math.random().toString( 36 ).slice( 2, 9 ),
		name,
		description: '',
		language,
		content: '',
		file_order: order,
		_isDirty: true,
		_isSaving: false,
		_collapsed: false,
		_descriptionOpen: false,
		_previewOpen: false,
	};
}

function fileFromRest( row ) {
	return {
		id: row.id,
		_key: 'db-' + row.id,
		name: row.name || '',
		description: row.description || '',
		language: row.language || 'plaintext',
		content: row.content || '',
		file_order: row.file_order || 0,
		_isDirty: false,
		_isSaving: false,
		_collapsed: false,
		// Auto-reveal the description if there's already content in it, otherwise collapsed.
		_descriptionOpen: !! ( row.description && row.description.length > 0 ),
		_previewOpen: false,
	};
}

export default function Edit( { setAttributes } ) {
	const blockProps = useBlockProps( { className: 'lagoon-viewer-editor' } );

	const postId = useSelect(
		( s ) => s( 'core/editor' ).getCurrentPostId(),
		[]
	);

	// Bump the block's `contentVersion` attribute. Gutenberg watches block
	// attributes to decide whether the post is dirty — files live in our
	// custom DB table, not in post_content, so a plain file edit doesn't
	// register. Bumping this counter on every change makes Gutenberg's Save
	// button activate properly.
	const markPostDirty = useCallback( () => {
		setAttributes( { contentVersion: Date.now() } );
	}, [ setAttributes ] );

	const [ files, setFiles ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ hasLoaded, setHasLoaded ] = useState( false );

	// Editor theme — read once from user meta, then saved back on change.
	const [ editorTheme, setEditorTheme ] = useState( DEFAULT_THEME );
	const [ themeReady, setThemeReady ] = useState( false );
	const monacoLibRef = useRef( null );

	// Build the inline style for the current theme. CSS custom properties set on
	// this wrapper inherit down to every `.monaco-editor` descendant, so the
	// `var(--vscode-editor-background)` etc. lookups in Monaco's bundled CSS
	// resolve to the correct theme colours. Built-in vs-dark / vs have no JSON
	// data so they fall through to the baseline values declared in editor.scss.
	const themeStyle = useMemo( () => {
		const def = MONACO_THEMES[ editorTheme ];
		const colors = def && def.data && def.data.colors;
		if ( ! colors ) {
			return {};
		}
		const style = {};
		Object.entries( colors ).forEach( ( [ key, value ] ) => {
			const varName = '--vscode-' + key.replace( /\./g, '-' );
			style[ varName ] = value;
		} );
		return style;
	}, [ editorTheme ] );

	// Tracks the most recently focused Monaco editor instance so the top
	// toolbar's action buttons can route their commands to "the editor the
	// user was just working in", not a specific file card.
	const focusedEditorRef = useRef( null );

	const hasPostId = !! postId && postId > 0;

	// Mirror Monaco's monaco-colors style from the parent doc into the
	// editor-canvas iframe on mount. The function is also invoked at module
	// load time, but the iframe might not exist yet then; this useEffect
	// guarantees we run again after the React component has mounted.
	useEffect( () => {
		return mirrorMonacoColorsToEditorIframes();
	}, [] );

	// Load the user's preferred theme once on mount.
	useEffect( () => {
		apiFetch( { path: '/wp/v2/users/me?context=edit' } )
			.then( ( user ) => {
				const stored =
					user && user.meta && user.meta.codelag_editor_theme;
				if ( stored && MONACO_THEMES[ stored ] ) {
					setEditorTheme( stored );
				}
			} )
			.catch( () => {
				// noop — fall back to default
			} )
			.finally( () => setThemeReady( true ) );
	}, [] );

	// Apply the theme any time it changes (also on first apply once monaco is ready).
	useEffect( () => {
		if ( monacoLibRef.current && themeReady ) {
			applyMonacoTheme( monacoLibRef.current, editorTheme );
		}
	}, [ editorTheme, themeReady ] );

	// Persist a theme change to user meta.
	const handleThemeChange = useCallback( ( newTheme ) => {
		setEditorTheme( newTheme );
		apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { codelag_editor_theme: newTheme } },
		} ).catch( ( err ) => {
			setError(
				err?.message ||
					__( 'Failed to save theme preference.', 'codelag-blocks' )
			);
		} );
	}, [] );

	// Run a Monaco action against the most-recently focused editor.
	// Focus MUST be restored to the editor BEFORE calling action.run():
	// cursor-based actions (move line up/down, copy line, delete line, etc.)
	// check the active editor and bail silently when focus is still on the
	// toolbar button that fired them. Previously we focused after running,
	// which is why those actions worked in the right-click menu (where the
	// editor already has focus) but were no-ops from the toolbar.
	const runEditorAction = useCallback( ( actionId ) => {
		const editor = focusedEditorRef.current;
		if ( ! editor ) {
			return;
		}
		try {
			editor.focus();
		} catch ( err ) {
			// noop
		}
		const action = editor.getAction( actionId );
		if ( action ) {
			action.run();
		}
	}, [] );

	// Fetch files whenever the post id becomes known.
	useEffect( () => {
		if ( ! hasPostId ) {
			return;
		}
		setLoading( true );
		setError( null );
		apiFetch( { path: `/codelag/v1/lagoons/${ postId }/files` } )
			.then( ( rows ) => {
				setFiles(
					Array.isArray( rows ) ? rows.map( fileFromRest ) : []
				);
				setHasLoaded( true );
			} )
			.catch( ( err ) => {
				setError(
					err?.message ||
						__( 'Failed to load files.', 'codelag-blocks' )
				);
			} )
			.finally( () => setLoading( false ) );
	}, [ hasPostId, postId ] );

	// Hook into Gutenberg's post save lifecycle. When the user clicks the
	// global Save button, we lock the post save, persist every dirty file
	// via REST, then unlock so Gutenberg can finish saving the post body.
	// This is what makes "edit a file → click Save → it actually persists".
	useEffect( () => {
		const lockKey = 'codelag-files-saving';
		let isSaving = false;

		const unsubscribe = subscribe( () => {
			const editorStore = select( 'core/editor' );
			if ( ! editorStore ) {
				return;
			}
			const savingNow =
				editorStore.isSavingPost() && ! editorStore.isAutosavingPost();

			if ( savingNow && ! isSaving ) {
				isSaving = true;

				const dirtyFiles = filesRef.current.filter(
					( f ) => f._isDirty
				);
				if ( dirtyFiles.length === 0 ) {
					return;
				}

				dispatch( 'core/editor' ).lockPostSaving( lockKey );

				const currentPostId = postIdRef.current;
				if ( ! currentPostId ) {
					dispatch( 'core/editor' ).unlockPostSaving( lockKey );
					return;
				}

				const tasks = dirtyFiles.map( ( file ) => {
					const payload = {
						name: file.name,
						description: file.description,
						language: file.language,
						content: file.content,
					};
					const path = file.id
						? `/codelag/v1/lagoons/${ currentPostId }/files/${ file.id }`
						: `/codelag/v1/lagoons/${ currentPostId }/files`;
					const method = file.id ? 'PUT' : 'POST';
					return apiFetch( { path, method, data: payload } )
						.then( ( response ) => ( {
							key: file._key,
							response,
							error: null,
						} ) )
						.catch( ( err ) => ( {
							key: file._key,
							response: null,
							error: err,
						} ) );
				} );

				Promise.all( tasks ).then( ( results ) => {
					const failures = results.filter( ( r ) => r.error );

					setFiles( ( current ) =>
						current.map( ( f ) => {
							const result = results.find(
								( r ) => r.key === f._key
							);
							if ( ! result || result.error ) {
								return f;
							}
							return fileFromRest( result.response );
						} )
					);

					if ( failures.length > 0 ) {
						setError(
							__(
								'Some files failed to save. Check the file cards for details.',
								'codelag-blocks'
							)
						);
					}

					dispatch( 'core/editor' ).unlockPostSaving( lockKey );
				} );
			}

			if ( ! editorStore.isSavingPost() ) {
				isSaving = false;
			}
		} );

		return () => {
			unsubscribe();
			dispatch( 'core/editor' ).unlockPostSaving( lockKey );
		};
	}, [] );

	// ---------- helpers ----------

	const patchFile = useCallback(
		( key, patch ) => {
			setFiles( ( current ) =>
				current.map( ( f ) =>
					f._key === key ? { ...f, ...patch, _isDirty: true } : f
				)
			);
			markPostDirty();
		},
		[ markPostDirty ]
	);

	const toggleCollapse = useCallback( ( key ) => {
		setFiles( ( current ) =>
			current.map( ( f ) =>
				f._key === key ? { ...f, _collapsed: ! f._collapsed } : f
			)
		);
	}, [] );

	const toggleDescription = useCallback( ( key ) => {
		setFiles( ( current ) =>
			current.map( ( f ) =>
				f._key === key
					? { ...f, _descriptionOpen: ! f._descriptionOpen }
					: f
			)
		);
	}, [] );

	const togglePreview = useCallback( ( key ) => {
		setFiles( ( current ) =>
			current.map( ( f ) =>
				f._key === key ? { ...f, _previewOpen: ! f._previewOpen } : f
			)
		);
	}, [] );

	// New file modal state.
	const [ isNewFileModalOpen, setIsNewFileModalOpen ] = useState( false );
	const [ newFileName, setNewFileName ] = useState( 'untitled.txt' );
	const [ newFileLanguage, setNewFileLanguage ] = useState( 'plaintext' );

	const openNewFileModal = useCallback( () => {
		setNewFileName( 'untitled.txt' );
		setNewFileLanguage( 'plaintext' );
		setIsNewFileModalOpen( true );
	}, [] );

	const confirmNewFile = useCallback( () => {
		const trimmedName = newFileName.trim() || 'untitled.txt';
		setFiles( ( current ) => [
			...current,
			makeEmptyFile( current.length, trimmedName, newFileLanguage ),
		] );
		markPostDirty();
		setIsNewFileModalOpen( false );
	}, [ newFileName, newFileLanguage, markPostDirty ] );

	const removeFile = useCallback(
		async ( file ) => {
			if (
				// eslint-disable-next-line no-alert
				! window.confirm( __( 'Delete this file?', 'codelag-blocks' ) )
			) {
				return;
			}
			if ( file.id ) {
				try {
					await apiFetch( {
						path: `/codelag/v1/lagoons/${ postId }/files/${ file.id }`,
						method: 'DELETE',
					} );
				} catch ( err ) {
					setError(
						err?.message ||
							__( 'Failed to delete the file.', 'codelag-blocks' )
					);
					return;
				}
			}
			setFiles( ( current ) =>
				current.filter( ( f ) => f._key !== file._key )
			);
			markPostDirty();
		},
		[ postId, markPostDirty ]
	);

	// Stable refs to current files + state, used by the save-lifecycle
	// subscription below. We avoid re-subscribing on every state change.
	const filesRef = useRef( files );
	useEffect( () => {
		filesRef.current = files;
	}, [ files ] );

	const postIdRef = useRef( postId );
	useEffect( () => {
		postIdRef.current = postId;
	}, [ postId ] );

	// eslint-disable-next-line no-unused-vars
	const saveFile = useCallback(
		async ( file ) => {
			if ( ! hasPostId ) {
				return;
			}
			setFiles( ( current ) =>
				current.map( ( f ) =>
					f._key === file._key ? { ...f, _isSaving: true } : f
				)
			);
			try {
				const payload = {
					name: file.name,
					description: file.description,
					language: file.language,
					content: file.content,
				};
				let response;
				if ( file.id ) {
					response = await apiFetch( {
						path: `/codelag/v1/lagoons/${ postId }/files/${ file.id }`,
						method: 'PUT',
						data: payload,
					} );
				} else {
					response = await apiFetch( {
						path: `/codelag/v1/lagoons/${ postId }/files`,
						method: 'POST',
						data: payload,
					} );
				}
				const fresh = fileFromRest( response );
				setFiles( ( current ) =>
					current.map( ( f ) => ( f._key === file._key ? fresh : f ) )
				);
			} catch ( err ) {
				setError(
					err?.message ||
						__( 'Failed to save the file.', 'codelag-blocks' )
				);
				setFiles( ( current ) =>
					current.map( ( f ) =>
						f._key === file._key ? { ...f, _isSaving: false } : f
					)
				);
			}
		},
		[ hasPostId, postId ]
	);

	// ---------- drag + reorder ----------

	const sensors = useSensors(
		useSensor( PointerSensor ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const handleDragEnd = useCallback(
		async ( event ) => {
			const { active, over } = event;
			if ( ! over || active.id === over.id ) {
				return;
			}
			const oldIndex = files.findIndex( ( f ) => f._key === active.id );
			const newIndex = files.findIndex( ( f ) => f._key === over.id );
			if ( oldIndex < 0 || newIndex < 0 ) {
				return;
			}
			const reordered = arrayMove( files, oldIndex, newIndex ).map(
				( f, idx ) => ( {
					...f,
					file_order: idx,
				} )
			);
			setFiles( reordered );

			const savedIds = reordered
				.filter( ( f ) => f.id )
				.map( ( f ) => f.id );
			if ( savedIds.length > 0 ) {
				try {
					await apiFetch( {
						path: `/codelag/v1/lagoons/${ postId }/files/reorder`,
						method: 'POST',
						data: { ids: savedIds },
					} );
				} catch ( err ) {
					setError(
						err?.message ||
							__(
								'Failed to save the new order.',
								'codelag-blocks'
							)
					);
				}
			}
		},
		[ files, postId ]
	);

	const itemKeys = useMemo( () => files.map( ( f ) => f._key ), [ files ] );

	// ---------- render ----------

	if ( ! hasPostId ) {
		return (
			<div { ...blockProps }>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Save this lagoon as a draft to start adding files.',
						'codelag-blocks'
					) }
				</Notice>
			</div>
		);
	}

	return (
		<div
			{ ...blockProps }
			style={ { ...( blockProps.style || {} ), ...themeStyle } }
		>
			<div className="lagoon-viewer-editor__toolbar">
				<div className="lagoon-viewer-editor__toolbar-actions">
					<Button
						variant="primary"
						size="small"
						icon={ plus }
						onClick={ openNewFileModal }
					>
						{ __( 'New file', 'codelag-blocks' ) }
					</Button>

					<div className="lagoon-viewer-editor__toolbar-divider" />

					<Button
						size="small"
						icon={ search }
						label={ __( 'Find (Ctrl+F)', 'codelag-blocks' ) }
						onClick={ () => runEditorAction( 'actions.find' ) }
					/>
					<Button
						size="small"
						icon={
							<svg
								width="20"
								height="20"
								viewBox="0 0 24 24"
								xmlns="http://www.w3.org/2000/svg"
								aria-hidden="true"
							>
								{ /* Magnifier + pencil (find-replace-1). */ }
								<circle
									cx="10"
									cy="12"
									r="5"
									fill="none"
									stroke="currentColor"
									strokeWidth="1.6"
								/>
								<line
									x1="13.6"
									y1="15.6"
									x2="17.5"
									y2="19.5"
									stroke="currentColor"
									strokeWidth="1.6"
									strokeLinecap="round"
								/>
								<path
									d="M16 4.5 L18 6.5 L12.5 12 L10 12.5 L10.5 10 Z"
									fill="none"
									stroke="currentColor"
									strokeWidth="1.6"
									strokeLinejoin="round"
								/>
								<line
									x1="15.2"
									y1="5.3"
									x2="17.2"
									y2="7.3"
									stroke="currentColor"
									strokeWidth="1.6"
									strokeLinecap="round"
								/>
							</svg>
						}
						label={ __(
							'Find & Replace (Ctrl+H)',
							'codelag-blocks'
						) }
						onClick={ () =>
							runEditorAction(
								'editor.action.startFindReplaceAction'
							)
						}
					/>
					<Button
						size="small"
						icon={ code }
						label={ __(
							'Format document (Shift+Alt+F)',
							'codelag-blocks'
						) }
						onClick={ () =>
							runEditorAction( 'editor.action.formatDocument' )
						}
					/>
					<Button
						size="small"
						icon={ commentContent }
						label={ __(
							'Toggle line comment (Ctrl+/)',
							'codelag-blocks'
						) }
						onClick={ () =>
							runEditorAction( 'editor.action.commentLine' )
						}
					/>
					<Button
						size="small"
						icon={ formatIndent }
						label={ __( 'Indent', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction( 'editor.action.indentLines' )
						}
					/>
					<Button
						size="small"
						icon={ formatOutdent }
						label={ __( 'Outdent', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction( 'editor.action.outdentLines' )
						}
					/>
					<Button
						size="small"
						icon={ arrowUp }
						label={ __( 'Move line up', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction( 'editor.action.moveLinesUpAction' )
						}
					/>
					<Button
						size="small"
						icon={ arrowDown }
						label={ __( 'Move line down', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction(
								'editor.action.moveLinesDownAction'
							)
						}
					/>
					<Button
						size="small"
						icon={ copy }
						label={ __( 'Duplicate line below', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction(
								'editor.action.copyLinesDownAction'
							)
						}
					/>
					<Button
						size="small"
						icon={ closeSmall }
						label={ __( 'Delete line', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction( 'editor.action.deleteLines' )
						}
					/>
					<Button
						size="small"
						icon={ chevronUp }
						label={ __( 'Fold all', 'codelag-blocks' ) }
						onClick={ () => runEditorAction( 'editor.foldAll' ) }
					/>
					<Button
						size="small"
						icon={ chevronDown }
						label={ __( 'Unfold all', 'codelag-blocks' ) }
						onClick={ () => runEditorAction( 'editor.unfoldAll' ) }
					/>
					<Button
						size="small"
						icon={ aspectRatio }
						label={ __( 'Toggle minimap', 'codelag-blocks' ) }
						onClick={ () => {
							// Monaco doesn't register a `toggleMinimap` action
							// in the standalone build, so going through the
							// action registry is a no-op. Read the current
							// option off the focused editor and flip it
							// directly via updateOptions.
							const editor = focusedEditorRef.current;
							if ( ! editor ) {
								return;
							}
							const raw = editor.getRawOptions();
							const enabled = raw?.minimap?.enabled !== false;
							editor.updateOptions( {
								minimap: {
									...( raw?.minimap || {} ),
									enabled: ! enabled,
								},
							} );
							try {
								editor.focus();
							} catch ( err ) {
								// noop
							}
						} }
					/>
					<Button
						size="small"
						icon={
							<svg
								width="20"
								height="20"
								viewBox="0 0 24 24"
								xmlns="http://www.w3.org/2000/svg"
								fill="none"
								stroke="currentColor"
								strokeWidth="1.8"
								strokeLinecap="round"
								strokeLinejoin="round"
								aria-hidden="true"
							>
								{ /* Hash + right arrow (goto-3), hash stretched horizontally. */ }
								<line x1="1.5" y1="8" x2="15" y2="8" />
								<line x1="1.5" y1="14" x2="15" y2="14" />
								<line x1="6" y1="4" x2="5" y2="18" />
								<line x1="12" y1="4" x2="11" y2="18" />
								<line x1="15.5" y1="11" x2="22" y2="11" />
								<polyline points="19,8 22,11 19,14" />
							</svg>
						}
						label={ __( 'Go to line', 'codelag-blocks' ) }
						onClick={ () =>
							runEditorAction( 'editor.action.gotoLine' )
						}
					/>
				</div>

				<SelectControl
					label={ __( 'Editor theme', 'codelag-blocks' ) }
					value={ editorTheme }
					options={ Object.entries( MONACO_THEMES ).map(
						( [ key, theme ] ) => ( {
							value: key,
							label: theme.label,
						} )
					) }
					onChange={ handleThemeChange }
					__nextHasNoMarginBottom
				/>
			</div>

			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( null ) }
				>
					{ error }
				</Notice>
			) }

			{ loading && ! hasLoaded && (
				<div className="lagoon-viewer-editor__loading">
					<Spinner />
					<span>{ __( 'Loading files…', 'codelag-blocks' ) }</span>
				</div>
			) }

			<DndContext
				sensors={ sensors }
				collisionDetection={ closestCenter }
				onDragEnd={ handleDragEnd }
			>
				<SortableContext
					items={ itemKeys }
					strategy={ verticalListSortingStrategy }
				>
					{ files.map( ( file ) => (
						<FileCard
							key={ file._key }
							file={ file }
							editorTheme={ editorTheme }
							monacoLibRef={ monacoLibRef }
							focusedEditorRef={ focusedEditorRef }
							onUpdate={ ( patch ) =>
								patchFile( file._key, patch )
							}
							onDelete={ () => removeFile( file ) }
							onToggleCollapse={ () =>
								toggleCollapse( file._key )
							}
							onToggleDescription={ () =>
								toggleDescription( file._key )
							}
							onTogglePreview={ () => togglePreview( file._key ) }
						/>
					) ) }
				</SortableContext>
			</DndContext>

			{ isNewFileModalOpen && (
				<Modal
					title={ __( 'Add a new file', 'codelag-blocks' ) }
					onRequestClose={ () => setIsNewFileModalOpen( false ) }
					className="lagoon-new-file-modal"
				>
					<TextControl
						label={ __( 'Filename', 'codelag-blocks' ) }
						value={ newFileName }
						onChange={ setNewFileName }
						placeholder="example.php"
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Language', 'codelag-blocks' ) }
						value={ newFileLanguage }
						options={ LANGUAGE_OPTIONS }
						onChange={ setNewFileLanguage }
						__nextHasNoMarginBottom
					/>
					<div className="lagoon-new-file-modal__actions">
						<Button
							variant="tertiary"
							onClick={ () => setIsNewFileModalOpen( false ) }
						>
							{ __( 'Cancel', 'codelag-blocks' ) }
						</Button>
						<Button variant="primary" onClick={ confirmNewFile }>
							{ __( 'Add file', 'codelag-blocks' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}

/**
 * Pick the right icon for a file's language. Replaces a nested ternary in
 * {@see FileCard}'s Dropdown toggle, which eslint's `no-nested-ternary` rule
 * rejects. Returns JSX.
 *
 * @param {Object}        props               Icon selection props.
 * @param {Function|null} props.LangIcon      react-icons/si icon component for the language, if any.
 * @param {string|null}   props.langIconColor Brand colour for the react-icons icon.
 * @param {string}        props.langLabel     Human-readable language label (e.g. "PHP").
 * @param {string}        props.language      Monaco language id (used for the plain-text / XML fallbacks).
 */
function renderLangIcon( { LangIcon, langIconColor, langLabel, language } ) {
	if ( LangIcon ) {
		return (
			<LangIcon size={ 18 } color={ langIconColor } title={ langLabel } />
		);
	}
	if ( language === 'xml' ) {
		return <FileTypeIcon label="XML" color="#7dd3fc" />;
	}
	if ( language === 'plaintext' ) {
		return <FileTypeIcon label="TXT" color="#4ade80" />;
	}
	return <FileTypeIcon />;
}

/**
 * Sortable collapsible card for a single file.
 *
 * @param {Object}   props                     Component props.
 * @param {Object}   props.file                In-memory file row (see makeEmptyFile / fileFromRest).
 * @param {string}   props.editorTheme         Active Monaco theme id.
 * @param {Object}   props.monacoLibRef        Ref to the loaded monaco module instance.
 * @param {Object}   props.focusedEditorRef    Ref tracking which editor currently has focus.
 * @param {Function} props.onUpdate            Partial-update callback (patch fields on this file).
 * @param {Function} props.onDelete            Delete-this-file callback.
 * @param {Function} props.onToggleCollapse    Collapse / expand card callback.
 * @param {Function} props.onToggleDescription Description open / close callback.
 * @param {Function} props.onTogglePreview     Markdown-preview open / close callback.
 */
function FileCard( {
	file,
	editorTheme,
	monacoLibRef,
	focusedEditorRef,
	onUpdate,
	onDelete,
	onToggleCollapse,
	onToggleDescription,
	onTogglePreview,
} ) {
	const isMarkdown = file.language === 'markdown' || file.language === 'md';
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( {
		id: file._key,
	} );

	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.6 : 1,
	};

	// Hide the Monaco editor until its first layout call has fired. Without
	// this, Monaco mounts at its content-driven natural width and visibly
	// snaps to the parent's actual width once `automaticLayout` measures —
	// the snap is the layout flash the user sees on every new file card.
	const [ isEditorReady, setIsEditorReady ] = useState( false );

	const langLabel =
		( LANGUAGE_OPTIONS.find( ( o ) => o.value === file.language ) || {} )
			.label || file.language;
	const langIconDef = LANGUAGE_ICONS[ file.language ];
	const LangIcon = langIconDef ? langIconDef.Icon : null;
	const langIconColor = langIconDef ? langIconDef.color : null;
	// eslint-disable-next-line no-unused-vars
	const isXml = file.language === 'xml';

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `lagoon-file-card${
				file._collapsed ? ' is-collapsed' : ''
			}${ file._isDirty ? ' is-dirty' : '' }` }
		>
			<div className="lagoon-file-card__header">
				<Button
					icon={ dragHandle }
					size="small"
					label={ __( 'Drag to reorder', 'codelag-blocks' ) }
					className="lagoon-file-card__handle"
					{ ...attributes }
					{ ...listeners }
				/>

				<input
					type="text"
					className="lagoon-file-card__name"
					value={ file.name }
					placeholder={ __( 'filename', 'codelag-blocks' ) }
					onChange={ ( e ) => onUpdate( { name: e.target.value } ) }
				/>

				<Dropdown
					popoverProps={ { placement: 'bottom-end' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							size="small"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							aria-label={
								__( 'Change language:', 'codelag-blocks' ) +
								' ' +
								langLabel
							}
							className="lagoon-file-card__lang-badge"
						>
							{ renderLangIcon( {
								LangIcon,
								langIconColor,
								langLabel,
								language: file.language,
							} ) }
						</Button>
					) }
					renderContent={ ( { onClose } ) => (
						<div className="lagoon-file-card__lang-picker">
							<SelectControl
								label={ __( 'Language', 'codelag-blocks' ) }
								value={ file.language }
								options={ LANGUAGE_OPTIONS }
								onChange={ ( value ) => {
									onUpdate( { language: value } );
									onClose();
								} }
								__nextHasNoMarginBottom
							/>
						</div>
					) }
				/>

				<Button
					icon={ commentContent }
					size="small"
					label={
						file._descriptionOpen
							? __( 'Hide description', 'codelag-blocks' )
							: __( 'Edit description', 'codelag-blocks' )
					}
					onClick={ onToggleDescription }
					aria-pressed={ file._descriptionOpen }
					className={
						file._descriptionOpen
							? 'lagoon-file-card__desc-toggle is-active'
							: 'lagoon-file-card__desc-toggle'
					}
				/>

				{ isMarkdown && (
					<Button
						icon={ seen }
						size="small"
						label={
							file._previewOpen
								? __( 'Hide preview', 'codelag-blocks' )
								: __(
										'Show markdown preview',
										'codelag-blocks'
								  )
						}
						onClick={ onTogglePreview }
						aria-pressed={ file._previewOpen }
						className={
							file._previewOpen
								? 'lagoon-file-card__preview-toggle is-active'
								: 'lagoon-file-card__preview-toggle'
						}
					/>
				) }

				<Button
					icon={ file._collapsed ? chevronDown : chevronUp }
					size="small"
					label={
						file._collapsed
							? __( 'Expand file', 'codelag-blocks' )
							: __( 'Collapse file', 'codelag-blocks' )
					}
					onClick={ onToggleCollapse }
				/>

				<Button
					icon={ trash }
					size="small"
					isDestructive
					label={ __( 'Delete file', 'codelag-blocks' ) }
					onClick={ onDelete }
				/>
			</div>

			{ ! file._collapsed && (
				<div className="lagoon-file-card__body">
					{ file._descriptionOpen && (
						<div className="lagoon-file-card__description">
							<TextareaControl
								label={ __( 'Description', 'codelag-blocks' ) }
								value={ file.description }
								onChange={ ( value ) =>
									onUpdate( { description: value } )
								}
								rows={ 3 }
								__nextHasNoMarginBottom
							/>
						</div>
					) }

					{ isMarkdown && file._previewOpen ? (
						<div className="lagoon-file-card__preview">
							<ReactMarkdown remarkPlugins={ [ remarkGfm ] }>
								{ file.content || '' }
							</ReactMarkdown>
						</div>
					) : (
						<div
							className={ `lagoon-file-card__editor${
								isEditorReady ? ' is-ready' : ''
							}` }
						>
							<Editor
								height="100%"
								width="100%"
								language={ slugToMonacoLanguage(
									file.language
								) }
								value={ file.content }
								onChange={ ( value ) =>
									onUpdate( { content: value || '' } )
								}
								theme={ editorTheme }
								loading={ <Spinner /> }
								beforeMount={ ( monacoLib ) => {
									// Register every custom theme with Monaco BEFORE the
									// editor instance is created. After this, passing the
									// theme name via the `theme` prop is enough to switch.
									defineAllThemes( monacoLib );
								} }
								onMount={ ( editor, monacoLib ) => {
									if ( monacoLibRef ) {
										monacoLibRef.current = monacoLib;
									}
									// Belt-and-braces re-apply in case React's prop diff
									// missed the initial render.
									applyMonacoTheme( monacoLib, editorTheme );

									// Track focus so the top toolbar's action buttons can
									// route their commands to the editor the user was just
									// working in.
									if ( focusedEditorRef ) {
										focusedEditorRef.current = editor;
										try {
											editor.onDidFocusEditorWidget(
												() => {
													focusedEditorRef.current =
														editor;
												}
											);
										} catch ( err ) {
											// noop
										}
									}

									try {
										editor.layout();
									} catch ( err ) {
										// noop
									}
									window.requestAnimationFrame( () => {
										try {
											editor.layout();
										} catch ( err ) {
											// noop
										}
										// Flip the ready flag after the second
										// layout pass so the fade-in mask hides
										// the initial mount flash.
										setIsEditorReady( true );
									} );

									// Surface a curated set of Monaco actions
									// in the right-click menu under a single
									// "Codelagoon" group. Most of these are
									// already registered by Monaco internally
									// but their `contextMenuGroupId` is unset,
									// so they never appear when the user
									// right-clicks. We re-expose them by
									// adding thin wrappers that delegate to
									// the original action.
									registerContextMenuActions( editor );
								} }
								options={ {
									minimap: {
										enabled: true,
										side: 'right',
										renderCharacters: true,
										size: 'fill',
										scale: 2,
										maxColumn: 120,
										showSlider: 'always',
									},
									tabSize: 2,
									wordWrap: 'on',
									fontSize: 14,
									fontLigatures: true,
									automaticLayout: true,
									scrollBeyondLastLine: false,
									lineNumbers: 'on',
									bracketPairColorization: { enabled: true },
									guides: {
										bracketPairs: true,
										indentation: true,
										highlightActiveIndentation: true,
									},
									smoothScrolling: true,
									cursorBlinking: 'smooth',
									cursorSmoothCaretAnimation: 'on',
									renderLineHighlight: 'all',
									formatOnPaste: true,
									padding: { top: 12, bottom: 12 },
									scrollbar: {
										verticalScrollbarSize: 10,
										horizontalScrollbarSize: 10,
									},
									// Detach context menu / hover / autocomplete
									// widgets from the editor container so the
									// surrounding `overflow: hidden` (used to
									// stop the mount-flash from leaking) can't
									// clip them. Without this the right-click
									// menu loses items as soon as it hits the
									// container's edge.
									fixedOverflowWidgets: true,
								} }
							/>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
