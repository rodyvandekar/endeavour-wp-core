<?php
/**
 * Build a signed Endeavour release.
 *
 * Reads the plugin version, checks it against the git tag, and writes
 * build/endeavour.zip and build/manifest.json. GitLab CI uploads both.
 *
 * @package Endeavour
 */

$root = dirname( __DIR__ );

require_once $root . '/inc/update-signature.php';

$tag = getenv( 'CI_COMMIT_TAG' );
if ( ! is_string( $tag ) || '' === $tag ) {
	fwrite( STDERR, "CI_COMMIT_TAG is required, for example v1.2.0\n" );
	exit( 1 );
}

if ( 1 !== preg_match( '/^v(\d+\.\d+\.\d+)$/', $tag, $matches ) ) {
	fwrite( STDERR, "Tag must look like v1.2.0\n" );
	exit( 1 );
}

$version = $matches[1];
$header   = endeavour_release_header_version( $root . '/endeavour.php' );
$constant = endeavour_release_constant_version( $root . '/endeavour.php' );

if ( $header !== $version || $constant !== $version ) {
	fwrite(
		STDERR,
		"Version mismatch. Tag is {$version}, header is {$header}, constant is {$constant}.\n"
	);
	exit( 1 );
}

$secret_b64 = getenv( 'ENDEAVOUR_SIGNING_KEY' );
$secret     = is_string( $secret_b64 ) ? base64_decode( $secret_b64, true ) : false;

if ( ! is_string( $secret ) || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
	fwrite( STDERR, "ENDEAVOUR_SIGNING_KEY must be the base64 secret key.\n" );
	exit( 1 );
}

$project = getenv( 'CI_PROJECT_PATH' );
if ( ! is_string( $project ) || '' === $project ) {
	$project = 'internal/elementor-team/endeavour';
}

$host = getenv( 'CI_SERVER_HOST' );
if ( ! is_string( $host ) || '' === $host ) {
	$host = 'gitlab.pxlwidgets.com';
}

$package = sprintf(
	'https://%s/api/v4/projects/%s/packages/generic/endeavour/%s/endeavour.zip',
	$host,
	rawurlencode( $project ),
	rawurlencode( $tag )
);

$build = $root . '/build';
if ( ! is_dir( $build ) && ! mkdir( $build, 0755, true ) ) {
	fwrite( STDERR, "Could not create the build directory.\n" );
	exit( 1 );
}

$zip_path = $build . '/endeavour.zip';
if ( is_file( $zip_path ) ) {
	unlink( $zip_path );
}

endeavour_release_zip( $root, $zip_path );

$changelog = getenv( 'CI_COMMIT_TAG_MESSAGE' );
if ( ! is_string( $changelog ) ) {
	$changelog = '';
}
$changelog = trim( $changelog );

$payload = array(
	'version'     => $version,
	'tag'         => $tag,
	'package'     => $package,
	'sha256'      => hash_file( 'sha256', $zip_path ),
	'changelog'   => $changelog,
	'requires_php' => '8.2',
	'requires_wp' => '6.4',
);

$manifest = array(
	'payload'   => $payload,
	'signature' => endeavour_update_sign( $payload, $secret ),
);

$written = file_put_contents(
	$build . '/manifest.json',
	json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

if ( false === $written ) {
	fwrite( STDERR, "Could not write the manifest.\n" );
	exit( 1 );
}

echo "Built {$version}\n";

/**
 * Version from the plugin header.
 *
 * @param string $file Main plugin file.
 */
function endeavour_release_header_version( string $file ): string {
	$contents = file_get_contents( $file );
	if ( ! is_string( $contents ) || 1 !== preg_match( '/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$/m', $contents, $matches ) ) {
		return '';
	}

	return $matches[1];
}

/**
 * Version constant from the main plugin file.
 *
 * @param string $file Main plugin file.
 */
function endeavour_release_constant_version( string $file ): string {
	$contents = file_get_contents( $file );
	if ( ! is_string( $contents ) || 1 !== preg_match( "/define\\(\\s*'ENDEAVOUR_VERSION',\\s*'(\\d+\\.\\d+\\.\\d+)'\\s*\\)/", $contents, $matches ) ) {
		return '';
	}

	return $matches[1];
}

/**
 * Zip the plugin as wp-content/plugins/endeavour.
 *
 * @param string $root     Repository root.
 * @param string $zip_path Destination zip.
 */
function endeavour_release_zip( string $root, string $zip_path ): void {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
		fwrite( STDERR, "Could not create the zip.\n" );
		exit( 1 );
	}

	$prefix = 'endeavour/';
	$zip->addEmptyDir( 'endeavour' );
	$zip->addFile( $root . '/endeavour.php', $prefix . 'endeavour.php' );

	foreach ( array( 'inc', 'assets' ) as $directory ) {
		$base     = $root . '/' . $directory;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative = $prefix . $directory . '/' . substr( $item->getPathname(), strlen( $base ) + 1 );
			$relative = str_replace( '\\', '/', $relative );

			if ( str_ends_with( $relative, '.css.map' ) ) {
				continue;
			}

			if ( $item->isDir() ) {
				$zip->addEmptyDir( $relative );
				continue;
			}

			if ( str_ends_with( $relative, 'inc/update-token.php' ) ) {
				continue;
			}

			$zip->addFile( $item->getPathname(), $relative );
		}
	}

	$token_php = endeavour_release_token_php();
	if ( '' !== $token_php ) {
		$zip->addFromString( $prefix . 'inc/update-token.php', $token_php );
	}

	$zip->close();
}

/**
 * Optional read token, written only into the release zip.
 *
 * The CI variable keeps a private registry readable without wp-config.php.
 * A public package registry does not need it.
 */
function endeavour_release_token_php(): string {
	$token = getenv( 'ENDEAVOUR_UPDATE_TOKEN' );
	if ( ! is_string( $token ) ) {
		return '';
	}

	$token = trim( $token );
	if ( '' === $token ) {
		return '';
	}

	return "<?php\nreturn " . var_export( $token, true ) . ";\n";
}
