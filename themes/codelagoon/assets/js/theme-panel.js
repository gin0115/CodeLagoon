/**
 * Floating theme-picker panel.
 *
 * Plain-JS module (no build step) that:
 *  - toggles a side popout panel open/closed
 *  - lets the user flip the panel between left and right edges (position
 *    persisted in localStorage under `codelag-theme-panel-side`)
 *  - swaps `data-site-theme` on <html> live when the site-theme select
 *    changes, then PATCHes the user meta so the choice survives reloads
 *  - does the same for the code-syntax select, which only matters on
 *    lagoon pages where view.js listens for the change and re-applies
 *    Prism themes — we just fire a custom event so view.js can react
 *    without this script having to know how it works
 *
 * This script is loaded in the footer and is only enqueued for logged-in
 * users; the corresponding markup is printed by codelag_render_theme_panel().
 */

( function () {
	'use strict';

	var ROOT_ATTR    = 'data-site-theme';
	var SIDE_STORE   = 'codelag-theme-panel-side';
	var DEFAULT_SIDE = 'right';

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		var panel = document.querySelector( '[data-codelag-theme-panel]' );
		if ( ! panel ) {
			return;
		}

		applyInitialSide( panel );
		wireToggle( panel );
		wireClose( panel );
		wireSideSwap( panel );
		wireSiteThemeSelect( panel );
		wireSyntaxThemeSelect( panel );
		wireColumnsToggle( panel );
	}

	/**
	 * Restore the persisted left/right side on load.
	 */
	function applyInitialSide( panel ) {
		var side = DEFAULT_SIDE;
		try {
			var stored = window.localStorage.getItem( SIDE_STORE );
			if ( stored === 'left' || stored === 'right' ) {
				side = stored;
			}
		} catch ( err ) {
			/* localStorage unavailable — fall back to default */
		}
		panel.setAttribute( 'data-side', side );
	}

	function wireToggle( panel ) {
		var toggle = panel.querySelector( '[data-codelag-theme-toggle]' );
		var body   = panel.querySelector( '[data-codelag-theme-body]' );
		if ( ! toggle || ! body ) {
			return;
		}
		toggle.addEventListener( 'click', function () {
			var open = body.hasAttribute( 'hidden' );
			if ( open ) {
				body.removeAttribute( 'hidden' );
				toggle.setAttribute( 'aria-expanded', 'true' );
			} else {
				body.setAttribute( 'hidden', '' );
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	function wireClose( panel ) {
		var close  = panel.querySelector( '[data-codelag-theme-close]' );
		var toggle = panel.querySelector( '[data-codelag-theme-toggle]' );
		var body   = panel.querySelector( '[data-codelag-theme-body]' );
		if ( ! close || ! body ) {
			return;
		}
		close.addEventListener( 'click', function () {
			body.setAttribute( 'hidden', '' );
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	function wireSideSwap( panel ) {
		var btn = panel.querySelector( '[data-codelag-theme-side]' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var side = panel.getAttribute( 'data-side' ) === 'left' ? 'right' : 'left';
			panel.setAttribute( 'data-side', side );
			try {
				window.localStorage.setItem( SIDE_STORE, side );
			} catch ( err ) {
				/* ignore */
			}
		} );
	}

	function wireSiteThemeSelect( panel ) {
		var select = panel.querySelector( '[data-codelag-site-theme]' );
		var metaKey = panel.getAttribute( 'data-site-meta-key' );
		if ( ! select || ! metaKey ) {
			return;
		}
		select.addEventListener( 'change', function () {
			var value = select.value;
			document.documentElement.setAttribute( ROOT_ATTR, value );
			saveMeta( panel, metaKey, value );
		} );
	}

	function wireSyntaxThemeSelect( panel ) {
		var select = panel.querySelector( '[data-codelag-syntax-theme]' );
		var metaKey = panel.getAttribute( 'data-syntax-meta-key' );
		if ( ! select || ! metaKey ) {
			return;
		}
		select.addEventListener( 'change', function () {
			var value = select.value;
			// Notify any listeners (view.js on lagoon pages applies Prism themes
			// when it sees this event) so the preview updates immediately.
			document.dispatchEvent(
				new CustomEvent( 'codelag:syntax-theme', { detail: { key: value } } )
			);
			saveMeta( panel, metaKey, value );
		} );
	}

	/**
	 * Wire the 1-col / 2-col button pair. Clicking swaps `data-codelag-cols`
	 * on <html> live (so CSS column rules apply immediately) and PATCHes
	 * the user's meta so the choice survives reloads.
	 */
	function wireColumnsToggle( panel ) {
		var buttons = panel.querySelectorAll( '[data-codelag-columns]' );
		var metaKey = panel.getAttribute( 'data-columns-meta-key' );
		if ( ! buttons.length || ! metaKey ) {
			return;
		}

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var value = btn.getAttribute( 'data-codelag-columns' );
				if ( value !== '1' && value !== '2' ) {
					return;
				}

				buttons.forEach( function ( other ) {
					other.setAttribute(
						'aria-pressed',
						other === btn ? 'true' : 'false'
					);
				} );

				document.documentElement.setAttribute( 'data-codelag-cols', value );
				saveMeta( panel, metaKey, parseInt( value, 10 ) );
			} );
		} );
	}

	/**
	 * PATCH a single user-meta key on the current user via wp/v2/users/me.
	 */
	function saveMeta( panel, metaKey, value ) {
		var root  = panel.getAttribute( 'data-rest-root' );
		var nonce = panel.getAttribute( 'data-nonce' );
		if ( ! root || ! nonce ) {
			return;
		}
		var payload = { meta: {} };
		payload.meta[ metaKey ] = value;

		fetch( root.replace( /\/$/, '' ) + '/wp/v2/users/me', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify( payload ),
		} ).catch( function ( err ) {
			// eslint-disable-next-line no-console
			console.error( '[codelag-theme-panel] save failed', err );
		} );
	}
} )();
