<?php
/**
 * Install signed GitLab releases of this must-use plugin.
 *
 * WordPress only updates plugins in wp-content/plugins. This screen lives
 * under Tools and writes back into wp-content/mu-plugins, after the release
 * signature and the zip checksum both match.
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
 * Register the daily check and the Tools screen.
 */
function endeavour_updater_bootstrap() {
	add_action( 'endeavour_update_check', 'endeavour_run_update_check' );
	add_action( 'admin_menu', 'endeavour_register_updates_page' );
	add_action( 'network_admin_menu', 'endeavour_register_updates_page' );
	add_action( 'admin_notices', 'endeavour_update_admin_notice' );
	add_action( 'network_admin_notices', 'endeavour_update_admin_notice' );
	add_action( 'admin_post_endeavour_check_updates', 'endeavour_handle_check_updates' );
	add_action( 'admin_post_endeavour_install_update', 'endeavour_handle_install_update' );
	add_action( 'admin_post_endeavour_save_update_settings', 'endeavour_handle_save_update_settings' );
	add_action( 'admin_post_endeavour_rollback_update', 'endeavour_handle_rollback_update' );
	add_action( 'after_plugin_row_endeavour.php', 'endeavour_mu_update_row', 10, 2 );

	if ( wp_installing() || wp_next_scheduled( 'endeavour_update_check' ) ) {
		return;
	}

	wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'endeavour_update_check' );
}
add_action( 'init', 'endeavour_updater_bootstrap' );

/**
 * Whether the current user may install a release.
 */
function endeavour_updater_can(): bool {
	if ( is_multisite() ) {
		return current_user_can( 'manage_network' );
	}

	return current_user_can( 'update_plugins' );
}

/**
 * Tools → Endeavour, or the network settings menu on multisite.
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
 * URL of the update screen.
 */
function endeavour_updates_page_url(): string {
	$args = array( 'page' => 'endeavour-updates' );

	if ( is_multisite() ) {
		return add_query_arg( $args, network_admin_url( 'settings.php' ) );
	}

	return add_query_arg( $args, admin_url( 'tools.php' ) );
}

/**
 * Automatic installs are on when the site forces them, or the checkbox is set.
 */
function endeavour_auto_update_enabled(): bool {
	if ( defined( 'ENDEAVOUR_AUTO_UPDATE' ) ) {
		return (bool) ENDEAVOUR_AUTO_UPDATE;
	}

	return 'yes' === get_option( 'endeavour_auto_update', '' );
}

/**
 * Version currently on disk.
 */
function endeavour_installed_version(): string {
	if ( function_exists( 'get_mu_plugins' ) ) {
		$plugins = get_mu_plugins();

		if ( isset( $plugins['endeavour.php']['Version'] ) && is_string( $plugins['endeavour.php']['Version'] ) ) {
			return $plugins['endeavour.php']['Version'];
		}
	}

	return ENDEAVOUR_VERSION;
}

/**
 * Daily check. Installs as well when automatic updates are on.
 */
function endeavour_run_update_check() {
	$result = endeavour_fetch_verified_release();
	endeavour_store_update_state( $result );

	if ( is_wp_error( $result ) || ! endeavour_auto_update_enabled() ) {
		return;
	}

	$available = $result['version'];
	if ( version_compare( $available, endeavour_installed_version(), '<=' ) ) {
		return;
	}

	$installed = endeavour_install_release( $result );
	if ( is_wp_error( $installed ) ) {
		$state            = get_option( 'endeavour_update_state', array() );
		$state['error']   = $installed->get_error_message();
		$state['available'] = $available;
		update_option( 'endeavour_update_state', $state, false );
	}
}

/**
 * Remember the last check for the Tools screen.
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

	$project = rawurlencode( ENDEAVOUR_GITLAB_PROJECT );
	$url     = sprintf(
		'https://%s/api/v4/projects/%s/releases/permalink/latest',
		ENDEAVOUR_GITLAB_HOST,
		$project
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

	$args = array(
		'timeout' => 30,
		'headers' => $headers,
	);

	return array_merge( $args, $extra );
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
 * Download, verify and install a verified release payload.
 *
 * @param array $payload Signed manifest payload.
 * @return true|WP_Error
 */
