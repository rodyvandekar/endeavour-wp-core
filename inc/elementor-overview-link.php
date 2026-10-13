<?php
/**
 * Registration glue for the Endeavour Overview Link widget.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the widget stylesheet.
 */
function endeavour_child_register_overview_link_assets() {
	$relative_path = '/assets/css/elementor-overview-link.css';
	$absolute_path = endeavour_path() . $relative_path;

	if ( ! is_file( $absolute_path ) ) {
		return;
	}

	wp_register_style(
		'endeavour-overview-link',
		endeavour_url() . $relative_path,
		array(),
		(string) filemtime( $absolute_path )
	);
}
add_action( 'wp_enqueue_scripts', 'endeavour_child_register_overview_link_assets', 5 );

/**
 * Register the widget with Elementor.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function endeavour_child_register_overview_link_widget( $widgets_manager ) {
	require_once endeavour_path() . '/inc/widgets/class-endeavour-overview-link.php';

	$widgets_manager->register( new Endeavour_Overview_Link() );
}
add_action( 'elementor/widgets/register', 'endeavour_child_register_overview_link_widget' );
