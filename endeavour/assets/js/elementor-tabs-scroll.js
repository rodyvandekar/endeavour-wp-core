/**
 * Slide Nested Tabs when the labels do not fit.
 *
 * Elementor scrolls the heading, and on a phone that scroll is invisible:
 * the scrollbar is hidden and there is no chevron. Figma 4400:21185 shows
 * a line and a chevron at the side that still has tabs out of view.
 *
 * The heading must stay a direct child of `.e-n-tabs`: every tab state the
 * widget prints (`> .e-n-tabs > .e-n-tabs-heading > .e-n-tab-title`) depends
 * on it. The controls are positioned over its edges instead.
 *
 * The icons are the Endeavour set Elementor already loads (`endv-chevron-left`
 * and `endv-chevron-right`). Tab colour, the underline and the panel stay on
 * the widget.
 */
( () => {
	const TAB = ':scope > .e-n-tab-title';

	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	// $motion-duration in _tokens.scss. Something that travels, not a hover.
	const MOTION_MS = 300;

	// Native `behavior: 'smooth'` is dropped by Chromium on the first call in
	// some embedded views, so the slide is animated here instead.
	const animate = ( heading, left ) => {
		const from = heading.scrollLeft;
		const distance = left - from;

		if ( heading.__endeavourSlide ) {
			cancelAnimationFrame( heading.__endeavourSlide );
		}

		if ( reducedMotion.matches || Math.abs( distance ) < 1 ) {
			heading.scrollLeft = left;
			return;
		}

		const start = performance.now();

		const step = ( now ) => {
			const progress = Math.min( 1, ( now - start ) / MOTION_MS );
			const eased = 1 - Math.pow( 1 - progress, 3 );

			heading.scrollLeft = from + distance * eased;
			heading.__endeavourSlide = progress < 1 ? requestAnimationFrame( step ) : 0;
		};

		heading.__endeavourSlide = requestAnimationFrame( step );
	};

	const icon = ( name ) => {
		const glyph = document.createElement( 'i' );

		glyph.className = 'endv endv-chevron-' + name;
		glyph.setAttribute( 'aria-hidden', 'true' );

		return glyph;
	};

	const control = ( direction ) => {
		const group = document.createElement( 'div' );
		const line = document.createElement( 'span' );
		const button = document.createElement( 'button' );

		group.className = 'endeavour-tabs-scroll endeavour-tabs-scroll--' + direction;
		group.hidden = true;

		line.className = 'endeavour-tabs-scroll__line';
		line.setAttribute( 'aria-hidden', 'true' );

		button.type = 'button';
		button.className = 'endeavour-tabs-scroll__button';
		button.append( icon( direction === 'next' ? 'right' : 'left' ) );
		button.setAttribute(
			'aria-label',
			direction === 'next' ? 'Next tabs' : 'Previous tabs'
		);

		if ( direction === 'next' ) {
			group.append( line, button );
		} else {
			group.append( button, line );
		}

		return { group, button };
	};

	// A couple of pixels, so a fractional scroll still counts as the end.
	const EDGE = 2;

	const tabs = ( heading ) => [ ...heading.querySelectorAll( TAB ) ];

	// Scroll position at which each tab's left edge sits flush with the row.
	const tabStarts = ( heading ) => {
		const box = heading.getBoundingClientRect();

		return tabs( heading ).map(
			( tab ) => heading.scrollLeft + tab.getBoundingClientRect().left - box.left
		);
	};

	// "At the end" is the last tab sitting fully inside the row, not scrollLeft
	// reaching scrollWidth. A snap can stop a few pixels short of the maximum
	// and the next chevron would otherwise stay.
	const overflow = ( heading ) => {
		const items = tabs( heading );
		const box = heading.getBoundingClientRect();
		const max = Math.max( 0, heading.scrollWidth - heading.clientWidth );
		const first = items[ 0 ];
		const last = items[ items.length - 1 ];

		return {
			max,
			before: !! first && first.getBoundingClientRect().left < box.left - EDGE,
			after: !! last && last.getBoundingClientRect().right > box.right + EDGE,
		};
	};

	// The tab currently flush with the left edge, then one step from there.
	// The last step goes all the way, so the chevron can disappear.
	const slide = ( heading, direction ) => {
		const state = overflow( heading );
		const starts = tabStarts( heading );
		let index = 0;

		starts.forEach( ( start, i ) => {
			if ( start <= heading.scrollLeft + EDGE ) {
				index = i;
			}
		} );

		const nextIndex = index + direction;

		if ( direction > 0 && ( nextIndex >= starts.length || starts[ nextIndex ] >= state.max - EDGE ) ) {
			animate( heading, state.max );
			return;
		}

		if ( direction < 0 && nextIndex <= 0 ) {
			animate( heading, 0 );
			return;
		}

		animate( heading, Math.max( 0, Math.min( state.max, starts[ nextIndex ] ) ) );
	};

	// Mouse and touch. Elementor's own drag only listens for mousedown and
	// moves a few pixels per event, so this replaces it. A small movement
	// stays a click that switches the tab.
	const DRAG_THRESHOLD = 6;

	const enableDrag = ( heading ) => {
		let pointerId = null;
		let startX = 0;
		let startScroll = 0;
		let dragged = false;
		let swallowClick = false;

		heading.addEventListener( 'mousedown', ( event ) => {
			event.stopPropagation();
		}, true );

		heading.addEventListener( 'pointerdown', ( event ) => {
			if ( event.button !== 0 || overflow( heading ).max <= EDGE ) {
				return;
			}

			pointerId = event.pointerId;
			startX = event.clientX;
			startScroll = heading.scrollLeft;
			dragged = false;

			if ( heading.__endeavourSlide ) {
				cancelAnimationFrame( heading.__endeavourSlide );
				heading.__endeavourSlide = 0;
			}

			try {
				heading.setPointerCapture( event.pointerId );
			} catch {
				// The pointer can already be gone. Dragging still follows the move.
			}
		} );

		heading.addEventListener( 'pointermove', ( event ) => {
			if ( event.pointerId !== pointerId ) {
				return;
			}

			const delta = event.clientX - startX;

			if ( ! dragged && Math.abs( delta ) < DRAG_THRESHOLD ) {
				return;
			}

			dragged = true;
			heading.classList.add( 'is-dragging' );
			heading.scrollLeft = startScroll - delta;
			event.preventDefault();
		}, { passive: false } );

		const finishDrag = ( event ) => {
			if ( event.pointerId !== pointerId ) {
				return;
			}

			pointerId = null;
			heading.classList.remove( 'is-dragging' );

			if ( ! dragged ) {
				return;
			}

			swallowClick = true;
			dragged = false;

			const state = overflow( heading );
			const starts = tabStarts( heading );
			let nearest = heading.scrollLeft <= EDGE ? 0 : state.max;

			starts.forEach( ( start ) => {
				if ( Math.abs( start - heading.scrollLeft ) < Math.abs( nearest - heading.scrollLeft ) ) {
					nearest = start;
				}
			} );

			if ( heading.scrollLeft >= state.max - EDGE ) {
				nearest = state.max;
			}

			animate( heading, Math.max( 0, Math.min( state.max, nearest ) ) );
		};

		heading.addEventListener( 'pointerup', finishDrag );
		heading.addEventListener( 'pointercancel', finishDrag );

		heading.addEventListener( 'click', ( event ) => {
			if ( ! swallowClick ) {
				return;
			}

			swallowClick = false;
			event.preventDefault();
			event.stopPropagation();
		}, true );
	};

	const setup = ( widget ) => {
		const root = widget.querySelector( ':scope .e-n-tabs' );
		const heading = root && root.querySelector( ':scope > .e-n-tabs-heading' );

		if ( ! heading || root.querySelector( ':scope > .endeavour-tabs-scroll' ) ) {
			return;
		}

		const previous = control( 'prev' );
		const next = control( 'next' );

		root.classList.add( 'endeavour-tabs-scrollable' );
		root.append( previous.group, next.group );
		enableDrag( heading );

		// The controls cover the edge of the heading instead of narrowing it.
		// Any change to the heading width mid-scroll cancels a smooth scroll.
		const paint = () => {
			const state = overflow( heading );

			previous.group.hidden = ! state.before;
			next.group.hidden = ! state.after;
			heading.classList.toggle( 'endeavour-tabs-draggable', state.max > EDGE );

			const top = heading.offsetTop + 'px';
			const height = heading.offsetHeight + 'px';

			[ previous.group, next.group ].forEach( ( group ) => {
				group.style.top = top;
				group.style.height = height;
			} );
		};

		previous.button.addEventListener( 'click', () => slide( heading, -1 ) );
		next.button.addEventListener( 'click', () => slide( heading, 1 ) );
		heading.addEventListener( 'scroll', paint, { passive: true } );
		window.addEventListener( 'resize', paint );

		if ( typeof ResizeObserver !== 'undefined' ) {
			new ResizeObserver( paint ).observe( root );
		}

		paint();
	};

	const initialize = ( scope ) => {
		const root = scope instanceof Element ? scope : document;

		root.querySelectorAll( '.elementor-widget-n-tabs' ).forEach( setup );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initialize() );
	} else {
		initialize();
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/nested-tabs.default',
			( scope ) => initialize( scope[ 0 ] )
		);
	} );
} )();