function endeavour_install_release( array $payload ) {
	$allowed = endeavour_validate_release_payload( $payload );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}

	if ( get_transient( 'endeavour_update_lock' ) ) {
		return new WP_Error( 'endeavour_update_lock', __( 'An update is already running.', 'endeavour' ) );
	}

	set_transient( 'endeavour_update_lock', '1', 15 * MINUTE_IN_SECONDS );

	try {
		$zip_path = endeavour_update_workdir() . '/endeavour.zip';
		$staged   = endeavour_update_workdir() . '/stage';
		endeavour_update_delete_tree( $staged );
		wp_mkdir_p( $staged );

		$downloaded = endeavour_update_download( $payload['package'], $zip_path );
		if ( is_wp_error( $downloaded ) ) {
			return $downloaded;
		}

		$sha = hash_file( 'sha256', $zip_path );
		if ( ! is_string( $sha ) || ! hash_equals( $payload['sha256'], $sha ) ) {
			return new WP_Error( 'endeavour_update_checksum', __( 'The downloaded zip does not match the signed checksum.', 'endeavour' ) );
		}

		$extracted = endeavour_update_extract( $zip_path, $staged );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}

		if ( ! endeavour_update_lint_tree( $staged ) ) {
			return new WP_Error( 'endeavour_update_lint', __( 'The release contains a PHP file that does not parse.', 'endeavour' ) );
		}

		endeavour_update_replace_plugin( $staged );
		update_option(
			'endeavour_update_state',
			array(
				'checked_at' => time(),
				'available'  => $payload['version'],
				'changelog'  => isset( $payload['changelog'] ) && is_string( $payload['changelog'] ) ? $payload['changelog'] : '',
				'error'      => '',
				'payload'    => array(),
			),
			false
		);

		return true;
	} catch ( Throwable $error ) {
		return new WP_Error( 'endeavour_update_failed', $error->getMessage() );
	} finally {
		delete_transient( 'endeavour_update_lock' );
	}
}

/**
 * Writable directory under wp-content/upgrade.
 */
function endeavour_update_workdir(): string {
	$dir = WP_CONTENT_DIR . '/upgrade/endeavour-update';
	if ( ! wp_mkdir_p( $dir ) ) {
		throw new RuntimeException( __( 'Could not create the update directory.', 'endeavour' ) );
	}

	return $dir;
}

/**
 * Stream a URL to disk.
 *
 * @param string $url  Allowed package URL.
 * @param string $file Destination.
 * @return true|WP_Error
 */
function endeavour_update_download( string $url, string $file ) {
	if ( ! endeavour_update_url_is_allowed( $url ) ) {
		return new WP_Error( 'endeavour_update_package', __( 'The release package URL is not on GitLab.', 'endeavour' ) );
	}

	if ( is_file( $file ) ) {
		unlink( $file );
	}

	$response = wp_remote_get(
		$url,
		endeavour_update_http_args(
			array(
				'timeout'  => 120,
				'stream'   => true,
				'filename' => $file,
			)
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_file( $file ) ) {
		return new WP_Error( 'endeavour_update_download', __( 'Could not download the release zip.', 'endeavour' ) );
	}

	return true;
}

/**
 * Extract a zip that contains only the loader and the plugin directory.
 *
 * @param string $zip_path Zip path.
 * @param string $staged   Empty destination directory.
 * @return true|WP_Error
 */
function endeavour_update_extract( string $zip_path, string $staged ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return new WP_Error( 'endeavour_update_zip', __( 'Could not open the release zip.', 'endeavour' ) );
	}

	for ( $index = 0; $index < $zip->numFiles; $index++ ) {
		$name = $zip->getNameIndex( $index );
		if ( ! is_string( $name ) || ! endeavour_update_zip_name_is_safe( $name ) ) {
			$zip->close();
			return new WP_Error( 'endeavour_update_zip', __( 'The release zip contains an unexpected path.', 'endeavour' ) );
		}
	}

	if ( ! $zip->extractTo( $staged ) ) {
		$zip->close();
		return new WP_Error( 'endeavour_update_zip', __( 'Could not extract the release zip.', 'endeavour' ) );
	}

	$zip->close();

	if ( ! is_file( $staged . '/endeavour.php' ) || ! is_dir( $staged . '/endeavour' ) ) {
		return new WP_Error( 'endeavour_update_zip', __( 'The release zip is missing the plugin files.', 'endeavour' ) );
	}

	return true;
}

