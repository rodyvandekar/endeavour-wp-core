/**
 * Endeavour Off-Canvas Menu
 *
 * Drives the drill-down in the `endeavour-offcanvas-menu` widget. The panel
 * itself belongs to Elementor Pro's Off-Canvas widget, which handles opening,
 * closing, the overlay and the focus trap; this only decides which level is
 * on screen.
 *
 * All positioning is CSS. This file moves two classes and keeps the
 * accessibility state honest.
 */
( function () {
	'use strict';

	var ROOT_SELECTOR = '[data-endeavour-offcanvas-menu]';
	var PANEL_SELECTOR = '.endeavour-offcanvas-menu__panel';

	/**
	 * Roughly the Off-Canvas close animation. Resetting to the first level
	 * only after it has slid away keeps the visitor from watching the menu
	 * rewind itself on the way out.
	 */
	var RESET_DELAY = 400;

	/**
	 * Walk from a panel up to the root, collecting ids.
	 *
	 * @param {Element} root  Widget wrapper.
	 * @param {Element} panel Panel to start from.
	 * @return {string[]} Ids, nearest first.
	 */
	function ancestry( root, panel ) {
		var chain = [];
		var current = panel;

		while ( current ) {
			chain.push( current.id );

			var parentId = current.getAttribute( 'data-parent' );
			current = parentId ? root.querySelector( '#' + parentId ) : null;
		}

		return chain;
	}

	/**
	 * Hide, or bring back, everything the editor placed beside the menu.
	 *
	 * A submenu is a screen of its own in the design, so the rest of the
	 * panel — the Contact button — steps aside until the visitor is back at
	 * the first level. The elements are found by walking up from the widget
	 * to the panel, so which ones exist stays an editor decision.
	 *
	 * @param {Element} root   Widget wrapper.
	 * @param {boolean} hidden Whether a submenu is on screen.
	 */
	function setAsidesHidden( root, hidden ) {
		var panel = root.closest( '.e-off-canvas__content' ) || root.closest( '.e-off-canvas' );
		var node = root;

		while ( panel && node && node !== panel && node.parentElement ) {
			var sibling = node;

			Array.prototype.forEach.call( node.parentElement.children, function ( child ) {
				if ( child !== sibling ) {
					child.toggleAttribute( 'data-endeavour-menu-hidden', hidden );
				}
			} );

			node = node.parentElement;
		}
	}

	/**
	 * Run a callback once a level has finished sliding.
	 *
	 * The duration is read off the element rather than repeated from the
	 * stylesheet, so a visitor on `prefers-reduced-motion`, where there is no
	 * transition at all, gets the callback straight away. The timer is a
	 * fallback for the case where `transitionend` never arrives, which is
	 * what happens when the panel is closed mid-slide.
	 *
	 * @param {Element}  panel    Level that is arriving.
	 * @param {Function} callback What to do once it has settled.
	 */
	function afterSlide( panel, callback ) {
		var durations = window.getComputedStyle( panel ).transitionDuration.split( ',' );
		var seconds = durations.reduce( function ( longest, value ) {
			return Math.max( longest, parseFloat( value ) || 0 );
		}, 0 );

		if ( ! seconds ) {
			callback();

			return;
		}

		var settle = function () {
			panel.removeEventListener( 'transitionend', onEnd );
			window.clearTimeout( timer );
			callback();
		};

		var onEnd = function ( event ) {
			if ( event.target === panel && 'transform' === event.propertyName ) {
				settle();
			}
		};

		var timer = window.setTimeout( settle, seconds * 1000 + 50 );

		panel.addEventListener( 'transitionend', onEnd );
	}

	/**
	 * Bring one level on screen and place every other level around it.
	 *
	 * @param {Element} root     Widget wrapper.
	 * @param {string}  targetId Id of the level to show.
	 * @return {Element|null} The level now on screen.
	 */
	function show( root, targetId ) {
		var target = targetId ? root.querySelector( '#' + targetId ) : null;

		if ( ! target ) {
			return null;
		}

		var chain = ancestry( root, target );

		Array.prototype.forEach.call( root.querySelectorAll( PANEL_SELECTOR ), function ( panel ) {
			var isCurrent = panel === target;

			panel.classList.toggle( 'is-current', isCurrent );

			// Everything else in the chain is a level already drilled through,
			// so it parks on the left. Untouched levels keep their default
			// position on the right.
			panel.classList.toggle( 'is-parent', ! isCurrent && chain.indexOf( panel.id ) !== -1 );

			// Off-screen levels are still in the layout, so take them out of
			// the tab order and the accessibility tree.
			panel.toggleAttribute( 'inert', ! isCurrent );
		} );

		Array.prototype.forEach.call( root.querySelectorAll( '[data-endeavour-menu-open]' ), function ( trigger ) {
			var opens = trigger.getAttribute( 'data-endeavour-menu-open' );

			trigger.setAttribute( 'aria-expanded', chain.indexOf( opens ) !== -1 ? 'true' : 'false' );
		} );

		if ( '0' !== target.getAttribute( 'data-level' ) ) {
			// Stepping in: the rest of the panel leaves before the submenu
			// arrives, so nothing lingers beside a level it does not belong
			// to.
			setAsidesHidden( root, true );
		} else {
			// Coming back: wait for the first level to be all the way in.
			// Anything sooner and the button lands next to a menu that is
			// still on its way, which reads as a second, competing movement.
			afterSlide( target, function () {
				// Unless they went back in while it was sliding.
				if ( target.classList.contains( 'is-current' ) ) {
					setAsidesHidden( root, false );
				}
			} );
		}

		return target;
	}

	/**
	 * Send focus somewhere sensible after a level change.
	 *
	 * @param {Element|null} element Element to focus, when it exists.
	 */
	function focusIfPossible( element ) {
		if ( element ) {
			element.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || typeof target.closest !== 'function' ) {
			return;
		}

		var opener = target.closest( '[data-endeavour-menu-open]' );

		if ( opener ) {
			var openerRoot = opener.closest( ROOT_SELECTOR );

			if ( ! openerRoot ) {
				return;
			}

			var opened = show( openerRoot, opener.getAttribute( 'data-endeavour-menu-open' ) );

			// Focus lands on the way out of the new level, which is both the
			// first control in it and the way back.
			if ( opened ) {
				focusIfPossible( opened.querySelector( '.endeavour-offcanvas-menu__back' ) );
			}

			return;
		}

		var back = target.closest( '[data-endeavour-menu-back]' );

		if ( ! back ) {
			return;
		}

		var backRoot = back.closest( ROOT_SELECTOR );
		var leaving = back.closest( PANEL_SELECTOR );

		if ( ! backRoot || ! leaving ) {
			return;
		}

		// Back is a link to the level being returned to. Now that the slide
		// is taking over, keep the browser from jumping to that level and
		// leaving the fragment behind in the address bar and the history.
		event.preventDefault();

		show( backRoot, back.getAttribute( 'data-endeavour-menu-back' ) );

		// Return focus to the row that opened the level being left, so the
		// visitor picks up where they were rather than at the top.
		focusIfPossible(
			backRoot.querySelector( '[data-endeavour-menu-open="' + leaving.id + '"]' )
		);
	} );

	/**
	 * Reset to the first level once the Off-Canvas has closed.
	 *
	 * Elementor flips `aria-hidden` on `.e-off-canvas` when it opens and
	 * closes, which is the only public signal the widget gives.
	 *
	 * @param {Element} root Widget wrapper.
	 */
	function resetOnClose( root ) {
		var offCanvas = root.closest( '.e-off-canvas' );

		if ( ! offCanvas || offCanvas.hasAttribute( 'data-endeavour-menu-bound' ) ) {
			return;
		}

		offCanvas.setAttribute( 'data-endeavour-menu-bound', '' );

		new MutationObserver( function () {
			if ( 'true' !== offCanvas.getAttribute( 'aria-hidden' ) ) {
				return;
			}

			window.setTimeout( function () {
				show( root, root.getAttribute( 'data-root' ) );
			}, RESET_DELAY );
		} ).observe( offCanvas, {
			attributeFilter: [ 'aria-hidden' ],
			attributes: true,
		} );
	}

	/**
	 * @param {Element|Document} scope Where to look for widgets.
	 */
	function init( scope ) {
		var context = scope || document;
		var roots = context.querySelectorAll ? context.querySelectorAll( ROOT_SELECTOR ) : [];

		Array.prototype.forEach.call( roots, function ( root ) {
			// The markup already marks the first level as current; this adds
			// the state the server cannot set meaningfully.
			show( root, root.getAttribute( 'data-root' ) );
			resetOnClose( root );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	// Re-run inside the editor, where a widget is replaced on every change.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/endeavour-offcanvas-menu.default',
			function ( $scope ) {
				init( $scope && $scope[ 0 ] ? $scope[ 0 ] : document );
			}
		);
	} );
}() );
