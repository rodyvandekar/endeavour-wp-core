<?php
/**
 * Registration glue for the Endeavour Share widget.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the widget stylesheet and behaviour.
 */
function endeavour_child_register_share_assets() {
	$style_path = endeavour_path() . '/assets/css/elementor-share.css';
	$script_path = endeavour_path() . '/assets/js/elementor-share.js';

	if ( is_file( $style_path ) ) {
		wp_register_style(
			'endeavour-share',
			endeavour_url() . '/assets/css/elementor-share.css',
			array( 'elementor-frontend' ),
			(string) filemtime( $style_path )
		);
	}

	if ( is_file( $script_path ) ) {
		wp_register_script(
			'endeavour-share',
			endeavour_url() . '/assets/js/elementor-share.js',
			array(),
			(string) filemtime( $script_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'endeavour_child_register_share_assets', 5 );
add_action( 'elementor/editor/before_enqueue_scripts', 'endeavour_child_register_share_assets' );

/**
 * Register the widget with Elementor.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function endeavour_child_register_share_widget( $widgets_manager ) {
	require_once endeavour_path() . '/inc/widgets/class-endeavour-share.php';

	$widgets_manager->register( new Endeavour_Share() );
}
add_action( 'elementor/widgets/register', 'endeavour_child_register_share_widget' );