/**
 * Reject absolute paths and paths that climb out of the zip.
 *
 * @param string $name Zip entry name.
 */
function endeavour_update_zip_name_is_safe( string $name ): bool {
	$normal = str_replace( '\\', '/', $name );

	if ( '' === $normal || str_starts_with( $normal, '/' ) || str_contains( $normal, '../' ) ) {
		return false;
	}

	return 'endeavour.php' === $normal
		|| 'endeavour' === $normal
		|| str_starts_with( $normal, 'endeavour/' );
}

/**
 * Back up the live plugin and replace it with the staged release.
 *
 * @param string $staged Directory that holds the extracted release.
 */
function endeavour_update_replace_plugin( string $staged ) {
	$backup = endeavour_update_backup_dir();
	endeavour_update_delete_tree( $backup );
	wp_mkdir_p( $backup );

	$live_loader = WPMU_PLUGIN_DIR . '/endeavour.php';
	$live_dir    = WPMU_PLUGIN_DIR . '/endeavour';

	if ( is_file( $live_loader ) ) {
		copy( $live_loader, $backup . '/endeavour.php' );
	}

	if ( is_dir( $live_dir ) ) {
		endeavour_update_copy_tree( $live_dir, $backup . '/endeavour' );
	}

	$swapped = false;

	try {
		endeavour_update_delete_tree( $live_dir );
		$swapped = true;
		endeavour_update_copy_tree( $staged . '/endeavour', $live_dir );

		if ( ! copy( $staged . '/endeavour.php', $live_loader ) ) {
			throw new RuntimeException( __( 'Could not replace the plugin loader.', 'endeavour' ) );
		}
	} catch ( Throwable $error ) {
		if ( $swapped ) {
			endeavour_update_restore_backup();
		}

		throw $error;
	}

	endeavour_update_invalidate_cache( $live_loader );
	endeavour_update_invalidate_cache( $live_dir . '/endeavour.php' );
}

/**
 * Directory that holds the previous install.
 */
function endeavour_update_backup_dir(): string {
	return WP_CONTENT_DIR . '/upgrade/endeavour-backup';
}

/**
 * Put the backup back when a release does not parse.
 *
 * @return true|WP_Error
 */
function endeavour_update_restore_backup() {
	$backup = endeavour_update_backup_dir();
	if ( ! is_file( $backup . '/endeavour.php' ) || ! is_dir( $backup . '/endeavour' ) ) {
		return new WP_Error( 'endeavour_update_backup', __( 'There is no backup to restore.', 'endeavour' ) );
	}

	try {
		endeavour_update_delete_tree( WPMU_PLUGIN_DIR . '/endeavour' );
		endeavour_update_copy_tree( $backup . '/endeavour', WPMU_PLUGIN_DIR . '/endeavour' );
		copy( $backup . '/endeavour.php', WPMU_PLUGIN_DIR . '/endeavour.php' );
		return true;
	} catch ( Throwable $error ) {
		return new WP_Error( 'endeavour_update_backup', $error->getMessage() );
	}
}

/**
 * Parse every PHP file in a staged release before it replaces the live one.
 *
 * @param string $root Staged release root.
 */
