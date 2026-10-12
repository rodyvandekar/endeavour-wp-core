<?php
/**
 * Registration glue for the Endeavour Off-Canvas Menu widget.
 *
 * The widget itself lives in inc/widgets/. This file only wires it into
 * Elementor: a panel category, the two assets it needs, and the widget class.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add an "Endeavour" category to the widget panel.
 *
 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
 */
function endeavour_child_register_widget_category( $elements_manager ) {
	$elements_manager->add_category(
		'endeavour',
		array(
			'title' => esc_html__( 'Endeavour', 'endeavour' ),
			'icon'  => 'eicon-nav-menu',
		)
	);
}
add_action( 'elementor/elements/categories_registered', 'endeavour_child_register_widget_category' );

/**
 * Register the widget's stylesheet and script.
 *
 * These are deliberately not in the global list in functions.php: Elementor
 * pulls them in through the widget's get_style_depends() / get_script_depends(),
 * so a page without the widget never downloads them. Versioning on filemtime
 * means a recompile reaches the browser without a theme version bump.
 */
function endeavour_child_register_offcanvas_menu_assets() {
	$directory = endeavour_path();
	$uri       = endeavour_url();

	$stylesheet = '/assets/css/elementor-offcanvas-menu.css';

	if ( is_file( $directory . $stylesheet ) ) {
		wp_register_style(
			'endeavour-offcanvas-menu',
			$uri . $stylesheet,
			array(),
			(string) filemtime( $directory . $stylesheet )
		);
	}

	$script = '/assets/js/elementor-offcanvas-menu.js';

	if ( is_file( $directory . $script ) ) {
		wp_register_script(
			'endeavour-offcanvas-menu',
			$uri . $script,
			array(),
			(string) filemtime( $directory . $script ),
			true
		);
	}
}
// Early, so the handles exist by the time Elementor renders a widget.
add_action( 'wp_enqueue_scripts', 'endeavour_child_register_offcanvas_menu_assets', 5 );

/**
 * Register the Endeavour widgets.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 */
function endeavour_child_register_widgets( $widgets_manager ) {
	require_once endeavour_path() . '/inc/widgets/class-endeavour-offcanvas-menu.php';

	$widgets_manager->register( new Endeavour_Offcanvas_Menu() );
}
add_action( 'elementor/widgets/register', 'endeavour_child_register_widgets' );
