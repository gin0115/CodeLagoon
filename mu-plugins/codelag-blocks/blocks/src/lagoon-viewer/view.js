/**
 * Frontend interactivity for the `codelag/lagoon-viewer` block.
 *
 * - Highlights non-markdown code blocks via Prism.
 * - Wires per-file copy buttons (reads the hidden raw textarea).
 * - Wires markdown source/preview toggle buttons.
 */

import Prism from 'prismjs';

// CSS dependencies — imported here (not in style.scss) because dart-sass'
// `@import "*.css"` does not inline through webpack's resolver, while JS-side
// CSS imports are extracted by MiniCssExtractPlugin into the `view.css`
// sibling that block.json's `viewStyle` then enqueues on the frontend.
//
// Only Prism's syntax theme is a third-party import. `.markdown-body` styling
// is authored by hand in style.scss so the prose is always plain white + black
// regardless of the site palette.
import 'prismjs/themes/prism-tomorrow.css';

// Language imports — order matters. Languages with dependencies must come
// AFTER the language they depend on (markup before php; javascript before
// typescript; css before scss; markup before markdown).
import 'prismjs/components/prism-markup';
import 'prismjs/components/prism-css';
import 'prismjs/components/prism-clike';
import 'prismjs/components/prism-javascript';
import 'prismjs/components/prism-typescript';
import 'prismjs/components/prism-markup-templating';
import 'prismjs/components/prism-php';
import 'prismjs/components/prism-scss';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-yaml';
import 'prismjs/components/prism-markdown';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-sql';
import 'prismjs/components/prism-python';
import 'prismjs/components/prism-go';
import 'prismjs/components/prism-rust';
import 'prismjs/components/prism-diff';

const COPY_RESET_MS = 2000;

/**
 * Highlight every <code> element under the given root that Prism can handle.
 *
 * @param {ParentNode} root
 */
function highlightAll( root ) {
	Prism.highlightAllUnder( root );
}

/**
 * Wire a single copy button. Reads its sibling [data-codelag-raw] textarea
 * and writes the value to the clipboard, swapping the label briefly.
 *
 * @param {HTMLElement} button
 */
function wireCopyButton( button ) {
	const file = button.closest( '.lagoon-file' );
	if ( ! file ) {
		return;
	}
	const raw = file.querySelector( '[data-codelag-raw]' );
	if ( ! raw ) {
		return;
	}
	const labelDefault = button.querySelector( '[data-codelag-copy-default]' );
	const labelSuccess = button.querySelector( '[data-codelag-copy-success]' );

	button.addEventListener( 'click', async () => {
		const text = raw.value;
		try {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				await navigator.clipboard.writeText( text );
			} else {
				// Fallback for older browsers / non-secure contexts.
				raw.removeAttribute( 'hidden' );
				raw.select();
				document.execCommand( 'copy' );
				raw.setAttribute( 'hidden', '' );
			}
			if ( labelDefault && labelSuccess ) {
				labelDefault.setAttribute( 'hidden', '' );
				labelSuccess.removeAttribute( 'hidden' );
				window.setTimeout( () => {
					labelSuccess.setAttribute( 'hidden', '' );
					labelDefault.removeAttribute( 'hidden' );
				}, COPY_RESET_MS );
			}
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( '[lagoon-viewer] copy failed', err );
		}
	} );
}

/**
 * Wire a single markdown toggle button. Flips visibility between the
 * rendered preview and the raw source <pre> within the same file body.
 *
 * @param {HTMLElement} button
 */
function wireMarkdownToggle( button ) {
	const file = button.closest( '.lagoon-file' );
	if ( ! file ) {
		return;
	}
	const body = file.querySelector( '[data-codelag-md-body]' );
	if ( ! body ) {
		return;
	}
	const preview = body.querySelector( '[data-codelag-md-preview]' );
	const source  = body.querySelector( '[data-codelag-md-source]' );
	const labelToPreview = button.querySelector( '[data-codelag-md-toggle-label-preview]' );
	const labelToSource  = button.querySelector( '[data-codelag-md-toggle-label-source]' );

	button.addEventListener( 'click', () => {
		if ( ! preview || ! source ) {
			return;
		}
		const showingSource = ! source.hasAttribute( 'hidden' );
		if ( showingSource ) {
			source.setAttribute( 'hidden', '' );
			preview.removeAttribute( 'hidden' );
			if ( labelToPreview && labelToSource ) {
				labelToSource.setAttribute( 'hidden', '' );
				labelToPreview.removeAttribute( 'hidden' );
			}
		} else {
			preview.setAttribute( 'hidden', '' );
			source.removeAttribute( 'hidden' );
			if ( labelToPreview && labelToSource ) {
				labelToPreview.setAttribute( 'hidden', '' );
				labelToSource.removeAttribute( 'hidden' );
			}
			// Highlight the source on first reveal — Prism skips hidden nodes.
			const code = source.querySelector( 'code' );
			if ( code && ! code.dataset.codelagHighlighted ) {
				Prism.highlightElement( code );
				code.dataset.codelagHighlighted = '1';
			}
		}
	} );
}

