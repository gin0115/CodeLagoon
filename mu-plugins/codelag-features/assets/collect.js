/**
 * Collect-button toggle for lagoons.
 *
 * Wires every `[data-codelag-collect]` button to POST /collect when not yet
 * collected and DELETE /collect when already collected. Flips the button's
 * `data-collected`, swaps the label spans, and updates `aria-pressed`.
 *
 * Used on single-lagoon pages. Unstarring on the My Collection archive is
 * done by visiting the lagoon and clicking the button there — the archive
 * itself uses the standard lagoon-card block, so no per-card wiring lives
 * here.
 */
( function () {
	'use strict';

	function endpoint( button ) {
		const root = button.getAttribute( 'data-collect-rest-root' ) || '/wp-json/';
		const id = parseInt( button.getAttribute( 'data-lagoon-id' ) || '0', 10 );
		if ( ! id ) {
			return '';
		}
		return root.replace( /\/+$/, '' ) + '/codelag/v1/lagoons/' + id + '/collect';
	}

	async function request( button, method ) {
		const url = endpoint( button );
		if ( '' === url ) {
			return null;
		}
		const nonce = button.getAttribute( 'data-collect-nonce' ) || '';
		const res = await fetch( url, {
			method: method,
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': nonce,
				'Accept': 'application/json',
			},
		} );
		if ( ! res.ok ) {
			return null;
		}
		return res.json();
	}

	function setState( button, collected ) {
		button.setAttribute( 'data-collected', collected ? 'true' : 'false' );
		button.setAttribute( 'aria-pressed', collected ? 'true' : 'false' );
		const def = button.querySelector( '[data-codelag-collect-default]' );
		const act = button.querySelector( '[data-codelag-collect-active]' );
		if ( def ) {
			def.hidden = collected;
		}
		if ( act ) {
			act.hidden = ! collected;
		}
	}

	function wireToggle( button ) {
		button.addEventListener( 'click', async () => {
			if ( button.disabled ) {
				return;
			}
			button.disabled = true;
			const currentlyCollected = 'true' === button.getAttribute( 'data-collected' );
			const method = currentlyCollected ? 'DELETE' : 'POST';
			const result = await request( button, method );
			if ( result && typeof result.collected === 'boolean' ) {
				setState( button, result.collected );
			}
			button.disabled = false;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-codelag-collect]' ).forEach( wireToggle );
	} );
}() );
