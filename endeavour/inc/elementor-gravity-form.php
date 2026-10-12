<?php
/**
 * Registration glue for the Endeavour Gravity Form widget.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the widget with Elementor.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function endeavour_child_register_gravity_form_widget( $widgets_manager ) {
	require_once endeavour_path() . '/inc/widgets/class-endeavour-gravity-form.php';

	$widgets_manager->register( new Endeavour_Gravity_Form() );
}
add_action( 'elementor/widgets/register', 'endeavour_child_register_gravity_form_widget' );
