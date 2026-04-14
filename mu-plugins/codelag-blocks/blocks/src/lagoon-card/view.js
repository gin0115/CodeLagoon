/**
 * Lagoon Card — frontend interactivity.
 *
 * Wires the Fork button in each card. The markup matches the existing
 * ForkButtonShortcode (same data attributes), so on lagoon singular pages
 * this script is redundant — but on archive / taxonomy pages where no
 * other fork wiring exists, this is the only handler.
 */

( function () {
	'use strict';

	const COPY_RESET_MS = 2000;

	function init() {
		document
			.querySelectorAll( '[data-codelag-fork-lagoon]' )
			.forEach( wireForkButton );
	}

	function wireForkButton( button ) {
		if ( button.dataset.codelagForkWired === '1' ) {
			return;
		}
		button.dataset.codelagForkWired = '1';

		const lagoonId = button.getAttribute( 'data-lagoon-id' );
		const nonce = button.getAttribute( 'data-fork-nonce' );
		const restRoot = button.getAttribute( 'data-fork-rest-root' );
		if ( ! lagoonId || ! nonce || ! restRoot ) {
			return;
		}

		const labelDefault = button.querySelector(
			'[data-codelag-fork-default]'
		);
		const labelBusy = button.querySelector( '[data-codelag-fork-busy]' );

		button.addEventListener( 'click', function () {
			if ( button.disabled ) {
				return;
			}
			button.disabled = true;
			if ( labelDefault && labelBusy ) {
				labelDefault.setAttribute( 'hidden', '' );
				labelBusy.removeAttribute( 'hidden' );
			}

			const url =
				restRoot.replace( /\/$/, '' ) +
				'/codelag/v1/lagoons/' +
				encodeURIComponent( lagoonId ) +
				'/fork';

			fetch( url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						return response
							.json()
							.catch( function () {
								return {};
							} )
							.then( function ( body ) {
								throw new Error(
									body && body.message
										? body.message
										: 'Fork request failed (' +
										  response.status +
										  ').'
								);
							} );
					}
					return response.json();
				} )
				.then( function ( data ) {
					if ( data && data.edit_link ) {
						window.location.assign( data.edit_link );
					} else {
						throw new Error( 'Fork response missing edit_link.' );
					}
				} )
				.catch( function ( err ) {
					// eslint-disable-next-line no-console
					console.error( '[lagoon-card] fork failed', err );
					button.disabled = false;
					if ( labelDefault && labelBusy ) {
						labelBusy.setAttribute( 'hidden', '' );
						labelDefault.removeAttribute( 'hidden' );
					}
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Re-wire after client-side grid swaps from the filters block.
	document.addEventListener( 'codelag:grid-updated', init );

	// Currently unused — kept for future toast / flash behaviour.
	void COPY_RESET_MS;
} )();
