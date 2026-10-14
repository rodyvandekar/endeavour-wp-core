<?php
/**
 * Frontend scripts and the compiled design-system styles.
 *
 * Sources live in assets/scss/. Versioning on filemtime means a recompile
 * reaches the browser without bumping the plugin version by hand.
 *
 * Compile from this plugin's directory:
 * npx --yes sass assets/scss/FILE.scss assets/css/FILE.css --no-source-map
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Behaviour scripts that are not owned by an Elementor widget.
 */
function endeavour_enqueue_behaviour_scripts() {
	$scripts = array(
		'endeavour-elementor-header-search'      => array(
			'file' => 'elementor-header-search.js',
			'deps' => array(),
		),
		'endeavour-elementor-search-suggestions' => array(
			'file' => 'elementor-search-suggestions.js',
			'deps' => array( 'elementor-frontend' ),
		),
		'endeavour-elementor-video-toggle'       => array(
			'file' => 'elementor-video-toggle.js',
			'deps' => array( 'elementor-frontend' ),
		),
		'endeavour-elementor-tabs-scroll'        => array(
			'file' => 'elementor-tabs-scroll.js',
			'deps' => array( 'elementor-frontend' ),
		),
	);

	foreach ( $scripts as $handle => $script ) {
		$absolute_path = endeavour_path() . '/assets/js/' . $script['file'];

		if ( ! is_file( $absolute_path ) ) {
			continue;
		}

		wp_enqueue_script(
			$handle,
			endeavour_url() . '/assets/js/' . $script['file'],
			$script['deps'],
			endeavour_asset_version( $absolute_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'endeavour_enqueue_behaviour_scripts', 20 );

/**
 * Load the compiled Elementor design-system styles.
 *
 * Normalize comes first, so a component stylesheet can still override it.
 * The admin-bar sheet is last and only when there is a bar to make room for.
 */
function endeavour_enqueue_elementor_styles() {
	$stylesheets = array(
		'endeavour-elementor-normalize'         => 'elementor-normalize',
		'endeavour-elementor-button-types'      => 'elementor-button-types',
		'endeavour-elementor-form-fields'       => 'elementor-form-fields',
		'endeavour-elementor-gravity-forms'     => 'elementor-gravity-forms',
		'endeavour-elementor-menu-items'        => 'elementor-menu-items',
		'endeavour-elementor-interactive-hover' => 'elementor-interactive-hover',
		'endeavour-elementor-tabs-scroll'       => 'elementor-tabs-scroll',
		'endeavour-elementor-container-hover'   => 'elementor-container-hover',
		'endeavour-elementor-container-link'    => 'elementor-container-link',
		'endeavour-elementor-loop-carousel'     => 'elementor-loop-carousel',
		'endeavour-elementor-image-carousel'    => 'elementor-image-carousel',
		'endeavour-elementor-search'            => 'elementor-search',
		'endeavour-elementor-text-read-more'    => 'elementor-text-read-more',
	);

	if ( is_admin_bar_showing() ) {
		$stylesheets['endeavour-elementor-admin-bar'] = 'elementor-admin-bar';
	}

	$dependencies = array();

	if ( wp_style_is( 'endeavour-child-style', 'registered' ) ) {
		$dependencies[] = 'endeavour-child-style';
	}

	foreach ( $stylesheets as $handle => $basename ) {
		$relative_path = '/assets/css/' . $basename . '.css';
		$absolute_path = endeavour_path() . $relative_path;

		if ( ! is_file( $absolute_path ) ) {
			continue;
		}

		wp_enqueue_style(
			$handle,
			endeavour_url() . $relative_path,
			$dependencies,
			endeavour_asset_version( $absolute_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'endeavour_enqueue_elementor_styles', 30 );
