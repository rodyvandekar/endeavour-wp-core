<?php
/**
 * Plugin Name: Endeavour WP Core
 * Plugin URI: https://endeavour.nl
 * Description: Design-system behaviour for the Endeavour site: post types, Elementor widgets and the compiled styles.
 * Version: 1.5.0
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Author: Endeavour
 * Author URI: https://endeavour.nl
 * License: GNU General Public License v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: endeavour
 * Domain Path: /languages
 * Update URI: https://github.com/rodyvandekar/endeavour-wp-core
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Settings used across the plugin. The release build reads the header and
 * these constants, so the version and requirements are written only once.
 */
if ( ! defined( 'ENDEAVOUR_VERSION' ) ) {
	define( 'ENDEAVOUR_VERSION', '1.5.0' );
}
if ( ! defined( 'ENDEAVOUR_SLUG' ) ) {
	define( 'ENDEAVOUR_SLUG', 'endeavour-wp-core' );
}
if ( ! defined( 'ENDEAVOUR_FILE' ) ) {
	define( 'ENDEAVOUR_FILE', __FILE__ );
}
if ( ! defined( 'ENDEAVOUR_BASENAME' ) ) {
	define( 'ENDEAVOUR_BASENAME', plugin_basename( ENDEAVOUR_FILE ) );
}
if ( ! defined( 'ENDEAVOUR_DIR' ) ) {
	define( 'ENDEAVOUR_DIR', plugin_dir_path( ENDEAVOUR_FILE ) );
}
if ( ! defined( 'ENDEAVOUR_URL' ) ) {
	define( 'ENDEAVOUR_URL', plugin_dir_url( ENDEAVOUR_FILE ) );
}
if ( ! defined( 'ENDEAVOUR_MIN_WP' ) ) {
	define( 'ENDEAVOUR_MIN_WP', '6.4' );
}
if ( ! defined( 'ENDEAVOUR_MIN_PHP' ) ) {
	define( 'ENDEAVOUR_MIN_PHP', '8.2' );
}
if ( ! defined( 'ENDEAVOUR_HOMEPAGE' ) ) {
	define( 'ENDEAVOUR_HOMEPAGE', 'https://endeavour.nl' );
}
if ( ! defined( 'ENDEAVOUR_REPOSITORY' ) ) {
	define( 'ENDEAVOUR_REPOSITORY', 'rodyvandekar/endeavour-wp-core' );
}
if ( ! defined( 'ENDEAVOUR_UPDATE_PUBLIC_KEY' ) ) {
	/* Public half of the release signing key. The private half lives in the GitHub secret ENDEAVOUR_SIGNING_KEY. */
	define( 'ENDEAVOUR_UPDATE_PUBLIC_KEY', 'yitLpgI+XyS8Xr77nGDK0aTZDnd8KfjTLAYs5znwTB4=' );
}

if ( ! function_exists( 'endeavour_path' ) ) {
	/**
	 * Absolute path inside this plugin, without a trailing slash.
	 *
	 * @return string
	 */
	function endeavour_path() {
		return untrailingslashit( ENDEAVOUR_DIR );
	}
}

if ( ! function_exists( 'endeavour_url' ) ) {
	/**
	 * URL of this plugin, without a trailing slash.
	 *
	 * @return string
	 */
	function endeavour_url() {
		return untrailingslashit( ENDEAVOUR_URL );
	}
}

if ( ! function_exists( 'endeavour_asset_version' ) ) {
	/**
	 * Cache-busting version for a bundled file: its modification time, so a
	 * recompile reaches the browser. A missing file falls back to the plugin
	 * version instead of raising a warning on every page.
	 *
	 * @param string $path Absolute path to the file.
	 * @return string
	 */
	function endeavour_asset_version( string $path ): string {
		return is_file( $path ) ? (string) filemtime( $path ) : ENDEAVOUR_VERSION;
	}
}

if ( ! function_exists( 'endeavour_load_textdomain' ) ) {
	/**
	 * Load translations shipped with the plugin.
	 */
	function endeavour_load_textdomain() {
		load_plugin_textdomain( 'endeavour', false, dirname( ENDEAVOUR_BASENAME ) . '/languages' );
	}
	add_action( 'init', 'endeavour_load_textdomain' );
}

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
require_once ENDEAVOUR_DIR . 'inc/required-plugin.php';
require_once ENDEAVOUR_DIR . 'inc/updater.php';
