<?php
/**
 * Restrict Elementor's icon picker to the Endeavour icon set.
 *
 * Elementor splits the picker into "native" tabs (the bundled Font Awesome
 * families, plus the Pro kit variants) and "additional" tabs (custom icon sets
 * uploaded under Elementor > Custom Icons). Dropping every Font Awesome tab
 * from the native list leaves Endeavour Icons as the only thing an editor can
 * choose from.
 *
 * This removes Font Awesome from the *editor* only. Widgets that still hold a
 * Font Awesome value keep rendering, so an older page never loses its icons.
 *
 * The icon picker's Recommended tab still names those Font Awesome libraries.
 * assets/js/elementor-icon-picker.js drops recommendations for libraries that
 * are no longer registered, so the picker opens on Endeavour Icons instead of
 * crashing on a missing `icons` list.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom icon sets registered under Elementor > Custom Icons.
 *
 * @return array Set config keyed by set name, empty when none are available.
 */
function endeavour_child_custom_icon_sets() {
	$custom_icons = '\ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons';

	// Reads the cached option and only falls back to a query when it is unset.
	if ( class_exists( $custom_icons ) ) {
		$config = $custom_icons::get_custom_icons_config();
	} else {
		$config = get_option( 'elementor_custom_icon_sets_config', array() );
	}

	return is_array( $config ) ? $config : array();
}

/**
 * Drop the Font Awesome tabs from Elementor's icon picker.
 *
 * Matching on the `fa-` prefix rather than on tab names covers the free
 * families and the Pro ones (light, duotone, …) that Elementor Pro swaps in
 * through this same filter.
 *
 * @param array $tabs Native icon manager tabs.
 * @return array Tabs without Font Awesome.
 */
function endeavour_child_hide_font_awesome_tabs( $tabs ) {
	// Never hand the editor an empty picker: if the Endeavour set is missing or
	// still being installed, leave Font Awesome in place.
	if ( ! endeavour_child_custom_icon_sets() ) {
		return $tabs;
	}

	foreach ( $tabs as $name => $tab ) {
		if ( isset( $tab['prefix'] ) && 'fa-' === $tab['prefix'] ) {
			unset( $tabs[ $name ] );
		}
	}

	return $tabs;
}
// Late, so Elementor Pro has already registered its Font Awesome Pro tabs.
add_filter( 'elementor/icons_manager/native', 'endeavour_child_hide_font_awesome_tabs', 100 );

/**
 * Load the picker guard in the Elementor editor.
 */
function endeavour_child_enqueue_icon_picker_guard() {
	$script = endeavour_path() . '/assets/js/elementor-icon-picker.js';

	if ( ! is_file( $script ) ) {
		return;
	}

	wp_enqueue_script(
		'endeavour-elementor-icon-picker',
		endeavour_url() . '/assets/js/elementor-icon-picker.js',
		array( 'elementor-editor' ),
		(string) filemtime( $script ),
		true
	);
}
add_action( 'elementor/editor/before_enqueue_scripts', 'endeavour_child_enqueue_icon_picker_guard' );
