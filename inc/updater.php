<?php
/**
 * Offer signed GitLab releases through the WordPress plugin updater.
 *
 * A newer release shows on Plugins and on Dashboard → Updates. WordPress
 * downloads the zip; this file checks the signature and checksum first.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/update-signature.php';

/**
 * Public half of the release signing key.
 *
 * The private half lives in the GitLab variable ENDEAVOUR_SIGNING_KEY.
 */
const ENDEAVOUR_UPDATE_PUBLIC_KEY = 'yitLpgI+XyS8Xr77nGDK0aTZDnd8KfjTLAYs5znwTB4=';

if ( ! defined( 'ENDEAVOUR_GITLAB_HOST' ) ) {
	define( 'ENDEAVOUR_GITLAB_HOST', 'gitlab.pxlwidgets.com' );
}

if ( ! defined( 'ENDEAVOUR_GITLAB_PROJECT' ) ) {
	define( 'ENDEAVOUR_GITLAB_PROJECT', 'internal/elementor-team/endeavour' );
}

/**
 * Register the update check and the WordPress updater hooks.
 */
function endeavour_updater_bootstrap() {
	add_action( 'endeavour_update_check', 'endeavour_run_update_check' );
	add_filter( 'update_plugins_' . ENDEAVOUR_GITLAB_HOST, 'endeavour_gitlab_update', 10, 4 );
	add_filter( 'plugins_api', 'endeavour_plugin_information', 10, 3 );
	add_filter( 'upgrader_pre_download', 'endeavour_verify_package_download', 10, 4 );
	add_filter( 'upgrader_source_selection', 'endeavour_upgrader_source', 10, 4 );
	add_filter( 'auto_update_plugin', 'endeavour_auto_update_plugin', 10, 2 );
	add_filter( 'http_request_args', 'endeavour_update_request_args', 10, 2 );
	add_action( 'admin_menu', 'endeavour_register_updates_page' );
	add_action( 'network_admin_menu', 'endeavour_register_updates_page' );
	add_action( 'admin_notices', 'endeavour_update_admin_notice' );
	add_action( 'network_admin_notices', 'endeavour_update_admin_notice' );
	add_action( 'admin_post_endeavour_check_updates', 'endeavour_handle_check_updates' );

	if ( wp_installing() || wp_next_scheduled( 'endeavour_update_check' ) ) {
		return;
	}

	wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'endeavour_update_check' );
}
add_action( 'init', 'endeavour_updater_bootstrap' );

/**
 * Whether the current user may manage plugin updates.
 */
function endeavour_updater_can(): bool {
	if ( is_multisite() ) {
		return current_user_can( 'manage_network' );
	}

	return current_user_can( 'update_plugins' );
}

/**
 * Tools → Endeavour, for the last check and a manual refresh.
 */
function endeavour_register_updates_page() {
	if ( ! endeavour_updater_can() ) {
		return;
	}

	$parent = is_network_admin() ? 'settings.php' : 'tools.php';

	add_submenu_page(
		$parent,
		__( 'Endeavour updates', 'endeavour' ),
		__( 'Endeavour', 'endeavour' ),
		is_multisite() ? 'manage_network' : 'update_plugins',
		'endeavour-updates',
		'endeavour_render_updates_page'
	);
}

/**
 * URL of the update status screen.
 */
function endeavour_updates_page_url(): string {
	$args = array( 'page' => 'endeavour-updates' );

	if ( is_multisite() ) {
		return add_query_arg( $args, network_admin_url( 'settings.php' ) );
	}

	return add_query_arg( $args, admin_url( 'tools.php' ) );
}

/**
 * Honour a forced auto-update flag from wp-config.php.
 *
 * Without that constant, the toggle on the Plugins screen decides.
 *
 * @param bool|null $update Whether to update.
 * @param object    $item   Update offer.
 * @return bool|null
 */
function endeavour_auto_update_plugin( $update, $item ) {
	if ( ! is_object( $item ) || plugin_basename( ENDEAVOUR_FILE ) !== ( $item->plugin ?? '' ) ) {
		return $update;
	}

	if ( defined( 'ENDEAVOUR_AUTO_UPDATE' ) ) {
		return (bool) ENDEAVOUR_AUTO_UPDATE;
	}

	return $update;
}

/**
 * Daily check. The Plugins screen reads the stored result.
 */
function endeavour_run_update_check() {
	endeavour_store_update_state( endeavour_fetch_verified_release() );
}

/**
 * Refresh the stored release when the last check is old.
 */
