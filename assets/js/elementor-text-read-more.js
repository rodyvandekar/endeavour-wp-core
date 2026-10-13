/**
 * Expand and collapse enabled Elementor Text Editor widgets.
 *
 * The collapsed height is the block's own line-height times the number of
 * lines the editor asked for. The teaser is a real clone of the content up to
 * that line, so links and emphasis inside the visible part keep working.
 */
( () => {
	'use strict';

	const DEFAULT_LINES = 3;

	/** Last text node that carries something other than whitespace. */
	const lastTextNode = ( element ) => {
		const walker = document.createTreeWalker( element, NodeFilter.SHOW_TEXT, {
			acceptNode: ( node ) =>
				node.textContent.trim()
					? NodeFilter.FILTER_ACCEPT
					: NodeFilter.FILTER_REJECT,
		} );
		let found = null;
		let node = walker.nextNode();

		while ( node ) {
			found = node;
			node = walker.nextNode();
		}

		return found;
	};

	const initialize = ( scope = document ) => {
		const root = scope instanceof Element ? scope : scope?.[ 0 ];

		if ( ! root ) {
			return;
		}

		const contents = root.matches?.( '.endeavour-read-more__content' )
			? [ root ]
			: root.querySelectorAll( '.endeavour-read-more__content' );

		contents.forEach( ( content ) => {
			if ( content.dataset.readMoreReady === 'true' ) {
				return;
			}

			const teaser = content.previousElementSibling;
			const button = content.nextElementSibling;

			if (
				! teaser?.classList.contains( 'endeavour-read-more__teaser' ) ||
				! button?.classList.contains( 'endeavour-read-more__toggle' )
			) {
				return;
			}

			content.dataset.readMoreReady = 'true';
			teaser.id = `${ content.id }-teaser`;
			button.setAttribute( 'aria-controls', `${ teaser.id } ${ content.id }` );

			const isExpanded = () =>
				button.getAttribute( 'aria-expanded' ) === 'true';

			const getLines = () => {
				const declared = parseInt(
					getComputedStyle( content ).getPropertyValue(
						'--endv-read-more-lines'
					),
					10
				);

				return declared > 0 ? declared : DEFAULT_LINES;
			};

			/**
			 * `line-height: normal` resolves per font, so the first rendered
			 * line box is the only value that can be trusted for it.
			 */
			const getLineHeight = () => {
				const declared = parseFloat(
					getComputedStyle( content ).lineHeight
				);

				if ( Number.isFinite( declared ) ) {
					return declared;
				}

				const node = lastTextNode( content );

				if ( ! node ) {
					return 0;
				}

				const range = document.createRange();
				range.selectNodeContents( node );

				return range.getClientRects()[ 0 ]?.height ?? 0;
			};

			/** Bottom edge of the last line an element is allowed to show. */
			const getClipBottom = ( element, lineHeight ) =>
				element.getBoundingClientRect().top +
				getLines() * lineHeight +
				0.5;

			/** Bottom edge of the last line an element currently renders. */
			const getRenderedBottom = ( element ) => {
				const node = lastTextNode( element );

				if ( ! node ) {
					return null;
				}

				const range = document.createRange();
				range.selectNodeContents( node );
				const rects = [ ...range.getClientRects() ];

				return rects.length ? rects[ rects.length - 1 ].bottom : null;
			};

			/**
			 * Clone the content up to the last character that still fits, so
			 * the teaser keeps the markup of the part the visitor can see.
			 * Returns null when everything fits and no teaser is needed.
			 */
			const buildTeaser = ( lineHeight ) => {
				const clipBottom = getClipBottom( content, lineHeight );
				const walker = document.createTreeWalker(
					content,
					NodeFilter.SHOW_TEXT,
					{
						acceptNode: ( node ) =>
							node.textContent.trim()
								? NodeFilter.FILTER_ACCEPT
								: NodeFilter.FILTER_REJECT,
					}
				);

				const fits = ( textNode, endOffset ) => {
					if ( endOffset === 0 ) {
						return true;
					}

					const range = document.createRange();
					range.setStart( textNode, 0 );
					range.setEnd( textNode, endOffset );
					const rects = [ ...range.getClientRects() ];

					// An empty list means the text has no layout yet. Treating
					// that as "fits" would hide the toggle on a block that does
					// overflow, so demand at least one rectangle.
					return (
						rects.length > 0 &&
						rects.every( ( rect ) => rect.bottom <= clipBottom )
					);
				};

				let node = walker.nextNode();
				let boundaryNode = null;
				let boundaryOffset = 0;
				let truncated = false;

				while ( node ) {
					const length = node.textContent.length;

					if ( fits( node, length ) ) {
						boundaryNode = node;
						boundaryOffset = length;
						node = walker.nextNode();
						continue;
					}

					let low = 0;
					let high = length;

					while ( low < high ) {
						const middle = Math.ceil( ( low + high ) / 2 );

						if ( fits( node, middle ) ) {
							low = middle;
						} else {
							high = middle - 1;
						}
					}

					boundaryNode = node;
					boundaryOffset = low;
					truncated = true;
					break;
				}

				if ( ! truncated || ! boundaryNode ) {
					return null;
				}

				const range = document.createRange();
				range.setStart( content, 0 );
				range.setEnd( boundaryNode, boundaryOffset );
				const fragment = range.cloneContents();

				// The teaser and the full copy coexist in the DOM, so copied
				// IDs would no longer be unique.
				fragment.querySelectorAll?.( '[id]' ).forEach( ( element ) => {
					element.removeAttribute( 'id' );
				} );

				return fragment;
			};

			/** Drop trailing empty wrappers a truncation left behind. */
			const pruneAfter = ( node, stopAt ) => {
				let current = node;

				while ( current && current !== stopAt ) {
					while ( current.nextSibling ) {
						current.nextSibling.remove();
					}

					current = current.parentNode;
				}
			};

			/** Remove the last word, keeping the ellipsis on the final line. */
			const dropLastWord = () => {
				const node = lastTextNode( teaser );

				if ( ! node ) {
					return false;
				}

				const text = node.textContent.replace( /…$/, '' );
				const shortened = text.replace( /\s*\S+\s*$/, '' );

				if ( ! shortened.trim() ) {
					const removable = node.parentNode === teaser ? node : node.parentNode;
					removable.remove();

					return Boolean( lastTextNode( teaser ) );
				}

				node.textContent = `${ shortened.trimEnd() }…`;

				return true;
			};

			const collapse = ( lineHeight ) => {
				const fragment = buildTeaser( lineHeight );

				if ( ! fragment ) {
					return false;
				}

				teaser.replaceChildren( fragment );

				const node = lastTextNode( teaser );

				if ( node ) {
					pruneAfter( node, teaser );
					node.textContent = `${ node.textContent.trimEnd() }…`;
				}

				teaser.hidden = false;
				content.hidden = true;

				// The ellipsis can push the final word onto one line too many,
				// so give words back until the teaser fits the allowed lines.
				let guard = 0;

				while ( guard < 200 ) {
					const bottom = getRenderedBottom( teaser );

					if (
						bottom === null ||
						bottom <= getClipBottom( teaser, lineHeight )
					) {
						break;
					}

					if ( ! dropLastWord() ) {
						break;
					}

					guard += 1;
				}

				return true;
			};

			const expand = () => {
				teaser.hidden = true;
				content.hidden = false;
			};

			const refresh = () => {
				if ( isExpanded() ) {
					return;
				}

				expand();

				// Nothing is measurable while the block is still unlaid out.
				if ( ! content.getClientRects().length ) {
					return;
				}

				const lineHeight = getLineHeight();
				const collapsed = lineHeight > 0 && collapse( lineHeight );

				button.hidden = ! collapsed;
			};

			requestAnimationFrame( refresh );
			window.addEventListener( 'resize', refresh, { passive: true } );

			// A webfont swap changes how much text fits, and the first frame can
			// land before the widget has any layout at all.
			window.addEventListener( 'load', refresh, { once: true } );
			document.fonts?.ready.then( refresh );

			button.addEventListener( 'click', () => {
				const nextExpanded = ! isExpanded();
				const label = button.querySelector(
					'.endeavour-read-more__label'
				);

				button.setAttribute( 'aria-expanded', String( nextExpanded ) );

				if ( nextExpanded ) {
					expand();
				} else {
					refresh();
				}

				if ( label ) {
					label.textContent = nextExpanded
						? button.dataset.lessText
						: button.dataset.moreText;
				}
			} );
		} );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () =>
			initialize( document.body )
		);
	} else {
		initialize( document.body );
	}

	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/text-editor.default',
			initialize
		);
	} );
} )();
