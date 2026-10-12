<?php
/**
 * Endeavour design system.
 *
 * Loaded by the must-use loader in the directory above. Post types, Elementor
 * widgets and the compiled styles live here so they stay active independently
 * of the child theme.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENDEAVOUR_VERSION', '1.1.0' );
define( 'ENDEAVOUR_FILE', __FILE__ );
define( 'ENDEAVOUR_DIR', plugin_dir_path( ENDEAVOUR_FILE ) );
define( 'ENDEAVOUR_URL', plugin_dir_url( ENDEAVOUR_FILE ) );

/**
 * Absolute path inside this plugin, without a trailing slash.
 *
 * @return string
 */
function endeavour_path() {
	return untrailingslashit( ENDEAVOUR_DIR );
}

/**
 * URL of this plugin, without a trailing slash.
 *
 * @return string
 */
function endeavour_url() {
	return untrailingslashit( ENDEAVOUR_URL );
}

/**
 * Load translations from wp-content/languages/plugins, where WordPress
 * stores must-use plugin translations.
 */
function endeavour_load_textdomain() {
	load_muplugin_textdomain( 'endeavour', 'endeavour/languages' );
}
add_action( 'init', 'endeavour_load_textdomain' );

require_once ENDEAVOUR_DIR . 'inc/nav-menu-locations.php';
require_once ENDEAVOUR_DIR . 'inc/nav-menu-item-borders.php';
require_once ENDEAVOUR_DIR . 'inc/endeavour-blocks.php';
require_once ENDEAVOUR_DIR . 'inc/people.php';
require_once ENDEAVOUR_DIR . 'inc/downloads.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-button-types.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-icon-library.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-offcanvas-menu.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-overview-link.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-gravity-form.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-share.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-text-read-more.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-container-hover.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-container-link.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-loop-carousel.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-carousel-nav.php';
require_once ENDEAVOUR_DIR . 'inc/elementor-search-suggestions.php';
require_once ENDEAVOUR_DIR . 'inc/gravity-forms-submit-note.php';
require_once ENDEAVOUR_DIR . 'inc/assets.php';