/**
 * Wire a "Fork this lagoon" button. Reads the lagoon ID, REST root and nonce
 * from the button's data attributes (the shortcode renders these server-side
 * because the static block template can't), POSTs to the fork endpoint, and
 * navigates to the new lagoon's edit screen on success.
 *
 * @param {HTMLButtonElement} button
 */
function wireForkButton( button ) {
	const lagoonId = button.getAttribute( 'data-lagoon-id' );
	const nonce    = button.getAttribute( 'data-fork-nonce' );
	const restRoot = button.getAttribute( 'data-fork-rest-root' );

	if ( ! lagoonId || ! nonce || ! restRoot ) {
		return;
	}

	const labelDefault = button.querySelector( '[data-codelag-fork-default]' );
	const labelBusy    = button.querySelector( '[data-codelag-fork-busy]' );

	button.addEventListener( 'click', async () => {
		if ( button.disabled ) {
			return;
		}
		button.disabled = true;
		if ( labelDefault && labelBusy ) {
			labelDefault.setAttribute( 'hidden', '' );
			labelBusy.removeAttribute( 'hidden' );
		}

		const url = restRoot.replace( /\/$/, '' ) + '/codelag/v1/lagoons/' + encodeURIComponent( lagoonId ) + '/fork';

		try {
			const response = await fetch( url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
			} );

			if ( ! response.ok ) {
				const body = await response.json().catch( () => ( {} ) );
				throw new Error( body && body.message ? body.message : 'Fork request failed (' + response.status + ').' );
			}

			const data = await response.json();
			if ( data && data.edit_link ) {
				window.location.assign( data.edit_link );
				return;
			}

			throw new Error( 'Fork response missing edit_link.' );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( '[lagoon-viewer] fork failed', err );
			button.disabled = false;
			if ( labelDefault && labelBusy ) {
				labelBusy.setAttribute( 'hidden', '' );
				labelDefault.removeAttribute( 'hidden' );
			}
		}
	} );
}

// ---------------------------------------------------------------------------
// Theme picker
//
// Lets the user swap Prism + github-markdown stylesheets at runtime. We don't
// bundle every theme variant — instead each theme is fetched from jsDelivr on
// demand and injected as a `<link>` in the document head, with the choice
// persisted in localStorage. The bundled prism-tomorrow + github-markdown-dark
// loaded by view.js at the top of this file remain as the visual default
// before any theme has been picked; once the user picks one, the dynamic
// link tags appear LATER in the head and override the bundled rules by
// source order. Keys here MUST stay in sync with ThemePickerShortcode::THEMES.
// ---------------------------------------------------------------------------

const THEMES = {
	tomorrow:       { prism: 'tomorrow',       dark: true },
	okaidia:        { prism: 'okaidia',        dark: true },
	twilight:       { prism: 'twilight',       dark: true },
	default:        { prism: 'default',        dark: false },
	coy:            { prism: 'coy',            dark: false },
	solarizedlight: { prism: 'solarizedlight', dark: false },
};

const PRISM_VERSION = '1.30.0';
const STORAGE_KEY   = 'codelag-theme';
const PRISM_LINK_ID = 'codelag-prism-theme';

/**
 * Build the jsDelivr URL for a Prism theme. The "default" light theme is
 * served as `prism.min.css` (no `-default` suffix); everything else is
 * `prism-<name>.min.css`.
 */
function prismUrl( name ) {
	const filename = name === 'default' ? 'prism.min.css' : `prism-${ name }.min.css`;
	return `https://cdn.jsdelivr.net/npm/prismjs@${ PRISM_VERSION }/themes/${ filename }`;
}

/**
 * Inject (or update) a stylesheet `<link>` with the given id and href. Idempotent —
 * the same call swaps the href on an existing link rather than appending a
 * duplicate, so memory and head clutter stay constant across theme switches.
 */
function setLink( id, href ) {
	let link = document.getElementById( id );
	if ( ! link ) {
		link = document.createElement( 'link' );
		link.id = id;
		link.rel = 'stylesheet';
		document.head.appendChild( link );
	}
	if ( link.href !== href ) {
		link.href = href;
	}
}