function endeavour_maybe_refresh_release() {
	static $done = false;

	if ( $done ) {
		return;
	}

	$done  = true;
	$state = get_option( 'endeavour_update_state', array() );
	$checked = isset( $state['checked_at'] ) ? (int) $state['checked_at'] : 0;

	if ( $checked > time() - 12 * HOUR_IN_SECONDS ) {
		return;
	}

	endeavour_run_update_check();
}

/**
 * Remember the last check for the Plugins screen and Tools.
 *
 * @param array|WP_Error $result Verified payload, or the reason the check failed.
 */
function endeavour_store_update_state( $result ) {
	$state = array(
		'checked_at' => time(),
		'available'  => '',
		'changelog'  => '',
		'error'      => '',
		'payload'    => array(),
	);

	if ( is_wp_error( $result ) ) {
		$state['error'] = $result->get_error_message();
		update_option( 'endeavour_update_state', $state, false );
		return;
	}

	$state['available'] = $result['version'];
	$state['changelog'] = $result['changelog'];
	$state['payload']   = $result;
	update_option( 'endeavour_update_state', $state, false );
}

/**
 * Update offer for the Update URI hostname hook.
 *
 * @param array|false $update      Update data, or false.
 * @param array       $plugin_data Plugin headers.
 * @param string      $plugin_file Plugin basename.
 * @param string[]    $locales     Installed locales.
 * @return array|false
 */
function endeavour_gitlab_update( $update, $plugin_data, $plugin_file, $locales ) {
	unset( $locales );

	if ( plugin_basename( ENDEAVOUR_FILE ) !== $plugin_file ) {
		return $update;
	}

	endeavour_maybe_refresh_release();

	$payload = endeavour_stored_payload();
	if ( ! $payload ) {
		return false;
	}

	return array(
		'slug'         => 'endeavour',
		'version'      => $payload['version'],
		'url'          => endeavour_project_url(),
		'package'      => $payload['package'],
		'requires'     => $payload['requires_wp'] ?? '6.4',
		'requires_php' => $payload['requires_php'] ?? '8.2',
	);
}

/**
 * Details modal on the Plugins screen.
 *
 * @param false|object|array $result Response.
 * @param string             $action Requested action.
 * @param object             $args   Request arguments.
 * @return false|object|array
 */
function endeavour_plugin_information( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'endeavour' !== $args->slug ) {
		return $result;
	}

	$payload = endeavour_stored_payload();
	$version = is_array( $payload ) ? $payload['version'] : ENDEAVOUR_VERSION;
	$notes   = is_array( $payload ) && is_string( $payload['changelog'] ?? null ) ? $payload['changelog'] : '';

	return (object) array(
		'name'          => 'Endeavour',
		'slug'          => 'endeavour',
		'version'       => $version,
		'author'        => '<a href="https://endeavour.nl">Endeavour</a>',
		'homepage'      => endeavour_project_url(),
		'requires'      => is_array( $payload ) ? ( $payload['requires_wp'] ?? '6.4' ) : '6.4',
		'requires_php'  => is_array( $payload ) ? ( $payload['requires_php'] ?? '8.2' ) : '8.2',
		'download_link' => is_array( $payload ) ? ( $payload['package'] ?? '' ) : '',
		'sections'      => array(
			'description' => __( 'Design-system behaviour for the Endeavour site.', 'endeavour' ),
			'changelog'   => '' === $notes ? __( 'No release notes.', 'endeavour' ) : nl2br( esc_html( $notes ) ),
		),
	);
}

/**
 * Download the signed zip ourselves and reject a checksum mismatch.
 *
 * @param false|string|WP_Error $reply      Short-circuit result.
 * @param string                $package    Package URL.
 * @param WP_Upgrader           $upgrader   Upgrader instance.
 * @param array                 $hook_extra Extra arguments.
 * @return false|string|WP_Error
 */
function endeavour_verify_package_download( $reply, $package, $upgrader, $hook_extra ) {
	unset( $upgrader );

	if ( false !== $reply ) {
		return $reply;
	}

	if ( ( $hook_extra['plugin'] ?? '' ) !== plugin_basename( ENDEAVOUR_FILE ) ) {
		return $reply;
	}

	$payload = endeavour_fetch_verified_release();
	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	if ( ! is_string( $package ) || strlen( $package ) !== strlen( $payload['package'] ) || ! hash_equals( $payload['package'], $package ) ) {
		return new WP_Error(
			'endeavour_update_package',
			__( 'The download does not match the signed release.', 'endeavour' )
		);
	}

	$file = wp_tempnam( 'endeavour.zip' );
	if ( ! is_string( $file ) || '' === $file ) {
		return new WP_Error(
			'endeavour_update_temp',
			__( 'Could not create a temporary file.', 'endeavour' )
		);
	}

	$downloaded = endeavour_update_download( $payload['package'], $file );
	if ( is_wp_error( $downloaded ) ) {
		wp_delete_file( $file );
		return $downloaded;
	}

	$hash = hash_file( 'sha256', $file );
	if ( ! is_string( $hash ) || ! hash_equals( $payload['sha256'], $hash ) ) {
		wp_delete_file( $file );
		return new WP_Error(
			'endeavour_update_checksum',
			__( 'The release checksum does not match.', 'endeavour' )
		);
	}

	return $file;
}

