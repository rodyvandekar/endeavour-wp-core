<?php
/**
 * Offer signed GitHub releases through the WordPress plugin updater.
 *
 * A newer release shows on Plugins and on Dashboard → Updates. WordPress
 * downloads the zip; this file checks the signature and checksum first.
 *
 * Wire format (do not change without a migration): the release asset names
 * `endeavour.zip` and `manifest.json` and the tag pattern `vX.Y.Z` are read by
 * every installed copy, including the ones that are still on older versions.
 *
 * @package Endeavour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/update-signature.php';

const ENDEAVOUR_RELEASE_ZIP      = 'endeavour.zip';
const ENDEAVOUR_RELEASE_MANIFEST = 'manifest.json';
const ENDEAVOUR_STATE_OPTION     = 'endeavour_update_state';
const ENDEAVOUR_CHECK_HOOK       = 'endeavour_update_check';
const ENDEAVOUR_UPDATE_HOST      = 'github.com';
const ENDEAVOUR_API_HOST         = 'api.github.com';

/**
 * Register the update check and the WordPress updater hooks.
 */
function endeavour_updater_bootstrap() {
	add_action( ENDEAVOUR_CHECK_HOOK, 'endeavour_run_update_check' );
	add_filter( 'update_plugins_' . ENDEAVOUR_UPDATE_HOST, 'endeavour_github_update', 10, 4 );
	add_filter( 'plugins_api', 'endeavour_plugin_information', 10, 3 );
	add_filter( 'upgrader_pre_download', 'endeavour_verify_package_download', 10, 4 );
	add_filter( 'upgrader_source_selection', 'endeavour_upgrader_source', 10, 4 );
	add_filter( 'auto_update_plugin', 'endeavour_auto_update_plugin', 10, 2 );
	add_action( 'admin_menu', 'endeavour_register_updates_page' );
	add_action( 'network_admin_menu', 'endeavour_register_updates_page' );
	add_action( 'admin_notices', 'endeavour_update_admin_notice' );
	add_action( 'network_admin_notices', 'endeavour_update_admin_notice' );
	add_action( 'admin_post_endeavour_check_updates', 'endeavour_handle_check_updates' );
	add_action( 'load-update-core.php', 'endeavour_force_check_on_updates_screen' );

	if ( ! wp_installing() && ! wp_next_scheduled( ENDEAVOUR_CHECK_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', ENDEAVOUR_CHECK_HOOK );
	}
}
add_action( 'init', 'endeavour_updater_bootstrap' );

/**
 * Whether the current user may manage plugin updates.
 */
function endeavour_updater_can(): bool {
	return is_multisite() ? current_user_can( 'manage_network' ) : current_user_can( 'update_plugins' );
}

/**
 * Tools → Endeavour (or Network Settings on multisite), for the last check.
 */
function endeavour_register_updates_page() {
	if ( ! endeavour_updater_can() ) {
		return;
	}

	add_submenu_page(
		is_network_admin() ? 'settings.php' : 'tools.php',
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

	return add_query_arg( $args, is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'tools.php' ) );
}

/**
 * WordPress must not install a release by itself.
 *
 * The offer still appears on Plugins and in the update list, so a management
 * tool such as ManageWP can install it.
 *
 * @param bool|null $update Whether to update.
 * @param object    $item   Update offer.
 * @return bool|null
 */
function endeavour_auto_update_plugin( $update, $item ) {
	if ( is_object( $item ) && ENDEAVOUR_BASENAME === ( $item->plugin ?? '' ) ) {
		return false;
	}

	return $update;
}

/**
 * Hourly check. A newer release is written into the Plugins screen cache.
 */
function endeavour_run_update_check() {
	$result = endeavour_fetch_verified_release();
	endeavour_store_update_state( $result );

	if ( is_wp_error( $result ) || ! version_compare( $result['version'], ENDEAVOUR_VERSION, '>' ) ) {
		return;
	}

	delete_site_transient( 'update_plugins' );

	if ( doing_action( ENDEAVOUR_CHECK_HOOK ) ) {
		wp_update_plugins();
	}
}

/**
 * Dashboard → Updates → Check again also asks GitHub right away.
 */
function endeavour_force_check_on_updates_screen() {
	// The check only reads GitHub. The capability check is enough.
	if ( ! isset( $_GET['force-check'] ) || ! endeavour_updater_can() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	endeavour_run_update_check();
}

/**
 * Refresh the stored release when the last check is old.
 *
 * Runs on every update-plugins lookup, so it must be cheap: the TTL keeps it
 * to one GitHub request per window.
 */
function endeavour_maybe_refresh_release() {
	static $done = false;

	if ( $done ) {
		return;
	}
	$done = true;

	$state     = get_option( ENDEAVOUR_STATE_OPTION, array() );
	$state     = is_array( $state ) ? $state : array();
	$checked   = (int) ( $state['checked_at'] ?? 0 );
	$available = (string) ( $state['available'] ?? '' );

	if ( '' !== ( $state['error'] ?? '' ) ) {
		$ttl = 2 * MINUTE_IN_SECONDS;
	} elseif ( '' !== $available && version_compare( $available, ENDEAVOUR_VERSION, '>' ) ) {
		$ttl = 12 * HOUR_IN_SECONDS;
	} else {
		$ttl = HOUR_IN_SECONDS;
	}

	if ( $checked > time() - $ttl ) {
		return;
	}

	endeavour_run_update_check();
}

/**
 * Remember the last check for the Plugins screen and the status page.
 *
 * On a failed check the last verified offer is kept, so a short GitHub
 * outage does not hide a real update.
 *
 * @param array|WP_Error $result Verified payload, or the reason the check failed.
 */
function endeavour_store_update_state( $result ) {
	$previous = get_option( ENDEAVOUR_STATE_OPTION, array() );
	$previous = is_array( $previous ) ? $previous : array();

	$state = array(
		'checked_at' => time(),
		'available'  => '',
		'changelog'  => '',
		'error'      => '',
		'payload'    => array(),
	);

	if ( is_wp_error( $result ) ) {
		$state['error'] = $result->get_error_message();

		if ( is_array( $previous['payload'] ?? null ) ) {
			$state['available'] = (string) ( $previous['available'] ?? '' );
			$state['changelog'] = (string) ( $previous['changelog'] ?? '' );
			$state['payload']   = $previous['payload'];
		}
	} else {
		$state['available'] = $result['version'];
		$state['changelog'] = $result['changelog'];
		$state['payload']   = $result;
	}

	update_option( ENDEAVOUR_STATE_OPTION, $state, false );
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
function endeavour_github_update( $update, $plugin_data, $plugin_file, $locales ) {
	unset( $plugin_data, $locales );

	if ( ENDEAVOUR_BASENAME !== $plugin_file ) {
		return $update;
	}

	endeavour_maybe_refresh_release();

	$payload = endeavour_stored_payload();
	if ( ! $payload || ! version_compare( $payload['version'], ENDEAVOUR_VERSION, '>' ) ) {
		return false;
	}

	return array(
		'slug'               => ENDEAVOUR_SLUG,
		'version'            => $payload['version'],
		'url'                => endeavour_project_url(),
		'package'            => $payload['package'],
		'requires'           => $payload['requires_wp'] ?? ENDEAVOUR_MIN_WP,
		'requires_php'       => $payload['requires_php'] ?? ENDEAVOUR_MIN_PHP,
		'autoupdate'         => false,
		'disable_autoupdate' => true,
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
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) || ENDEAVOUR_SLUG !== $args->slug ) {
		return $result;
	}

	$payload = endeavour_stored_payload();
	$notes   = (string) ( $payload['changelog'] ?? '' );

	return (object) array(
		'name'          => 'Endeavour WP Core',
		'slug'          => ENDEAVOUR_SLUG,
		'version'       => $payload['version'] ?? ENDEAVOUR_VERSION,
		'author'        => '<a href="' . esc_url( ENDEAVOUR_HOMEPAGE ) . '">Endeavour</a>',
		'homepage'      => endeavour_project_url(),
		'requires'      => $payload['requires_wp'] ?? ENDEAVOUR_MIN_WP,
		'requires_php'  => $payload['requires_php'] ?? ENDEAVOUR_MIN_PHP,
		'download_link' => $payload['package'] ?? '',
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

	if ( false !== $reply || ( $hook_extra['plugin'] ?? '' ) !== ENDEAVOUR_BASENAME ) {
		return $reply;
	}

	if ( ! is_string( $package ) ) {
		return endeavour_update_error( 'endeavour_update_package', 'The download does not match the signed release.' );
	}

	// Use the manifest from the last check when it matches. Only fetch again when it does not.
	$payload = endeavour_stored_payload();
	if ( ! $payload || ! hash_equals( $payload['package'], $package ) ) {
		$payload = endeavour_fetch_verified_release();
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
	}

	if ( ! hash_equals( $payload['package'], $package ) ) {
		return endeavour_update_error( 'endeavour_update_package', 'The download does not match the signed release.' );
	}

	if ( version_compare( $payload['version'], ENDEAVOUR_VERSION, '<' ) ) {
		return endeavour_update_error( 'endeavour_update_downgrade', 'The signed release is older than the installed version.' );
	}

	$file = wp_tempnam( ENDEAVOUR_RELEASE_ZIP );
	if ( ! is_string( $file ) || '' === $file ) {
		return endeavour_update_error( 'endeavour_update_temp', 'Could not create a temporary file.' );
	}

	$downloaded = endeavour_update_download( $payload['package'], $file );
	if ( is_wp_error( $downloaded ) ) {
		wp_delete_file( $file );
		return $downloaded;
	}

	$hash = hash_file( 'sha256', $file );
	if ( ! is_string( $hash ) || ! hash_equals( $payload['sha256'], $hash ) ) {
		wp_delete_file( $file );
		return endeavour_update_error( 'endeavour_update_checksum', 'The release checksum does not match.' );
	}

	return $file;
}

/**
 * Install into the folder the plugin already lives in.
 *
 * The zip's top folder can differ from the folder on the site. Renaming to the
 * installed folder keeps the plugin path stable, so WordPress keeps it active.
 *
 * @param string      $source        Selected source directory.
 * @param string      $remote_source Remote source directory.
 * @param WP_Upgrader $upgrader      Upgrader instance.
 * @param array       $hook_extra    Extra arguments.
 * @return string|WP_Error
 */
function endeavour_upgrader_source( $source, $remote_source, $upgrader, $hook_extra ) {
	unset( $upgrader );

	if ( ( $hook_extra['plugin'] ?? '' ) !== ENDEAVOUR_BASENAME ) {
		return $source;
	}

	$folder = dirname( ENDEAVOUR_BASENAME );
	if ( '.' === $folder ) {
		return endeavour_update_error( 'endeavour_update_folder', 'The plugin must live in its own folder.' );
	}

	if ( basename( untrailingslashit( $source ) ) === $folder ) {
		return $source;
	}

	global $wp_filesystem;

	$corrected = trailingslashit( $remote_source ) . $folder;
	if ( $wp_filesystem->move( $source, $corrected, true ) ) {
		return trailingslashit( $corrected );
	}

	return endeavour_update_error( 'endeavour_update_folder', 'The release zip could not be moved into place.' );
}

/**
 * Stored payload from the last successful check.
 *
 * @return array|null
 */
function endeavour_stored_payload() {
	$state   = get_option( ENDEAVOUR_STATE_OPTION, array() );
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
	return 'https://github.com/' . ENDEAVOUR_REPOSITORY;
}

/**
 * A WP_Error with a translated message.
 *
 * @param string $code    Error code.
 * @param string $message English message, passed through __().
 */
function endeavour_update_error( string $code, string $message ): WP_Error {
	return new WP_Error( $code, __( $message, 'endeavour' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
}

/**
 * Latest signed release, or an error.
 *
 * @return array|WP_Error Manifest payload.
 */
function endeavour_fetch_verified_release() {
	$release = endeavour_update_remote_json( endeavour_release_api_url() );
	if ( is_wp_error( $release ) ) {
		return $release;
	}

	$manifest_url = endeavour_release_asset_url( $release, ENDEAVOUR_RELEASE_MANIFEST );
	if ( '' === $manifest_url || ! endeavour_update_url_is_allowed( $manifest_url ) ) {
		return endeavour_update_error( 'endeavour_update_manifest', 'The latest GitHub release has no manifest.json.' );
	}

	$manifest = endeavour_update_remote_json( $manifest_url );
	if ( is_wp_error( $manifest ) ) {
		return $manifest;
	}

	$payload   = isset( $manifest['payload'] ) && is_array( $manifest['payload'] ) ? $manifest['payload'] : array();
	$signature = isset( $manifest['signature'] ) && is_string( $manifest['signature'] ) ? $manifest['signature'] : '';

	if ( ! endeavour_update_verify( $payload, $signature, ENDEAVOUR_UPDATE_PUBLIC_KEY ) ) {
		return endeavour_update_error( 'endeavour_update_signature', 'The release signature does not match.' );
	}

	$valid = endeavour_validate_release_payload( $payload );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	return $payload;
}

/**
 * GitHub API endpoint for the latest release.
 */
function endeavour_release_api_url(): string {
	return 'https://' . ENDEAVOUR_API_HOST . '/repos/' . ENDEAVOUR_REPOSITORY . '/releases/latest';
}

/**
 * Download JSON from this GitHub repository.
 *
 * @param string $url GitHub API or release asset URL.
 * @return array|WP_Error
 */
function endeavour_update_remote_json( string $url ) {
	if ( ! endeavour_update_url_is_allowed( $url ) && ! endeavour_update_release_url_is_allowed( $url ) ) {
		return endeavour_update_error( 'endeavour_update_url', 'Refusing to contact a URL outside this GitHub repository.' );
	}

	$response = wp_remote_get( $url, endeavour_update_http_args() );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return endeavour_update_http_error( $code );
	}

	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) ) {
		return endeavour_update_error( 'endeavour_update_json', 'GitHub did not return JSON.' );
	}

	return $decoded;
}

/**
 * Request arguments for a public GitHub release.
 *
 * @param array $extra Extra arguments for wp_remote_*.
 */
function endeavour_update_http_args( array $extra = array() ): array {
	return array_merge(
		array(
			'timeout'    => 30,
			'user-agent' => 'Endeavour-WP-Core/' . ENDEAVOUR_VERSION,
		),
		$extra
	);
}

/**
 * Asset URL from a GitHub release.
 *
 * @param array  $release Release JSON.
 * @param string $name    Asset file name.
 */
function endeavour_release_asset_url( array $release, string $name ): string {
	$assets = is_array( $release['assets'] ?? null ) ? $release['assets'] : array();

	foreach ( $assets as $asset ) {
		if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === $name && is_string( $asset['browser_download_url'] ?? null ) ) {
			return $asset['browser_download_url'];
		}
	}

	return '';
}

/**
 * Reject a signed payload that does not describe a GitHub release asset.
 *
 * @param array $payload Manifest payload.
 * @return true|WP_Error
 */
function endeavour_validate_release_payload( array $payload ) {
	$version = $payload['version'] ?? '';
	$package = $payload['package'] ?? '';
	$sha     = $payload['sha256'] ?? '';

	if ( ! is_string( $version ) || 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
		return endeavour_update_error( 'endeavour_update_version', 'The release version is invalid.' );
	}

	if ( ! is_string( $sha ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
		return endeavour_update_error( 'endeavour_update_checksum', 'The release checksum is invalid.' );
	}

	if ( ! is_string( $package ) || ! endeavour_update_url_is_allowed( $package ) || ! str_ends_with( $package, '/' . ENDEAVOUR_RELEASE_ZIP ) ) {
		return endeavour_update_error( 'endeavour_update_package', 'The release package URL is not on GitHub.' );
	}

	$requires_php = is_string( $payload['requires_php'] ?? null ) ? $payload['requires_php'] : ENDEAVOUR_MIN_PHP;
	if ( version_compare( PHP_VERSION, $requires_php, '<' ) ) {
		return endeavour_update_error( 'endeavour_update_php', 'This server PHP version is older than the release requires.' );
	}

	return true;
}

/**
 * Release asset URLs stay on this public GitHub repository.
 *
 * @param string $url Package or manifest URL.
 */
function endeavour_update_url_is_allowed( string $url ): bool {
	$parts = endeavour_update_url_parts( $url, ENDEAVOUR_UPDATE_HOST );
	if ( ! $parts ) {
		return false;
	}

	$pattern = '#^/' . preg_quote( ENDEAVOUR_REPOSITORY, '#' ) . '/releases/download/v\d+\.\d+\.\d+/('
		. preg_quote( ENDEAVOUR_RELEASE_ZIP, '#' ) . '|' . preg_quote( ENDEAVOUR_RELEASE_MANIFEST, '#' ) . ')$#';

	return 1 === preg_match( $pattern, $parts['path'] );
}

/**
 * The one release endpoint this plugin calls itself.
 *
 * @param string $url Release URL.
 */
function endeavour_update_release_url_is_allowed( string $url ): bool {
	$parts = endeavour_update_url_parts( $url, ENDEAVOUR_API_HOST );

	return $parts && '/repos/' . ENDEAVOUR_REPOSITORY . '/releases/latest' === $parts['path'];
}

/**
 * HTTPS URL on one allowed GitHub host, without credentials, port or query.
 *
 * @param string $url  URL to inspect.
 * @param string $host Expected host.
 * @return array|null URL parts with a path, or null.
 */
function endeavour_update_url_parts( string $url, string $host ): ?array {
	$parts = wp_parse_url( $url );

	if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || $host !== ( $parts['host'] ?? '' ) ) {
		return null;
	}

	if ( isset( $parts['user'], $parts['pass'], $parts['query'], $parts['port'] ) || ! is_string( $parts['path'] ?? null ) ) {
		return null;
	}

	return $parts;
}

/**
 * Explain a failed GitHub response.
 *
 * @param int $code HTTP status code.
 */
function endeavour_update_http_error( int $code ): WP_Error {
	if ( in_array( $code, array( 401, 403 ), true ) ) {
		return endeavour_update_error( 'endeavour_update_http', 'GitHub refused the download. The release has to be publicly readable.' );
	}

	if ( 404 === $code ) {
		return endeavour_update_error( 'endeavour_update_http', 'GitHub has no public release yet.' );
	}

	return new WP_Error(
		'endeavour_update_http',
		sprintf(
			/* translators: %d: HTTP status code. */
			__( 'GitHub answered with HTTP %d.', 'endeavour' ),
			$code
		)
	);
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
		return endeavour_update_error( 'endeavour_update_url', 'Refusing to download a package outside this GitHub repository.' );
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
		return endeavour_update_http_error( $code );
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

	$state     = get_option( ENDEAVOUR_STATE_OPTION, array() );
	$state     = is_array( $state ) ? $state : array();
	$available = (string) ( $state['available'] ?? '' );
	$error     = (string) ( $state['error'] ?? '' );
	$checked   = (int) ( $state['checked_at'] ?? 0 );
	$notes     = (string) ( $state['changelog'] ?? '' );
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

	$state = get_option( ENDEAVOUR_STATE_OPTION, array() );
	$error = is_array( $state ) ? (string) ( $state['error'] ?? '' ) : '';
	if ( '' === $error ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>' . esc_html( $error ) . '</p></div>';
}
