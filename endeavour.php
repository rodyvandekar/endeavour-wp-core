<?php
/**
 * Plugin Name: Endeavour
 * Plugin URI: https://endeavour.nl
 * Description: Design-system behaviour for the Endeavour site: post types, Elementor widgets and the compiled styles.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Author: Endeavour
 * Author URI: https://endeavour.nl
 * License: GNU General Public License v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: endeavour
 * Domain Path: /languages
 *
 * Must-use plugins only load PHP files placed directly in this directory.
 * This file boots the plugin that lives in the endeavour folder beside it.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/endeavour/endeavour.php';