/**
 * WordPress installs a plugin zip into a folder named after its top directory.
 *
 * @param string      $source        Selected source directory.
 * @param string      $remote_source Remote source directory.
 * @param WP_Upgrader $upgrader      Upgrader instance.
 * @param array       $hook_extra    Extra arguments.
 * @return string|WP_Error
 */
function endeavour_upgrader_source( $source, $remote_source, $upgrader, $hook_extra ) {
	unset( $upgrader );

	if ( ( $hook_extra['plugin'] ?? '' ) !== plugin_basename( ENDEAVOUR_FILE ) ) {
		return $source;
	}

	if ( 'endeavour' === basename( untrailingslashit( $source ) ) ) {
		return $source;
	}

	global $wp_filesystem;

	$corrected = trailingslashit( $remote_source ) . 'endeavour';
	if ( $wp_filesystem->move( $source, $corrected, true ) ) {
		return trailingslashit( $corrected );
	}

	return new WP_Error(
		'endeavour_update_folder',
		__( 'The release zip is not the Endeavour plugin.', 'endeavour' )
	);
}

/**
 * Attach the read token when WordPress contacts the package registry.
 *
 * @param array  $args Request arguments.
 * @param string $url  Request URL.
 * @return array
 */
function endeavour_update_request_args( $args, $url ) {
	if ( ! is_string( $url ) || ! endeavour_update_url_is_allowed( $url ) ) {
		return $args;
	}

	$auth = endeavour_update_http_args();
	if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
		$args['headers'] = array();
	}

	$args['headers'] = array_merge( $args['headers'], $auth['headers'] );

	return $args;
}

/**
 * Stored payload from the last successful check.
 *
 * @return array|null
 */
function endeavour_stored_payload() {
	$state   = get_option( 'endeavour_update_state', array() );
	$payload = is_array( $state ) ? ( $state['payload'] ?? null ) : null;

	if ( ! is_array( $payload ) || ! is_string( $payload['version'] ?? null ) || ! is_string( $payload['package'] ?? null ) ) {
		return null;
	}

	return $payload;
}

/**
 * Project page, without a trailing slash.
 */
function endeavour_project_url(): string {
	return 'https://' . ENDEAVOUR_GITLAB_HOST . '/' . ENDEAVOUR_GITLAB_PROJECT;
}

/**
 * Latest signed release, or an error.
 *
 * @return array|WP_Error Manifest payload.
 */
function endeavour_fetch_verified_release() {
	if ( ! defined( 'ENDEAVOUR_UPDATE_TOKEN' ) || '' === ENDEAVOUR_UPDATE_TOKEN ) {
		return new WP_Error(
			'endeavour_update_token',
			__( 'Set ENDEAVOUR_UPDATE_TOKEN in wp-config.php. Use a project access token with only read_api, or a deploy token written as username:token.', 'endeavour' )
		);
	}

	$url = sprintf(
		'https://%s/api/v4/projects/%s/releases/permalink/latest',
		ENDEAVOUR_GITLAB_HOST,
		rawurlencode( ENDEAVOUR_GITLAB_PROJECT )
	);

	$release = endeavour_update_remote_json( $url );
	if ( is_wp_error( $release ) ) {
		return $release;
	}

	$manifest_url = endeavour_release_asset_url( $release, 'manifest.json' );
	if ( '' === $manifest_url || ! endeavour_update_url_is_allowed( $manifest_url ) ) {
		return new WP_Error(
			'endeavour_update_manifest',
			__( 'The latest GitLab release has no manifest.json.', 'endeavour' )
		);
	}

	$manifest = endeavour_update_remote_json( $manifest_url );
	if ( is_wp_error( $manifest ) ) {
		return $manifest;
	}

	$payload   = isset( $manifest['payload'] ) && is_array( $manifest['payload'] ) ? $manifest['payload'] : array();
	$signature = isset( $manifest['signature'] ) && is_string( $manifest['signature'] ) ? $manifest['signature'] : '';

	if ( ! endeavour_update_verify( $payload, $signature, ENDEAVOUR_UPDATE_PUBLIC_KEY ) ) {
		return new WP_Error(
			'endeavour_update_signature',
			__( 'The release signature does not match.', 'endeavour' )
		);
	}

	$allowed = endeavour_validate_release_payload( $payload );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}

	return $payload;
}