/**
 * Apply a theme by key. Falls back to the default if the key is unknown.
 *
 * @param {string} key
 */
function applyTheme( key ) {
	const theme = THEMES[ key ] || THEMES.tomorrow;
	setLink( PRISM_LINK_ID, prismUrl( theme.prism ) );
	document.body.setAttribute( 'data-codelag-theme', theme.dark ? 'dark' : 'light' );
	document.body.setAttribute( 'data-codelag-theme-key', key );
}

/**
 * Read the persisted theme from localStorage, validating it against the map.
 */
function readPersistedTheme() {
	try {
		const saved = window.localStorage.getItem( STORAGE_KEY );
		if ( saved && THEMES[ saved ] ) {
			return saved;
		}
	} catch ( err ) {
		// localStorage may be disabled in some privacy modes — ignore.
	}
	return null;
}

function persistTheme( key ) {
	try {
		window.localStorage.setItem( STORAGE_KEY, key );
	} catch ( err ) {
		// ignore
	}
}

/**
 * Wire a single theme picker `<select>`. Sets the option matching the
 * persisted choice on init, then listens for changes.
 */
function wireThemePicker( select ) {
	const persisted = readPersistedTheme();
	if ( persisted ) {
		select.value = persisted;
	}

	select.addEventListener( 'change', () => {
		const key = select.value;
		applyTheme( key );
		persistTheme( key );
	} );
}

/**
 * Wire a Share button. On click, copies the current page URL to the clipboard
 * via the Clipboard API and briefly swaps the label to "Copied!".
 *
 * @param {HTMLButtonElement} button
 */
function wireShareButton( button ) {
	const labelDefault = button.querySelector( '[data-codelag-share-default]' );
	const labelSuccess = button.querySelector( '[data-codelag-share-success]' );

	button.addEventListener( 'click', async () => {
		const url = window.location.href;
		try {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				await navigator.clipboard.writeText( url );
			} else {
				// Fallback for older browsers / non-secure contexts.
				const tmp = document.createElement( 'textarea' );
				tmp.value = url;
				tmp.setAttribute( 'readonly', '' );
				tmp.style.position = 'absolute';
				tmp.style.left = '-9999px';
				document.body.appendChild( tmp );
				tmp.select();
				document.execCommand( 'copy' );
				document.body.removeChild( tmp );
			}
			if ( labelDefault && labelSuccess ) {
				labelDefault.setAttribute( 'hidden', '' );
				labelSuccess.removeAttribute( 'hidden' );
				window.setTimeout( () => {
					labelSuccess.setAttribute( 'hidden', '' );
					labelDefault.removeAttribute( 'hidden' );
				}, COPY_RESET_MS );
			}
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( '[lagoon-viewer] share copy failed', err );
		}
	} );
}

function init() {
	const viewers = document.querySelectorAll( '.wp-block-codelag-lagoon-viewer' );
	viewers.forEach( ( viewer ) => {
		highlightAll( viewer );
		viewer.querySelectorAll( '[data-codelag-copy]' ).forEach( wireCopyButton );
		viewer.querySelectorAll( '[data-codelag-md-toggle]' ).forEach( wireMarkdownToggle );
	} );

	// Fork buttons live OUTSIDE the lagoon-viewer wrapper (in the singular
	// template's header card), so they're queried at document level rather
	// than scoped per viewer.
	document.querySelectorAll( '[data-codelag-fork-lagoon]' ).forEach( wireForkButton );
	document.querySelectorAll( '[data-codelag-share]' ).forEach( wireShareButton );

	// Restore theme as early as possible to minimise FOUC, then wire the
	// pickers so the user can switch.
	const persistedTheme = readPersistedTheme();
	if ( persistedTheme ) {
		applyTheme( persistedTheme );
	}
	document.querySelectorAll( '[data-codelag-theme-picker]' ).forEach( wireThemePicker );

	// The theme-panel.js module (loaded globally by the theme for logged-in
	// users) fires a `codelag:syntax-theme` custom event when the user picks
	// a different code-syntax theme from the floating panel. Apply it so
	// Prism re-themes instantly, and mirror the choice into localStorage so
	// cross-page navigation keeps the choice even for anonymous browsing.
	document.addEventListener( 'codelag:syntax-theme', ( event ) => {
		const key = event && event.detail ? event.detail.key : null;
		if ( typeof key === 'string' && THEMES[ key ] ) {
			applyTheme( key );
			persistTheme( key );
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
