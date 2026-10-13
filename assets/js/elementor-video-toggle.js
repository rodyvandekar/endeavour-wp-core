/**
 * Play/pause a container's Elementor background video, or a Video widget.
 *
 * Everything visible is Elementor's: two Button (or Icon) widgets placed in
 * the container, marked `endeavour-video-pause` and `endeavour-video-play`
 * under Advanced > CSS Classes. Their icons, colours, size, border, hover and
 * position are widget controls. This file only supplies the behaviour, and the
 * operability Elementor's markup lacks — a button without a link renders as an
 * `<a role="button">` with no href, which no keyboard can reach.
 *
 * A Video widget has no pause icon of its own: its play overlay is removed the
 * moment playback starts. Mark the widget `endeavour-video-player` and give it
 * the same two siblings; this file keeps them over the video and wires them to
 * the media element. The widget's own overlay and play icon stay off.
 *
 * Exactly one of the pair is in the document at a time, hidden with the
 * `hidden` attribute so it leaves the accessibility tree too.
 *
 * The icon follows what the player reports rather than what was last clicked.
 * A hosted video exposes that through the media element. An embedded YouTube
 * video does not: Elementor creates the player through the IFrame API and
 * becomes its only API client, so a second `listening` subscription is refused
 * and cross-origin postMessage gives nothing back. Wrapping `YT.Player` while
 * the API boots hands us the same player instance Elementor built, which is
 * the documented way to read state and receive events.
 */