/**
 * Download JSON with the site's read token.
 *
 * @param string $url GitLab API or package URL.
 * @return array|WP_Error
 */
function endeavour_update_remote_json( string $url ) {
	if ( ! endeavour_update_url_is_allowed( $url ) && ! endeavour_update_release_url_is_allowed( $url ) ) {
		return new WP_Error(
			'endeavour_update_url',
			__( 'Refusing to contact a URL outside this GitLab project.', 'endeavour' )
		);
	}

	$response = wp_remote_get( $url, endeavour_update_http_args() );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error(
			'endeavour_update_http',
			sprintf(
				/* translators: %d: HTTP status code. */
				__( 'GitLab answered with HTTP %d.', 'endeavour' ),
				$code
			)
		);
	}

	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) ) {
		return new WP_Error(
			'endeavour_update_json',
			__( 'GitLab did not return JSON.', 'endeavour' )
		);
	}

	return $decoded;
}

/**
 * Request arguments, including auth when a token is configured.
 *
 * A token without a colon is sent as a project access token. A value of
 * username:token is sent as a GitLab deploy token.
 *
 * @param array $extra Extra arguments for wp_remote_*.
 */
function endeavour_update_http_args( array $extra = array() ): array {
	$headers = array();

	if ( defined( 'ENDEAVOUR_UPDATE_TOKEN' ) && '' !== ENDEAVOUR_UPDATE_TOKEN ) {
		$token = ENDEAVOUR_UPDATE_TOKEN;

		if ( str_contains( $token, ':' ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $token );
		} else {
			$headers['PRIVATE-TOKEN'] = $token;
		}
	}

	return array_merge(
		array(
			'timeout' => 30,
			'headers' => $headers,
		),
		$extra
	);
}

/**
 * Asset URL from a GitLab release.
 *
 * @param array  $release Release JSON.
 * @param string $name    Asset link name.
 */
function endeavour_release_asset_url( array $release, string $name ): string {
	$links = $release['assets']['links'] ?? array();
	if ( ! is_array( $links ) ) {
		return '';
	}

	foreach ( $links as $link ) {
		if ( ! is_array( $link ) ) {
			continue;
		}

		if ( ( $link['name'] ?? '' ) === $name && is_string( $link['url'] ?? null ) ) {
			return $link['url'];
		}
	}

	return '';
}

/**
 * Reject a signed payload that does not describe a GitLab package.
 *
 * @param array $payload Manifest payload.
 * @return true|WP_Error
 */
function endeavour_validate_release_payload( array $payload ) {
	$version = $payload['version'] ?? '';
	$package = $payload['package'] ?? '';
	$sha     = $payload['sha256'] ?? '';

	if ( ! is_string( $version ) || 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
		return new WP_Error( 'endeavour_update_version', __( 'The release version is invalid.', 'endeavour' ) );
	}

	if ( ! is_string( $sha ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
		return new WP_Error( 'endeavour_update_checksum', __( 'The release checksum is invalid.', 'endeavour' ) );
	}

	if ( ! is_string( $package ) || ! endeavour_update_url_is_allowed( $package ) ) {
		return new WP_Error( 'endeavour_update_package', __( 'The release package URL is not on GitLab.', 'endeavour' ) );
	}

	if ( isset( $payload['requires_php'] ) && is_string( $payload['requires_php'] ) && version_compare( PHP_VERSION, $payload['requires_php'], '<' ) ) {
		return new WP_Error( 'endeavour_update_php', __( 'This server PHP version is older than the release requires.', 'endeavour' ) );
	}

	return true;
}

/**
 * Package URLs stay on this GitLab host and its package registry.
 *
 * @param string $url Package URL from the manifest.
 */
function endeavour_update_url_is_allowed( string $url ): bool {
	$parts = endeavour_update_url_parts( $url );
	if ( ! $parts ) {
		return false;
	}

	return str_starts_with( $parts['path'], '/api/v4/projects/' )
		&& str_contains( $parts['path'], '/packages/generic/endeavour/' );
}

/**
 * The one release endpoint this plugin calls itself.
 *
 * @param string $url Release URL.
 */
function endeavour_update_release_url_is_allowed( string $url ): bool {
	$parts = endeavour_update_url_parts( $url );
	if ( ! $parts ) {
		return false;
	}

	$project = rawurlencode( ENDEAVOUR_GITLAB_PROJECT );

	return $parts['path'] === '/api/v4/projects/' . $project . '/releases/permalink/latest';
}

/**
 * HTTPS URL on the configured GitLab host.
 *
 * @param string $url URL to inspect.
 * @return array|null URL parts with a path, or null.
 */
function endeavour_update_url_parts( string $url ): ?array {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return null;
	}

	if ( 'https' !== ( $parts['scheme'] ?? '' ) || ENDEAVOUR_GITLAB_HOST !== ( $parts['host'] ?? '' ) ) {
		return null;
	}

	if ( ! is_string( $parts['path'] ?? null ) ) {
		return null;
	}

	return $parts;
}

