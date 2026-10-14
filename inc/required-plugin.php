<?php
/**
 * This plugin stays active. The Plugins screen explains why.
 *
 * WordPress deactivates a plugin silently while it installs an update, and
 * nothing turns it back on. The filters below stop that at the source. The
 * upgrade hook is a safety net that only edits the option, never loads the file.
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
	if ( ENDEAVOUR_BASENAME !== $plugin_file || ! is_array( $links ) ) {
		return $links;
	}

	unset( $links['deactivate'] );

	$notice = '<span class="endeavour-required-plugin">' . esc_html__( 'This plugin is required for this website', 'endeavour' ) . '</span>';

	return array( 'required' => $notice ) + $links;
}
add_filter( 'plugin_action_links', 'endeavour_required_plugin_links', 10, 2 );
add_filter( 'network_admin_plugin_action_links', 'endeavour_required_plugin_links', 10, 2 );

/**
 * Keep the plugin in the active list when a write would remove it.
 *
 * @param mixed $value Proposed active plugins.
 * @return mixed
 */
function endeavour_keep_plugin_active( $value ) {
	if ( ! is_array( $value ) || in_array( ENDEAVOUR_BASENAME, $value, true ) ) {
		return $value;
	}

	$value[] = ENDEAVOUR_BASENAME;

	return $value;
}
add_filter( 'pre_update_option_active_plugins', 'endeavour_keep_plugin_active' );

/**
 * The same guard for network activation.
 *
 * @param mixed $value Proposed network plugins.
 * @return mixed
 */
function endeavour_keep_plugin_network_active( $value ) {
	if ( ! is_array( $value ) || isset( $value[ ENDEAVOUR_BASENAME ] ) ) {
		return $value;
	}

	$value[ ENDEAVOUR_BASENAME ] = time();

	return $value;
}
add_filter( 'pre_update_site_option_active_sitewide_plugins', 'endeavour_keep_plugin_network_active' );

/**
 * Whether an upgrader hook names this plugin, for single and bulk updates.
 *
 * @param mixed $hook_extra Upgrader hook arguments.
 */
function endeavour_hook_is_our_upgrade( $hook_extra ): bool {
	if ( ! is_array( $hook_extra ) ) {
		return false;
	}

	$plugins = is_array( $hook_extra['plugins'] ?? null ) ? $hook_extra['plugins'] : array();
	if ( isset( $hook_extra['plugin'] ) ) {
		$plugins[] = $hook_extra['plugin'];
	}

	return in_array( ENDEAVOUR_BASENAME, $plugins, true );
}

/**
 * After an update: clear a recovery-mode pause and re-add the plugin to the
 * active list. This edits the option only. It never loads the plugin file,
 * because this request already has it loaded.
 *
 * @param WP_Upgrader $upgrader   Upgrader instance.
 * @param mixed       $hook_extra Upgrader hook arguments.
 */
function endeavour_restore_active_after_upgrade( $upgrader, $hook_extra ) {
	unset( $upgrader );

	if ( ! endeavour_hook_is_our_upgrade( $hook_extra ) || ! is_file( WP_PLUGIN_DIR . '/' . ENDEAVOUR_BASENAME ) ) {
		return;
	}

	if ( function_exists( 'wp_paused_plugins' ) ) {
		wp_paused_plugins()->delete( dirname( ENDEAVOUR_BASENAME ) );
	}

	$active = (array) get_option( 'active_plugins', array() );
	if ( ! in_array( ENDEAVOUR_BASENAME, $active, true ) ) {
		$active[] = ENDEAVOUR_BASENAME;
		sort( $active );
		update_option( 'active_plugins', $active );
	}

	if ( is_multisite() ) {
		$sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( ! isset( $sitewide[ ENDEAVOUR_BASENAME ] ) ) {
			$sitewide[ ENDEAVOUR_BASENAME ] = time();
			update_site_option( 'active_sitewide_plugins', $sitewide );
		}
	}
}
add_action( 'upgrader_process_complete', 'endeavour_restore_active_after_upgrade', 10, 2 );