function endeavour_update_lint_tree( string $root ): bool {
	$files = array( $root . '/endeavour.php' );
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root . '/endeavour', FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $item ) {
		if ( $item->isFile() && str_ends_with( $item->getFilename(), '.php' ) ) {
			$files[] = $item->getPathname();
		}
	}

	foreach ( $files as $file ) {
		if ( ! endeavour_update_lint( $file ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Parse a PHP file when the shell is available.
 *
 * @param string $file Absolute path.
 */
function endeavour_update_lint( string $file ): bool {
	if ( ! function_exists( 'exec' ) || ! defined( 'PHP_BINARY' ) || '' === PHP_BINARY ) {
		return true;
	}

	$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
	if ( in_array( 'exec', $disabled, true ) ) {
		return true;
	}

	$output = array();
	$status = 1;
	exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file ), $output, $status );

	return 0 === $status;
}

/**
 * Drop a cached bytecode copy of a replaced file.
 *
 * @param string $file Absolute path.
 */
function endeavour_update_invalidate_cache( string $file ) {
	if ( function_exists( 'opcache_invalidate' ) ) {
		opcache_invalidate( $file, true );
	}
}

/**
 * Copy a directory.
 *
 * @param string $from Source directory.
 * @param string $to   Destination directory.
 */
function endeavour_update_copy_tree( string $from, string $to ) {
	$from = rtrim( $from, '/' );
	$to   = rtrim( $to, '/' );

	if ( ! wp_mkdir_p( $to ) ) {
		throw new RuntimeException( __( 'Could not create a directory for the release.', 'endeavour' ) );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$target = $to . substr( $item->getPathname(), strlen( $from ) );

		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
				throw new RuntimeException( __( 'Could not create a directory for the release.', 'endeavour' ) );
			}
			continue;
		}

		if ( ! copy( $item->getPathname(), $target ) ) {
			throw new RuntimeException( __( 'Could not copy a release file.', 'endeavour' ) );
		}
	}
}

/**
 * Delete a directory that sits inside the upgrade folder or the plugin folder.
 *
 * @param string $path Absolute directory.
 */
function endeavour_update_delete_tree( string $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$real    = realpath( $path );
	$upgrade = realpath( WP_CONTENT_DIR . '/upgrade' );
	$mu      = realpath( WPMU_PLUGIN_DIR );
	$allowed = false;

	foreach ( array( $upgrade, $mu ) as $parent ) {
		if ( is_string( $real ) && is_string( $parent ) && str_starts_with( $real, $parent . '/' ) ) {
			$allowed = true;
		}
	}

	if ( ! $allowed ) {
		throw new RuntimeException( __( 'Refusing to delete a directory outside the plugin update.', 'endeavour' ) );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
			continue;
		}

		unlink( $item->getPathname() );
	}

	rmdir( $path );
}

/**
 * Stop a request that is not an admin update action.
 */
function endeavour_update_guard_action( string $action ) {
	if ( ! endeavour_updater_can() ) {
		wp_die( esc_html__( 'You are not allowed to update Endeavour.', 'endeavour' ) );
	}

	check_admin_referer( $action );
}

/**
 * Send the user back to the update screen with a result.
 *
 * @param string $code    Result slug.
 * @param string $message Optional error text.
 */
function endeavour_update_redirect( string $code, string $message = '' ) {
	if ( '' !== $message ) {
		set_transient( 'endeavour_update_notice', $message, MINUTE_IN_SECONDS );
	}

	wp_safe_redirect( add_query_arg( 'endeavour-update', $code, endeavour_updates_page_url() ) );
	exit;
}

/**
 * Check for a release from the Tools screen.
 */
function endeavour_handle_check_updates() {
	endeavour_update_guard_action( 'endeavour_check_updates' );
	endeavour_store_update_state( endeavour_fetch_verified_release() );
	endeavour_update_redirect( 'checked' );
}

/**
 * Install the release that was just verified.
 */
function endeavour_handle_install_update() {
	endeavour_update_guard_action( 'endeavour_install_update' );

	$result = endeavour_fetch_verified_release();
	if ( is_wp_error( $result ) ) {
		endeavour_store_update_state( $result );
		endeavour_update_redirect( 'failed', $result->get_error_message() );
	}

	if ( version_compare( $result['version'], endeavour_installed_version(), '<=' ) ) {
		endeavour_update_redirect( 'current' );
	}

	$installed = endeavour_install_release( $result );
	if ( is_wp_error( $installed ) ) {
		endeavour_update_redirect( 'failed', $installed->get_error_message() );
	}

	endeavour_update_redirect( 'updated' );
}

/**
 * Save the automatic-update checkbox.
 */
