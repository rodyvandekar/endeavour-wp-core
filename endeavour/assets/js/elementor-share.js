/**
 * Fill share addresses from the page the visitor is on, and toggle the
 * networks that sit behind Other.
 */
( () => {
	'use strict';

	const placeholder = /\{(url|title|text|message|app_id)\}/g;

	const pageUrl = () => location.href.split( '#' )[ 0 ];

	const valuesFor = ( root ) => {
		const source = root.dataset.urlSource;
		const url =
			source === 'custom' && root.dataset.url
				? root.dataset.url
				: pageUrl();
		const title = root.dataset.title || document.title;
		const text = root.dataset.text || title;

		return {
			url,
			title,
			text,
			message: `${ text } ${ url }`.trim(),
			app_id: root.dataset.appId || '',
		};
	};

	const fill = ( template, values ) =>
		template.replace( placeholder, ( token, key ) =>
			encodeURIComponent( values[ key ] || '' )
		);

	const initialize = ( scope = document ) => {
		const rootNode = scope instanceof Element ? scope : scope?.[ 0 ];

		if ( ! rootNode ) {
			return;
		}

		const widgets = rootNode.matches?.( '.endeavour-share' )
			? [ rootNode ]
			: rootNode.querySelectorAll( '.endeavour-share' );

		widgets.forEach( ( root ) => {
			if ( root.dataset.shareReady === 'true' ) {
				return;
			}

			root.dataset.shareReady = 'true';
			const values = valuesFor( root );

			root.querySelectorAll( '[data-template]' ).forEach( ( link ) => {
				const template = link.dataset.customUrl || link.dataset.template;

				if ( template ) {
					link.href = fill( template, values );
				}
			} );

			root.querySelectorAll( '[data-action="native"]' ).forEach( ( button ) => {
				if ( typeof navigator.share !== 'function' ) {
					button.hidden = true;
				}
			} );

			const toggle = root.querySelector( '.endeavour-share__toggle' );
			const more = root.querySelector( '.endeavour-share__more' );

			if ( toggle && more ) {
				const label = toggle.querySelector( '.endeavour-share__label' );
				const iconClosed = toggle.querySelector(
					'.endeavour-share__toggle-icon--closed'
				);
				const iconOpen = toggle.querySelector(
					'.endeavour-share__toggle-icon--open'
				);

				const setOpen = ( open ) => {
					const text = open
						? toggle.dataset.labelOpen
						: toggle.dataset.label;

					toggle.setAttribute( 'aria-expanded', String( open ) );
					more.hidden = ! open;

					if ( label ) {
						label.textContent = text;
					} else {
						toggle.setAttribute( 'aria-label', text );
					}

					if ( iconClosed && iconOpen ) {
						iconClosed.hidden = open;
						iconOpen.hidden = ! open;
					}
				};

				toggle.addEventListener( 'click', () => {
					setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
				} );

				root.addEventListener( 'keydown', ( event ) => {
					if (
						event.key === 'Escape' &&
						toggle.getAttribute( 'aria-expanded' ) === 'true'
					) {
						setOpen( false );
						toggle.focus();
					}
				} );
			}

			root.querySelectorAll( '[data-action="copy"]' ).forEach( ( button ) => {
				button.addEventListener( 'click', async () => {
					const current = valuesFor( root );
					const label = button.querySelector( '.endeavour-share__label' );
					const previousLabel = label ? label.textContent : '';
					const previousName = button.getAttribute( 'aria-label' );
					const copied = root.dataset.copiedLabel || 'Link copied';

					try {
						await navigator.clipboard.writeText( current.url );
					} catch ( error ) {
						return;
					}

					if ( label ) {
						label.textContent = copied;
					}

					if ( previousName ) {
						button.setAttribute( 'aria-label', copied );
					}

					window.setTimeout( () => {
						if ( label ) {
							label.textContent = previousLabel;
						}

						if ( previousName ) {
							button.setAttribute( 'aria-label', previousName );
						}
					}, 2000 );
				} );
			} );

			root.querySelectorAll( '[data-action="native"]' ).forEach( ( button ) => {
				button.addEventListener( 'click', async () => {
					const current = valuesFor( root );

					try {
						await navigator.share( {
							title: current.title,
							text: current.text,
							url: current.url,
						} );
					} catch ( error ) {
						// The visitor dismissed the sheet. Nothing to recover.
					}
				} );
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
			'frontend/element_ready/endeavour-share.default',
			initialize
		);
	} );
} )();