/**
 * Stream a package to disk.
 *
 * @param string $url  Package URL.
 * @param string $file Destination path.
 * @return true|WP_Error
 */
function endeavour_update_download( string $url, string $file ) {
	if ( ! endeavour_update_url_is_allowed( $url ) ) {
		return new WP_Error(
			'endeavour_update_url',
			__( 'Refusing to download a package outside this GitLab project.', 'endeavour' )
		);
	}

	$response = wp_remote_get(
		$url,
		endeavour_update_http_args(
			array(
				'timeout'  => 60,
				'stream'   => true,
				'filename' => $file,
			)
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code || ! is_file( $file ) ) {
		return new WP_Error(
			'endeavour_update_http',
			sprintf(
				/* translators: %d: HTTP status code. */
				__( 'GitLab answered with HTTP %d.', 'endeavour' ),
				$code
			)
		);
	}

	return true;
}

/**
 * Refresh the release and return to the status screen.
 */
function endeavour_handle_check_updates() {
	if ( ! endeavour_updater_can() ) {
		wp_die( esc_html__( 'You cannot update plugins.', 'endeavour' ) );
	}

	check_admin_referer( 'endeavour_check_updates' );
	endeavour_run_update_check();
	delete_site_transient( 'update_plugins' );

	wp_safe_redirect( endeavour_updates_page_url() );
	exit;
}

/**
 * Status screen. Installing happens on the Plugins screen.
 */
function endeavour_render_updates_page() {
	if ( ! endeavour_updater_can() ) {
		return;
	}

	$state     = get_option( 'endeavour_update_state', array() );
	$available = is_array( $state ) ? (string) ( $state['available'] ?? '' ) : '';
	$error     = is_array( $state ) ? (string) ( $state['error'] ?? '' ) : '';
	$checked   = is_array( $state ) ? (int) ( $state['checked_at'] ?? 0 ) : 0;
	$notes     = is_array( $state ) ? (string) ( $state['changelog'] ?? '' ) : '';
	$plugins   = is_network_admin() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Endeavour updates', 'endeavour' ) . '</h1>';
	echo '<p>' . esc_html__( 'A newer release appears on the Plugins screen. Use Update there.', 'endeavour' ) . '</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Installed version', 'endeavour' ) . '</th><td>' . esc_html( ENDEAVOUR_VERSION ) . '</td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Available version', 'endeavour' ) . '</th><td>' . esc_html( '' === $available ? '—' : $available ) . '</td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Last check', 'endeavour' ) . '</th><td>' . esc_html( $checked ? wp_date( 'Y-m-d H:i', $checked ) : '—' ) . '</td></tr>';
	echo '</tbody></table>';

	if ( '' !== $error ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
	}

	if ( '' !== $notes ) {
		echo '<h2>' . esc_html__( 'Release notes', 'endeavour' ) . '</h2>';
		echo '<p>' . nl2br( esc_html( $notes ) ) . '</p>';
	}

	echo '<p><a class="button button-primary" href="' . esc_url( $plugins ) . '">' . esc_html__( 'Open Plugins', 'endeavour' ) . '</a> ';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	wp_nonce_field( 'endeavour_check_updates' );
	echo '<input type="hidden" name="action" value="endeavour_check_updates" />';
	submit_button( __( 'Check again', 'endeavour' ), 'secondary', 'submit', false );
	echo '</form></p>';
	echo '</div>';
}

/**
 * Show a failed check on the Plugins screen.
 */
function endeavour_update_admin_notice() {
	if ( ! endeavour_updater_can() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network', 'tools_page_endeavour-updates' ), true ) ) {
		return;
	}

	$state = get_option( 'endeavour_update_state', array() );
	$error = is_array( $state ) ? (string) ( $state['error'] ?? '' ) : '';
	if ( '' === $error ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>' . esc_html( $error ) . '</p></div>';
}
