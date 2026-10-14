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
	if ( ! is_array( $value ) || ! empty( $GLOBALS['endeavour_upgrading'] ) ) {
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
	if ( ! is_array( $value ) || ! empty( $GLOBALS['endeavour_upgrading'] ) ) {
		return $value;
	}

	$plugin = plugin_basename( ENDEAVOUR_FILE );
	if ( ! isset( $value[ $plugin ] ) ) {
		$value[ $plugin ] = time();
	}

	return $value;
}
add_filter( 'pre_update_site_option_active_sitewide_plugins', 'endeavour_keep_plugin_network_active' );

/**
 * Plugin basenames named by an upgrader hook.
 *
 * @param mixed $hook_extra Upgrader hook arguments.
 * @return string[]
 */
function endeavour_upgrade_plugin_files( $hook_extra ) {
	if ( ! is_array( $hook_extra ) ) {
		return array();
	}

	$plugins = array();
	if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
		$plugins = $hook_extra['plugins'];
	}
	if ( isset( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
		$plugins[] = $hook_extra['plugin'];
	}

	return $plugins;
}

/**
 * Whether this upgrader hook is installing a new copy of this plugin.
 *
 * @param mixed $hook_extra Upgrader hook arguments.
 */
function endeavour_hook_is_our_upgrade( $hook_extra ): bool {
	return in_array( plugin_basename( ENDEAVOUR_FILE ), endeavour_upgrade_plugin_files( $hook_extra ), true );
}

/**
 * Let WordPress deactivate this plugin for the few seconds it replaces the files.
 *
 * @param mixed $response   Install response so far.
 * @param mixed $hook_extra Upgrader hook arguments.
 * @return mixed
 */
function endeavour_begin_plugin_upgrade( $response, $hook_extra ) {
	if ( ! endeavour_hook_is_our_upgrade( $hook_extra ) ) {
		return $response;
	}

	$plugin = plugin_basename( ENDEAVOUR_FILE );

	$GLOBALS['endeavour_upgrading']          = true;
	$GLOBALS['endeavour_was_network_active'] = function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin );

	return $response;
}
add_filter( 'upgrader_pre_install', 'endeavour_begin_plugin_upgrade', 1, 2 );

/**
 * Turn the plugin back on when the new files are in place.
 *
 * @param WP_Upgrader $upgrader   Upgrader instance.
 * @param mixed       $hook_extra Upgrader hook arguments.
 */
function endeavour_reactivate_after_upgrade( $upgrader, $hook_extra ) {
	unset( $upgrader );

	if ( ! endeavour_hook_is_our_upgrade( $hook_extra ) ) {
		return;
	}

	$GLOBALS['endeavour_upgrading'] = false;

	if ( ! function_exists( 'activate_plugin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin = plugin_basename( ENDEAVOUR_FILE );
	if ( function_exists( 'wp_paused_plugins' ) ) {
		wp_paused_plugins()->delete( dirname( $plugin ) );
	}

	if ( is_wp_error( validate_plugin( $plugin ) ) ) {
		return;
	}

	$network = ! empty( $GLOBALS['endeavour_was_network_active'] );
	if ( $network ) {
		if ( ! is_plugin_active_for_network( $plugin ) ) {
			activate_plugin( $plugin, '', true, true );
		}
		return;
	}

	if ( ! is_plugin_active( $plugin ) ) {
		activate_plugin( $plugin, '', false, true );
	}
}
add_action( 'upgrader_process_complete', 'endeavour_reactivate_after_upgrade', 10, 2 );
