/**
 * Recent searches and the hand-off to Elementor's live results.
 *
 * Configured sections are rendered with the widget. Recent searches live in
 * localStorage because they belong to the visitor, not the site. Once the
 * field reaches the widget's minimum length, the suggestions step aside and
 * Elementor's own search handler fills the same dropdown.
 */
( function () {
	'use strict';

	const STORAGE_KEY = 'endeavour-recent-searches';
	const STORED_LIMIT = 8;

	function readRecent() {
		try {
			const parsed = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || '[]' );

			return Array.isArray( parsed ) ? parsed.filter( function ( item ) {
				return item && 'string' === typeof item.label && item.label.trim();
			} ) : [];
		} catch ( error ) {
			return [];
		}
	}

	function writeRecent( items ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( items.slice( 0, STORED_LIMIT ) ) );
		} catch ( error ) {
			// A full or blocked store only means the recent list stays as it was.
		}
	}

	function remember( label, url ) {
		const text = ( label || '' ).replace( /\s+/g, ' ' ).trim();

		if ( ! text ) {
			return;
		}

		const items = readRecent().filter( function ( item ) {
			return item.label.toLowerCase() !== text.toLowerCase();
		} );

		items.unshift( {
			label: text,
			url: url || '',
		} );

		writeRecent( items );
	}

	function searchUrl( term ) {
		const url = new URL( window.location.origin + '/' );

		url.searchParams.set( 's', term );

		return url.toString();
	}

	function renderRecent( panel ) {
		const section = panel.querySelector( '[data-recent]' );

		if ( ! section ) {
			return;
		}

		const list = section.querySelector( '.endeavour-search-suggestions__list' );
		const limit = Number( panel.dataset.recentLimit ) || 3;
		const items = readRecent().slice( 0, limit );

		list.replaceChildren();

		items.forEach( function ( item ) {
			const row = document.createElement( 'li' );
			const link = document.createElement( 'a' );

			link.href = item.url || searchUrl( item.label );
			link.textContent = item.label;
			row.append( link );
			list.append( row );
		} );

		section.hidden = 0 === items.length;
	}

	function renderAll() {
		document.querySelectorAll( '.endeavour-search-suggestions' ).forEach( renderRecent );
	}

	function isTyping( widget ) {
		const input = widget.querySelector( '.e-search-input' );
		const suggestions = widget.querySelector( '.endeavour-search-suggestions' );

		if ( ! input || ! suggestions ) {
			return false;
		}

		const minimum = Number( suggestions.dataset.minimum ) || 3;

		return input.value.trim().length >= minimum;
	}

	function syncWidget( widget ) {
		if ( ! widget ) {
			return;
		}

		const suggestions = widget.querySelector( '.endeavour-search-suggestions' );
		const dropdown = widget.querySelector( '.e-search-results-container' );

		if ( ! suggestions ) {
			return;
		}

		const typing = isTyping( widget );

		suggestions.hidden = typing;

		// Elementor hides the dropdown when the opening click lands outside the
		// widget. Before a query is long enough, the suggestions are that dropdown.
		if ( dropdown && ! typing ) {
			dropdown.classList.remove( 'hidden' );
		}
	}

	function widgetFrom( element ) {
		return element && 'function' === typeof element.closest
			? element.closest( '.elementor-widget-search' )
			: null;
	}

	document.addEventListener( 'input', function ( event ) {
		if ( ! event.target.classList || ! event.target.classList.contains( 'e-search-input' ) ) {
			return;
		}

		syncWidget( widgetFrom( event.target ) );
	} );

	document.addEventListener( 'focusin', function ( event ) {
		if ( ! event.target.classList || ! event.target.classList.contains( 'e-search-input' ) ) {
			return;
		}

		syncWidget( widgetFrom( event.target ) );
	} );

	document.addEventListener( 'click', function ( event ) {
		const opener = event.target.closest( '[data-endeavour-search-open]' );

		if ( ! opener ) {
			return;
		}

		const header = opener.closest( '.endeavour-header' );
		const widget = header && header.querySelector( '.elementor-widget-search' );

		window.requestAnimationFrame( function () {
			syncWidget( widget );
		} );
	} );

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target;

		if ( ! form.classList || ! form.classList.contains( 'e-search-form' ) ) {
			return;
		}

		const input = form.querySelector( '.e-search-input' );
		const term = input ? input.value.trim() : '';

		if ( ! term ) {
			return;
		}

		remember( term, searchUrl( term ) );
		renderAll();
	} );

	document.addEventListener( 'click', function ( event ) {
		const clear = event.target.closest( '.e-search-input-wrapper > i, .e-search-input-wrapper > svg' );

		if ( clear ) {
			window.requestAnimationFrame( function () {
				syncWidget( widgetFrom( clear ) );
			} );

			return;
		}

		const recent = event.target.closest( '.endeavour-search-suggestions [data-recent] a' );

		if ( recent ) {
			const widget = widgetFrom( recent );
			const input = widget && widget.querySelector( '.e-search-input' );

			if ( ! input ) {
				return;
			}

			event.preventDefault();
			input.value = recent.textContent.trim();
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			input.focus();

			return;
		}

		const result = event.target.closest( '.e-search-results a[href], .endeavour-search-suggestions a[href]' );

		if ( result ) {
			remember( result.textContent, result.href );
			renderAll();
		}
	} );

	function boot() {
		renderAll();

		if ( window.elementorFrontend && elementorFrontend.hooks ) {
			elementorFrontend.hooks.addAction( 'search:results-updated', function () {
				document.querySelectorAll( '.elementor-widget-search' ).forEach( syncWidget );
			} );
		}
	}

	if ( window.elementorFrontend && elementorFrontend.hooks ) {
		boot();
	} else {
		window.addEventListener( 'elementor/frontend/init', boot );
	}
}() );