function endeavour_handle_save_update_settings() {
	endeavour_update_guard_action( 'endeavour_save_update_settings' );

	if ( defined( 'ENDEAVOUR_AUTO_UPDATE' ) ) {
		endeavour_update_redirect( 'settings' );
	}

	$enabled = isset( $_POST['endeavour_auto_update'] ) ? 'yes' : '';
	update_option( 'endeavour_auto_update', $enabled, false );
	endeavour_update_redirect( 'settings' );
}

/**
 * Restore the previous install.
 */
function endeavour_handle_rollback_update() {
	endeavour_update_guard_action( 'endeavour_rollback_update' );
	$restored = endeavour_update_restore_backup();

	if ( is_wp_error( $restored ) ) {
		endeavour_update_redirect( 'failed', $restored->get_error_message() );
	}

	endeavour_update_redirect( 'rolled-back' );
}

/**
 * Tools screen.
 */
function endeavour_render_updates_page() {
	if ( ! endeavour_updater_can() ) {
		return;
	}

	$state     = get_option( 'endeavour_update_state', array() );
	$installed = endeavour_installed_version();
	$available = isset( $state['available'] ) && is_string( $state['available'] ) ? $state['available'] : '';
	$has_newer = '' !== $available && version_compare( $available, $installed, '>' );
	$backup    = is_file( endeavour_update_backup_dir() . '/endeavour.php' );
	$notice    = get_transient( 'endeavour_update_notice' );
	if ( is_string( $notice ) ) {
		delete_transient( 'endeavour_update_notice' );
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Endeavour updates', 'endeavour' ) . '</h1>';
	endeavour_render_update_result( $notice );

	echo '<table class="widefat striped" style="max-width:720px"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Installed version', 'endeavour' ) . '</th><td>' . esc_html( $installed ) . '</td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Available version', 'endeavour' ) . '</th><td>';
	echo '' === $available ? esc_html__( 'Not checked yet', 'endeavour' ) : esc_html( $available );
	echo '</td></tr>';

	if ( ! empty( $state['checked_at'] ) ) {
		echo '<tr><th scope="row">' . esc_html__( 'Last checked', 'endeavour' ) . '</th><td>' . esc_html( wp_date( 'j F Y H:i', (int) $state['checked_at'] ) ) . '</td></tr>';
	}

	if ( ! empty( $state['error'] ) && is_string( $state['error'] ) ) {
		echo '<tr><th scope="row">' . esc_html__( 'Last error', 'endeavour' ) . '</th><td>' . esc_html( $state['error'] ) . '</td></tr>';
	}

	echo '</tbody></table>';

	if ( $has_newer && ! empty( $state['changelog'] ) && is_string( $state['changelog'] ) ) {
		echo '<h2>' . esc_html__( 'Changes', 'endeavour' ) . '</h2>';
		echo '<pre style="max-width:720px;white-space:pre-wrap">' . esc_html( $state['changelog'] ) . '</pre>';
	}

	echo '<p>';
	endeavour_render_post_button(
		'endeavour_check_updates',
		__( 'Check again', 'endeavour' ),
		'secondary'
	);

	if ( $has_newer ) {
		echo ' ';
		endeavour_render_post_button(
			'endeavour_install_update',
			__( 'Update now', 'endeavour' ),
			'primary'
		);
	}
	echo '</p>';

	echo '<hr /><h2>' . esc_html__( 'Automatic updates', 'endeavour' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'endeavour_save_update_settings' );
	echo '<input type="hidden" name="action" value="endeavour_save_update_settings" />';
	$forced = defined( 'ENDEAVOUR_AUTO_UPDATE' );
	echo '<label><input type="checkbox" name="endeavour_auto_update" value="yes"';
	checked( endeavour_auto_update_enabled() );
	disabled( $forced );
	echo ' /> ' . esc_html__( 'Install new releases during the daily check.', 'endeavour' ) . '</label>';

	if ( $forced ) {
		echo '<p class="description">' . esc_html__( 'ENDEAVOUR_AUTO_UPDATE in wp-config.php decides this for the site.', 'endeavour' ) . '</p>';
	}

	submit_button( __( 'Save', 'endeavour' ) );
	echo '</form>';

	if ( $backup ) {
		echo '<hr /><h2>' . esc_html__( 'Previous version', 'endeavour' ) . '</h2>';
		echo '<p>' . esc_html__( 'The install from before the last update is kept once.', 'endeavour' ) . '</p>';
		endeavour_render_post_button(
			'endeavour_rollback_update',
			__( 'Restore previous version', 'endeavour' ),
			'secondary'
		);
	}

	echo '</div>';
}