( () => {
	const PAUSE_CLASS = 'endeavour-video-pause';
	const PLAY_CLASS = 'endeavour-video-play';
	const PLAYER_CLASS = 'endeavour-video-player';

	// The control Elementor renders inside a Button or an Icon widget.
	const CONTROL_SELECTOR = '.elementor-button, .elementor-icon';

	// YouTube IFrame API player states that mean "running".
	const YT_PLAYING = 1;
	const YT_BUFFERING = 3;

	/** Every YouTube player created on this page, Elementor's included. */
	const players = [];

	const registerPlayer = ( player ) => {
		players.push( player );
		window.dispatchEvent( new CustomEvent( 'endeavour/video-player' ) );
	};

	/** Wrap a Player constructor so every instance is registered. */
	const wrapConstructor = ( Original ) => {
		const Wrapped = function ( element, options ) {
			const player = new Original( element, options );

			registerPlayer( player );

			return player;
		};

		Wrapped.prototype = Original.prototype;
		Object.assign( Wrapped, Original );

		return Wrapped;
	};

	const patchYouTubeApi = ( api ) => {
		if ( ! api || api.__endeavourPatched ) {
			return api;
		}

		api.__endeavourPatched = true;

		if ( api.Player ) {
			api.Player = wrapConstructor( api.Player );

			return api;
		}

		// The API builds its namespace in steps, so Player can still be
		// missing here. Catch it the moment it is assigned.
		let Player;

		Object.defineProperty( api, 'Player', {
			configurable: true,
			get: () => Player,
			set: ( value ) => {
				Player = value ? wrapConstructor( value ) : value;
			},
		} );

		return api;
	};

	// The API object may already be there, or may arrive later.
	if ( window.YT ) {
		patchYouTubeApi( window.YT );
	} else {
		let api;

		Object.defineProperty( window, 'YT', {
			configurable: true,
			get: () => api,
			set: ( value ) => {
				api = patchYouTubeApi( value );
			},
		} );
	}

	const findPlayerIn = ( container ) =>
		players.find( ( player ) => {
			if ( typeof player.getIframe !== 'function' ) {
				return false;
			}

			try {
				return container.contains( player.getIframe() );
			} catch {
				return false;
			}
		} );

	/**
	 * Make an Elementor widget usable as a button.
	 *
	 * A linked button already behaves; one without a link is an anchor with no
	 * href, so it needs a tab stop and the keys a button answers to.
	 *
	 * @param {Element}  widget Widget wrapper carrying the marker class.
	 * @param {Function} action Runs on activation.
	 * @return {Element|null} The control, or null when the widget has none.
	 */
	const asButton = ( widget, action ) => {
		const control = widget.querySelector( CONTROL_SELECTOR );

		if ( ! control ) {
			return null;
		}

		// The editor sets the label under Advanced > Attributes, which lands on
		// the widget wrapper. Move it to the control so the labelled node is
		// the one a screen reader announces as the button.
		const label = widget.getAttribute( 'aria-label' );

		if ( label && ! control.hasAttribute( 'aria-label' ) ) {
			control.setAttribute( 'aria-label', label );
			widget.removeAttribute( 'aria-label' );
		}

		if ( ! control.hasAttribute( 'href' ) && ! control.hasAttribute( 'tabindex' ) ) {
			control.setAttribute( 'tabindex', '0' );
		}

		control.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			action( false );
		} );

		control.addEventListener( 'keydown', ( event ) => {
			if ( 'Enter' !== event.key && ' ' !== event.key ) {
				return;
			}

			// Space scrolls the page and Enter would follow the anchor.
			event.preventDefault();
			action( true );
		} );

		return control;
	};

	/**
	 * Wire the pair of buttons inside one container to its background video.
	 *
	 * @param {Element} container Elementor container holding video and buttons.
	 */
	const setup = ( container ) => {
		if ( container.dataset.videoToggleReady ) {
			return;
		}

		const pauseWidget = container.querySelector( '.' + PAUSE_CLASS );
		const playWidget = container.querySelector( '.' + PLAY_CLASS );

		if ( ! pauseWidget || ! playWidget ) {
			return;
		}

		container.dataset.videoToggleReady = 'true';

		// Autoplaying video is motion the visitor did not ask for.
		let paused = window.matchMedia( '(prefers-reduced-motion: reduce)' )
			.matches;
		let control = null;

		const render = () => {
			// The pause button is the one on screen while the video runs.
			pauseWidget.hidden = paused;
			playWidget.hidden = ! paused;
		};

		/** Follow the player; only redraw on an actual change. */
		const reportState = ( isPaused ) => {
			if ( paused === isPaused ) {
				return;
			}

			paused = isPaused;
			render();
		};

		const toggle = ( shouldPause ) => ( viaKeyboard ) => {
			control?.setPaused( shouldPause );

			// Show the intent straight away; the player's own event confirms
			// it, or corrects it if playback could not start.
			reportState( shouldPause );

			// The control just activated leaves the document, so focus has to
			// follow. A pointer click must not leave a visible focus behind:
			// that keeps the pause button on screen after the pointer leaves.
			const next = ( shouldPause ? playWidget : pauseWidget )
				.querySelector( CONTROL_SELECTOR );

			next?.focus( { preventScroll: true, focusVisible: viaKeyboard === true } );

			if ( viaKeyboard !== true && next?.matches( ':focus-visible' ) ) {
				next.blur();
			}
		};

		asButton( pauseWidget, toggle( true ) );
		asButton( playWidget, toggle( false ) );

		const connectHosted = ( hosted ) => {
			control = {
				setPaused: ( shouldPause ) =>
					shouldPause ? hosted.pause() : hosted.play().catch( () => {} ),
			};

			[ 'play', 'playing' ].forEach( ( type ) =>
				hosted.addEventListener( type, () => reportState( false ) )
			);
			[ 'pause', 'ended' ].forEach( ( type ) =>
				hosted.addEventListener( type, () => reportState( true ) )
			);

			reportState( hosted.paused );
		};

		const connectEmbed = ( player ) => {
			control = {
				setPaused: ( shouldPause ) =>
					shouldPause ? player.pauseVideo() : player.playVideo(),
			};

			const follow = ( state ) =>
				reportState( state !== YT_PLAYING && state !== YT_BUFFERING );

			player.addEventListener( 'onStateChange', ( event ) =>
				follow( event.data )
			);

			if ( typeof player.getPlayerState === 'function' ) {
				follow( player.getPlayerState() );
			}
		};

		const connect = () => {
			const hosted = container.querySelector(
				'.elementor-background-video-hosted, .elementor-widget-video video'
			);

			if ( hosted ) {
				connectHosted( hosted );
			} else {
				const player = findPlayerIn( container );

				if ( ! player ) {
					return false;
				}

				connectEmbed( player );
			}

			if ( paused ) {
				control.setPaused( true );
			}

			return true;
		};

		if ( ! connect() ) {
			// Elementor builds the video after the container is ready, so wait
			// for either the media element or the player to show up.
			const retry = () => {
				if ( connect() ) {
					window.removeEventListener( 'endeavour/video-player', retry );
					observer.disconnect();
				}
			};

			const observer = new MutationObserver( retry );

			window.addEventListener( 'endeavour/video-player', retry );
			observer.observe( container, { childList: true, subtree: true } );
		}

		render();
	};

	/**
	 * Lay the play/pause pair over a Video widget.
	 *
	 * The pair is a row of two icon widgets. Centring it and taking it out of
	 * the flow lets the video fill the container while the buttons sit in the
	 * middle. Elementor owns the buttons themselves.
	 *
	 * @param {Element} container Container marked as a video player.
	 */
	const coverVideo = ( container ) => {
		const player = container.querySelector( '.elementor-widget-video' );
		const pair = player?.nextElementSibling;

		if ( ! player || ! pair || pair.dataset.videoCoverReady ) {
			return;
		}

		pair.dataset.videoCoverReady = 'true';

		// The row is a sibling of the video, so anchoring it to the container
		// would include the caption and drop the buttons below centre.
		Object.assign( pair.style, {
			position: 'absolute',
			left: '0',
			width: '100%',
			zIndex: '1',
			margin: '0',
			pointerEvents: 'none',
		} );

		const place = () => {
			pair.style.top = player.offsetTop + 'px';
			pair.style.height = player.offsetHeight + 'px';
		};

		place();
		new ResizeObserver( place ).observe( player );

		// Clicks belong to the buttons, not to the row that holds them.
		pair.querySelectorAll( '.elementor-widget' ).forEach( ( widget ) => {
			widget.style.pointerEvents = 'auto';
			widget.style.zIndex = '2';
		} );
	};

	const initialize = ( scope ) => {
		const root = scope instanceof Element ? scope : document;

		root.querySelectorAll( '.' + PLAYER_CLASS ).forEach( coverVideo );

		const widgets = root.querySelectorAll( '.' + PAUSE_CLASS );

		widgets.forEach( ( widget ) => {
			// A Video widget keeps its buttons in their own row, and that row
			// is itself an `.e-con`, so the nearest one holds no video. The
			// marked player is the container that owns both.
			const container =
				widget.closest( '.' + PLAYER_CLASS ) ||
				widget.closest( '.e-con' );

			if ( container ) {
				setup( container );
			}
		} );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initialize() );
	} else {
		initialize();
	}

	// In the editor a container is rebuilt on every change.
	window.addEventListener( 'elementor/frontend/init', () => {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/container',
			( scope ) => initialize( scope[ 0 ] )
		);
	} );
} )();
