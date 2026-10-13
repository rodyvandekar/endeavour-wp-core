<?php
/**
 * Navigation menu locations owned by the Endeavour child theme.
 *
 * Hello Elementor registers its own locations; `register_nav_menus()` merges
 * rather than replaces, so the parent's entries stay intact.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug of the location the Off-Canvas Menu widget reads by default.
 *
 * Kept as a constant because both the theme setup and the widget refer to it,
 * and a typo in either place would silently render an empty menu.
 */
define( 'ENDEAVOUR_OFFCANVAS_MENU_LOCATION', 'endeavour_offcanvas' );

/**
 * Register the Endeavour menu locations.
 */
function endeavour_child_register_menu_locations() {
	register_nav_menus(
		array(
			ENDEAVOUR_OFFCANVAS_MENU_LOCATION => esc_html__( 'Endeavour Off-Canvas', 'endeavour' ),
		)
	);
}
add_action( 'after_setup_theme', 'endeavour_child_register_menu_locations' );