/**
 * Result banner for the redirect that follows an action.
 *
 * @param string|null $error Error text stored for this request.
 */
function endeavour_render_update_result( $error ) {
	$code = isset( $_GET['endeavour-update'] ) ? sanitize_key( wp_unslash( $_GET['endeavour-update'] ) ) : '';
	if ( '' === $code ) {
		return;
	}

	$messages = array(
		'checked'     => __( 'Checked for a release.', 'endeavour' ),
		'updated'     => __( 'Endeavour is updated. Reload this screen if the version has not caught up yet.', 'endeavour' ),
		'current'     => __( 'This site is already on the latest release.', 'endeavour' ),
		'settings'    => __( 'Settings saved.', 'endeavour' ),
		'rolled-back' => __( 'The previous version is restored.', 'endeavour' ),
		'failed'      => is_string( $error ) && '' !== $error ? $error : __( 'The update failed.', 'endeavour' ),
	);

	if ( ! isset( $messages[ $code ] ) ) {
		return;
	}

	$class = 'failed' === $code ? 'notice-error' : 'notice-success';
	echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
}

/**
 * POST button that targets admin-post.php.
 *
 * @param string $action Nonce action and admin-post action.
 * @param string $label  Button label.
 * @param string $class  primary or secondary.
 */
function endeavour_render_post_button( string $action, string $label, string $class ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	wp_nonce_field( $action );
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
	submit_button( $label, $class, 'submit', false );
	echo '</form>';
}

/**
 * Tell an administrator when a release is waiting.
 */
function endeavour_update_admin_notice() {
	if ( ! endeavour_updater_can() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && str_contains( (string) $screen->id, 'endeavour-updates' ) ) {
		return;
	}

	$state     = get_option( 'endeavour_update_state', array() );
	$available = isset( $state['available'] ) && is_string( $state['available'] ) ? $state['available'] : '';

	if ( '' === $available || version_compare( $available, endeavour_installed_version(), '<=' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html( sprintf(
		/* translators: %s: version number. */
		__( 'Endeavour %s is available.', 'endeavour' ),
		$available
	) );
	echo ' <a href="' . esc_url( endeavour_updates_page_url() ) . '">' . esc_html__( 'Update now', 'endeavour' ) . '</a>';
	echo '</p></div>';
}

/**
 * Link from the Must-Use list to this screen. The core Update button writes
 * into wp-content/plugins, so it is not used here.
 *
 * @param string $plugin_file Plugin filename.
 */
function endeavour_mu_update_row( $plugin_file ) {
	if ( 'endeavour.php' !== $plugin_file || ! endeavour_updater_can() ) {
		return;
	}

	$state     = get_option( 'endeavour_update_state', array() );
	$available = isset( $state['available'] ) && is_string( $state['available'] ) ? $state['available'] : '';

	if ( '' === $available || version_compare( $available, endeavour_installed_version(), '<=' ) ) {
		return;
	}

	$columns = function_exists( 'get_column_headers' ) && function_exists( 'get_current_screen' )
		? get_column_headers( get_current_screen() )
		: array();
	$colspan = is_array( $columns ) && $columns ? count( $columns ) : 3;

	echo '<tr class="plugin-update-tr active"><td colspan="' . (int) $colspan . '" class="plugin-update colspanchange">';
	echo '<div class="update-message notice inline notice-warning notice-alt"><p>';
	echo esc_html( sprintf(
		/* translators: %s: version number. */
		__( 'Endeavour %s is available.', 'endeavour' ),
		$available
	) );
	echo ' <a href="' . esc_url( endeavour_updates_page_url() ) . '">' . esc_html__( 'View version details', 'endeavour' ) . '</a>';
	echo '</p></div></td></tr>';
}
