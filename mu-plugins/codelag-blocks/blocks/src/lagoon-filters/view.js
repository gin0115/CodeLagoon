/**
 * Lagoon Filters — frontend progressive enhancement.
 *
 * Baseline: the block renders a plain GET form that posts back to the
 * archive URL. With JS enabled, we intercept submits + input changes,
 * fetch `/wp-json/codelag/v1/lagoons?<qs>`, and swap the card grid in
 * place. URL is updated via history.pushState so the page stays
 * bookmarkable.
 *
 * We expect a sibling element marked `[data-codelag-grid]` elsewhere on
 * the page (the archive template's Query Loop wrapper). The cards inside
 * are re-rendered from REST JSON using a template function that mirrors
 * the lagoon-card/render.php markup.
 */

( function () {
	'use strict';

	var DEBOUNCE_MS = 250;

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		document.querySelectorAll( 'form[data-codelag-filters]' ).forEach( wireForm );
	}

	function wireForm( form ) {
		var grid = document.querySelector( '[data-codelag-grid]' );
		if ( ! grid ) {
			return;
		}

		initSelect2( form );

		var search = form.querySelector( 'input[name="search"]' );
		if ( search ) {
			var timer = null;
			search.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					submit( form, grid );
				}, DEBOUNCE_MS );
			} );
		}

		// select2 fires jQuery `change` events rather than native ones, so a
		// plain `addEventListener('change')` misses chip adds/removes on the
		// language / purpose / tag multi-selects. Route through jQuery when
		// it's present (always, when select2 is active) and fall back to
		// native listeners for the plain-HTML path.
		if ( window.jQuery ) {
			window.jQuery( form ).find( 'select' ).on( 'change.codelag', function () {
				submit( form, grid );
			} );
		} else {
			form.querySelectorAll( 'select' ).forEach( function ( select ) {
				select.addEventListener( 'change', function () {
					submit( form, grid );
				} );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submit( form, grid );
		} );

		window.addEventListener( 'popstate', function () {
			// Re-sync form from URL, then refetch.
			var params = new URLSearchParams( window.location.search );
			syncFormFromParams( form, params );
			submit( form, grid );
		} );
	}

	/**
	 * Upgrade the plain <select multiple> boxes (language / purpose / tag)
	 * into select2 chip pickers. Gracefully no-ops when jQuery / select2
	 * aren't available — the plain selects remain fully functional.
	 *
	 * Each select2 widget is wired to trigger a native `change` event on
	 * its source <select>, which our existing form.addEventListener picks
	 * up and runs the debounce/fetch pipeline. No extra event plumbing
	 * needed.
	 */
	function initSelect2( form ) {
		var $ = window.jQuery;
		if ( ! $ || ! $.fn || typeof $.fn.select2 !== 'function' ) {
			return;
		}

		form.querySelectorAll( 'select[multiple]' ).forEach( function ( select ) {
			if ( select.dataset.codelagSelect2Ready === '1' ) {
				return;
			}
			select.dataset.codelagSelect2Ready = '1';

			var label = select.closest( '.codelag-filters__slot' );
			var labelText = label ? ( label.querySelector( '.codelag-filters__label' ) || {} ).textContent : '';

			$( select ).select2( {
				placeholder: labelText ? 'Any ' + labelText.trim().toLowerCase() : 'Any',
				allowClear: true,
				// `style` = "grow to fit the chips on one line"; avoids
				// select2's default 100% / resolve-style behaviour that
				// pins the widget at a fixed px width.
				width: 'style',
				dropdownParent: $( form ),
				closeOnSelect: false,
			} );
		} );
	}

	/**
	 * Show or hide the "Clear" button based on whether the form currently
	 * carries any active filter. Server-rendered Clear state is only right
	 * at page load; after a JS-driven submit the URL has new params but the
	 * markup is stale, so we resync from the current form state.
	 */
	/**
	 * Keep the server-rendered pagination honest under JS filtering. We
	 * can't cheaply re-render the numbered links from REST data, so:
	 *   - More than one page total → leave pagination visible (page numbers
	 *     may be stale, but at least the "next" affordance is correct).
	 *   - Single page / no results → hide the pagination block so users
	 *     don't see stale page counts against zero (or one) results.
	 * A full page reload of the new filter URL re-renders pagination
	 * correctly, so the stale-number state is short-lived.
	 */
	function syncPagination( grid, totalPages ) {
		var pagination = grid.querySelector( '.wp-block-query-pagination' );
		if ( ! pagination ) {
			return;
		}
		pagination.style.display = totalPages > 1 ? '' : 'none';
	}

	function syncClearButton( form ) {
		var clear = form.querySelector( '[data-codelag-filters-clear]' );
		if ( ! clear ) {
			return;
		}
		// Toggle via inline style rather than the [hidden] attribute —
		// the block's stylesheet sets `display: inline-flex` on the
		// clear pill, which wins over `[hidden]`'s `display: none`.
		var params = formToParams( form );
		clear.style.display = 0 === params.toString().length ? 'none' : '';
	}

	function submit( form, grid ) {
		syncClearButton( form );

		var params = formToParams( form );
		var qs     = params.toString();

		grid.setAttribute( 'aria-busy', 'true' );

		// Author-archive scoping: when rendering on /snippets/by/<name>/,
		// the form carries `data-codelag-author="<id>"`. Append it to the
		// REST fetch URL only — not to `qs` — so the visible URL stays
		// clean (/snippets/by/<name>/?tag=…) while REST still scopes to
		// the author.
		var fetchParams = new URLSearchParams( qs );
		var scopedAuthor = form.getAttribute( 'data-codelag-author' );
		if ( scopedAuthor ) {
			fetchParams.set( 'author', scopedAuthor );
		}
		var fetchQs = fetchParams.toString();

		var restBase = getRestBase( grid );
		var url      = restBase + 'codelag/v1/lagoons' + ( fetchQs ? '?' + fetchQs : '' );

		// Send the REST nonce so WP recognises the caller as the logged-in
		// user — otherwise scoped responses (e.g. the collection view below)
		// see an anon caller and return zero results.
		var fetchHeaders = {};
		if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
			fetchHeaders['X-WP-Nonce'] = window.wpApiSettings.nonce;
		}

		// Collection-page marker: the grid wrapper carries
		// `data-codelag-collection="1"` on the My Collection template.
		// Forward it to REST as a header so the endpoint can scope to the
		// caller's saved lagoons without us polluting the URL with a
		// `collection=1` query arg.
		if ( '1' === ( grid.getAttribute( 'data-codelag-collection' ) || '' ) ) {
			fetchHeaders['X-Codelag-Collection'] = '1';
		}

		fetch( url, { credentials: 'same-origin', headers: fetchHeaders } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'REST ' + response.status );
				}
				var totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ) || '1', 10 );
				return response.json().then( function ( items ) {
					return { items: items, totalPages: totalPages };
				} );
			} )
			.then( function ( result ) {
				var items = result.items;
				renderCards( grid, items );
				syncPagination( grid, result.totalPages );
				grid.setAttribute( 'aria-busy', 'false' );

				var nextUrl = window.location.pathname + ( qs ? '?' + qs : '' );
				if ( window.location.pathname + window.location.search !== nextUrl ) {
					window.history.pushState( {}, '', nextUrl );
				}

				document.dispatchEvent( new CustomEvent( 'codelag:grid-updated', { detail: { count: items.length } } ) );
			} )
			.catch( function ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[lagoon-filters] fetch failed', err );
				grid.setAttribute( 'aria-busy', 'false' );
			} );
	}

	/**
	 * Derive the REST root from the page. Prefer `wpApiSettings.root` when
	 * wp-api-fetch is enqueued, otherwise fall back to `/wp-json/`.
	 */
	function getRestBase( /* grid */ ) {
		if ( window.wpApiSettings && window.wpApiSettings.root ) {
			return window.wpApiSettings.root;
		}
		return window.location.origin + '/wp-json/';
	}

	function formToParams( form ) {
		var data   = new FormData( form );
		var params = new URLSearchParams();

		data.forEach( function ( value, key ) {
			if ( typeof value === 'string' && value === '' ) {
				return;
			}
			if ( key === 'date_range' && value === 'any' ) {
				return;
			}
			params.append( key, value );
		} );

		return params;
	}

	function syncFormFromParams( form, params ) {
		var search = form.querySelector( 'input[name="search"]' );
		if ( search ) {
			search.value = params.get( 'search' ) || '';
		}

		// date_range remains a single-select.
		var dateSel = form.querySelector( 'select[name="date_range"]' );
		if ( dateSel ) {
			dateSel.value = params.get( 'date_range' ) || 'any';
		}

		// language / purpose / tag are all multi-selects with name="X[]".
		// Param names are prefixed with the taxonomy name to avoid colliding
		// with WP's reserved core query vars (notably `tag`).
		[ 'filter_language', 'filter_purpose', 'filter_tag' ].forEach( function ( name ) {
			var sel = form.querySelector( 'select[name="' + name + '[]"]' );
			if ( ! sel ) {
				return;
			}
			var values = params.getAll( name + '[]' ).concat( params.getAll( name ) );
			Array.from( sel.options ).forEach( function ( option ) {
				option.selected = values.indexOf( option.value ) !== -1;
			} );
		} );
	}

	function renderCards( grid, items ) {
		var list = grid.querySelector( '.wp-block-post-template' ) || grid;
		if ( ! items || items.length === 0 ) {
			list.innerHTML = '<p class="codelag-archive__empty">' + escape( 'No snippets match those filters.' ) + '</p>';
			return;
		}
		list.innerHTML = items.map( cardHtml ).join( '' );
	}

	/**
	 * Client-side mirror of lagoon-card/render.php output. Kept intentionally
	 * close to the server template so swaps don't flicker visually.
	 */
	function cardHtml( item ) {
		var title      = item.title ? escape( item.title ) : escape( item.slug || '' );
		var permalink  = escape( item.link || '#' );
		var author     = item.author && item.author.username ? escape( item.author.username ) : '';
		var authorUrl  = item.author && item.author.archive_url ? escape( item.author.archive_url ) : '#';
		var authorDisp = author ? '<a class="codelag-card__author" href="' + authorUrl + '">@' + author + '</a>' : '';
		var ago        = relativeTime( item.date );
		var langName   = item.languages && item.languages[0] ? escape( item.languages[0].name ) : '';
		var firstFile  = item.files && item.files[0] ? item.files[0] : null;
		var preview    = firstFile ? escape( ( firstFile.content || '' ).split( '\n' ).slice( 0, 6 ).join( '\n' ) ) : '';
		var tags       = ( item.tags || [] ).slice( 0, 4 ).map( function ( t ) {
			return '<li class="codelag-chip codelag-chip--tag">#' + escape( t.name ) + '</li>';
		} ).join( '' );

		// Wrap in <li class="wp-block-post"> to match core/post-template's
		// server-rendered structure. The grid layout CSS the block emits
		// (`.is-layout-grid > li`) only positions direct `<li>` children;
		// bare `<article>` children fall out of the grid and render unstyled.
		return ''
			+ '<li class="wp-block-post codelag-archive__card">'
			+ '<article class="wp-block-codelag-lagoon-card codelag-card" data-lagoon-id="' + escape( item.id ) + '">'
			+   '<header class="codelag-card__head">'
			+     '<div class="codelag-card__avatar" aria-hidden="true"></div>'
			+     '<div class="codelag-card__ident">'
			+       '<a class="codelag-card__title" href="' + permalink + '">' + title + '</a>'
			+       '<p class="codelag-card__meta">'
			+         ( authorDisp ? 'by ' + authorDisp + ' · ' : '' )
			+         escape( ago )
			+       '</p>'
			+     '</div>'
			+     ( langName ? '<span class="codelag-chip codelag-chip--lang">' + langName + '</span>' : '' )
			+   '</header>'
			+   ( preview ? '<pre class="codelag-card__preview"><code>' + preview + '</code></pre>' : '' )
			+   ( tags ? '<ul class="codelag-card__tags">' + tags + '</ul>' : '' )
			+   '<footer class="codelag-card__actions">'
			+     '<a class="codelag-card__action codelag-card__action--ghost" href="' + permalink + '">'
			+       '<span class="material-symbols-outlined" aria-hidden="true">visibility</span>'
			+       '<span>View</span>'
			+     '</a>'
			+   '</footer>'
			+ '</article>'
			+ '</li>';
	}

	function escape( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	function relativeTime( iso ) {
		if ( ! iso ) {
			return '';
		}
		var then = new Date( iso );
		var diff = Math.max( 0, Math.floor( ( Date.now() - then.getTime() ) / 1000 ) );
		if ( diff < 60 ) { return diff + 's ago'; }
		if ( diff < 3600 ) { return Math.floor( diff / 60 ) + 'm ago'; }
		if ( diff < 86400 ) { return Math.floor( diff / 3600 ) + 'h ago'; }
		if ( diff < 2592000 ) { return Math.floor( diff / 86400 ) + 'd ago'; }
		return then.toLocaleDateString();
	}
} )();
