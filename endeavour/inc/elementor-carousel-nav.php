<?php
/**
 * Registration glue for the Endeavour Carousel navigation widget.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the widget stylesheet and script.
 */
function endeavour_child_register_carousel_nav_assets() {
	$directory = endeavour_path();
	$uri       = endeavour_url();

	$stylesheet = '/assets/css/elementor-carousel-nav.css';

	if ( is_file( $directory . $stylesheet ) ) {
		wp_register_style(
			'endeavour-carousel-nav',
			$uri . $stylesheet,
			array(),
			(string) filemtime( $directory . $stylesheet )
		);
	}

	$script = '/assets/js/elementor-carousel-nav.js';

	if ( is_file( $directory . $script ) ) {
		wp_register_script(
			'endeavour-carousel-nav',
			$uri . $script,
			array( 'elementor-frontend' ),
			(string) filemtime( $directory . $script ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'endeavour_child_register_carousel_nav_assets', 5 );
add_action( 'elementor/frontend/after_enqueue_scripts', 'endeavour_child_register_carousel_nav_assets' );

/**
 * Register the widget.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function endeavour_child_register_carousel_nav_widget( $widgets_manager ) {
	require_once endeavour_path() . '/inc/widgets/class-endeavour-carousel-nav.php';

	$widgets_manager->register( new Endeavour_Carousel_Nav() );
}
add_action( 'elementor/widgets/register', 'endeavour_child_register_carousel_nav_widget' );

/**
 * Fill the Loop carousel select from the widgets on the page being edited.
 */
function endeavour_child_enqueue_carousel_nav_editor_script() {
	$path = endeavour_path() . '/assets/js/elementor-carousel-nav-editor.js';

	if ( ! is_file( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'endeavour-carousel-nav-editor',
		endeavour_url() . '/assets/js/elementor-carousel-nav-editor.js',
		array( 'elementor-editor' ),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'elementor/editor/after_enqueue_scripts', 'endeavour_child_enqueue_carousel_nav_editor_script' );
