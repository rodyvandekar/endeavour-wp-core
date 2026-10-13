<?php
/**
 * This plugin stays active. The Plugins screen explains why.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the deactivate action with a fixed notice.
 *
 * @param array  $links       Row actions.
 * @param string $plugin_file Plugin basename.
 * @return array
 */
function endeavour_required_plugin_links( $links, $plugin_file ) {
	if ( plugin_basename( ENDEAVOUR_FILE ) !== $plugin_file || ! is_array( $links ) ) {
		return $links;
	}

	unset( $links['deactivate'] );

	$notice = '<span class="endeavour-required-plugin">' . esc_html__( 'This plugin is required for this website', 'endeavour' ) . '</span>';

	return array( 'required' => $notice ) + $links;
}
add_filter( 'plugin_action_links', 'endeavour_required_plugin_links', 10, 2 );
add_filter( 'network_admin_plugin_action_links', 'endeavour_required_plugin_links', 10, 2 );

/**
 * Keep the plugin in the active list when a deactivate request removes it.
 *
 * @param mixed $value Proposed active plugins.
 * @return mixed
 */
function endeavour_keep_plugin_active( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	$plugin = plugin_basename( ENDEAVOUR_FILE );
	if ( ! in_array( $plugin, $value, true ) ) {
		$value[] = $plugin;
	}

	return $value;
}
add_filter( 'pre_update_option_active_plugins', 'endeavour_keep_plugin_active' );

/**
 * Same guard when the plugin is network activated.
 *
 * @param mixed $value Proposed network plugins.
 * @return mixed
 */
function endeavour_keep_plugin_network_active( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	$plugin = plugin_basename( ENDEAVOUR_FILE );
	if ( ! isset( $value[ $plugin ] ) ) {
		$value[ $plugin ] = time();
	}

	return $value;
}
add_filter( 'pre_update_site_option_active_sitewide_plugins', 'endeavour_keep_plugin_network_active' );
