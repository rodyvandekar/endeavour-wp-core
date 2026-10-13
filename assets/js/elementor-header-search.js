/**
 * Header search toggle.
 *
 * Visibility is switched with Elementor's own `elementor-hidden-{device}`
 * classes, so the breakpoints stay owned by Site Settings and no stylesheet
 * has to repeat them. The search container ships hidden on every device
 * through its Responsive controls; this script only adds and removes those
 * same classes.
 */
( function () {
	'use strict';

	const HEADER_SELECTOR = '.endeavour-header';
	const PANEL_SELECTOR = '.endeavour-search-panel';
	const HIDE_SELECTOR = '.endeavour-search-hide';
	const HIDE_COMPACT_SELECTOR = '.endeavour-search-hide-compact';
	const OPEN_SELECTOR = '[data-endeavour-search-open]';
	const CLOSE_SELECTOR = '[data-endeavour-search-close]';

	const openerByHeader = new WeakMap();

	function getBreakpointKeys( direction ) {
		const config = window.elementorFrontendConfig;
		const breakpoints = config && config.responsive && config.responsive.activeBreakpoints;

		if ( ! breakpoints ) {
			return 'max' === direction ? [ 'tablet', 'mobile' ] : [];
		}

		return Object.keys( breakpoints ).filter( function ( key ) {
			return breakpoints[ key ].is_enabled && direction === breakpoints[ key ].direction;
		} );
	}

	function hiddenClasses( includeDesktop ) {
		const keys = getBreakpointKeys( 'max' );

		if ( includeDesktop ) {
			keys.push( 'desktop', ...getBreakpointKeys( 'min' ) );
		}

		return keys.map( function ( key ) {
			return 'elementor-hidden-' + key;
		} );
	}

	function toggleClasses( elements, classes, shouldHide ) {
		elements.forEach( function ( element ) {
			if ( shouldHide ) {
				element.classList.add( ...classes );
			} else {
				element.classList.remove( ...classes );
			}
		} );
	}

	function focusSearchInput( panel ) {
		window.requestAnimationFrame( function () {
			const input = Array.from( panel.querySelectorAll( '.e-search-input' ) ).find( function ( candidate ) {
				return candidate.getClientRects().length > 0;
			} );

			if ( input ) {
				input.focus();
			}
		} );
	}

	function setSearchState( header, isOpen ) {
		const panel = header.querySelector( PANEL_SELECTOR );

		if ( ! panel ) {
			return;
		}

		const everywhere = hiddenClasses( true );

		toggleClasses( [ panel ], everywhere, ! isOpen );
		toggleClasses( Array.from( header.querySelectorAll( HIDE_SELECTOR ) ), everywhere, isOpen );
		toggleClasses( Array.from( header.querySelectorAll( HIDE_COMPACT_SELECTOR ) ), hiddenClasses( false ), isOpen );

		header.querySelectorAll( OPEN_SELECTOR ).forEach( function ( trigger ) {
			trigger.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );

		if ( isOpen ) {
			focusSearchInput( panel );

			return;
		}

		const opener = openerByHeader.get( header );

		if ( opener && opener.isConnected ) {
			opener.focus();
		}
	}

	function getHeader( element ) {
		return element && 'function' === typeof element.closest ? element.closest( HEADER_SELECTOR ) : null;
	}

	document.addEventListener( 'click', function ( event ) {
		const trigger = event.target.closest( OPEN_SELECTOR + ', ' + CLOSE_SELECTOR );

		if ( ! trigger ) {
			return;
		}

		const header = getHeader( trigger );

		if ( ! header ) {
			return;
		}

		event.preventDefault();

		const isOpening = trigger.matches( OPEN_SELECTOR );

		if ( isOpening ) {
			openerByHeader.set( header, trigger );
		}

		setSearchState( header, isOpening );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}

		const header = getHeader( event.target ) || Array.from( document.querySelectorAll( HEADER_SELECTOR ) ).find( function ( candidate ) {
			const panel = candidate.querySelector( PANEL_SELECTOR );

			return panel && panel.getClientRects().length > 0;
		} );

		if ( ! header ) {
			return;
		}

		const panel = header.querySelector( PANEL_SELECTOR );

		if ( panel && panel.getClientRects().length > 0 ) {
			event.preventDefault();
			setSearchState( header, false );
		}
	} );
}() );
