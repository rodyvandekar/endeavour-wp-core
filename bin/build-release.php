<?php
/**
 * Build a signed Endeavour release.
 *
 * Reads the version, slug and requirements from endeavour.php, checks the
 * version against the git tag, and writes build/endeavour.zip and
 * build/manifest.json. GitHub Actions uploads both.
 *
 * @package Endeavour
 */

$root = dirname( __DIR__ );

require_once $root . '/inc/update-signature.php';

$tag = getenv( 'CI_COMMIT_TAG' );
if ( ! is_string( $tag ) || '' === $tag ) {
	$tag = getenv( 'GITHUB_REF_NAME' );
}
if ( ! is_string( $tag ) || '' === $tag ) {
	fwrite( STDERR, "CI_COMMIT_TAG is required, for example v1.2.0\n" );
	exit( 1 );
}

if ( 1 !== preg_match( '/^v(\d+\.\d+\.\d+)$/', $tag, $matches ) ) {
	fwrite( STDERR, "Tag must look like v1.2.0\n" );
	exit( 1 );
}

$version = $matches[1];
$main    = $root . '/endeavour.php';
$header  = endeavour_release_header( $main, 'Version' );
$define  = endeavour_release_define( $main, 'ENDEAVOUR_VERSION' );

if ( $header !== $version || $define !== $version ) {
	fwrite(
		STDERR,
		"Version mismatch. Tag is {$version}, header is {$header}, constant is {$define}.\n"
	);
	exit( 1 );
}

$slug = endeavour_release_define( $main, 'ENDEAVOUR_SLUG' );
if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
	fwrite( STDERR, "ENDEAVOUR_SLUG must be a lowercase folder name.\n" );
	exit( 1 );
}

$secret_b64 = getenv( 'ENDEAVOUR_SIGNING_KEY' );
$secret     = is_string( $secret_b64 ) ? base64_decode( $secret_b64, true ) : false;

if ( ! is_string( $secret ) || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
	fwrite( STDERR, "ENDEAVOUR_SIGNING_KEY must be the base64 secret key.\n" );
	exit( 1 );
}

$repository = getenv( 'GITHUB_REPOSITORY' );
if ( ! is_string( $repository ) || 1 !== preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository ) ) {
	$repository = endeavour_release_define( $main, 'ENDEAVOUR_REPOSITORY' );
}

$build = $root . '/build';
if ( ! is_dir( $build ) && ! mkdir( $build, 0755, true ) ) {
	fwrite( STDERR, "Could not create the build directory.\n" );
	exit( 1 );
}

$zip_path = $build . '/endeavour.zip';
if ( is_file( $zip_path ) ) {
	unlink( $zip_path );
}

endeavour_release_zip( $root, $zip_path, $slug );

$changelog = getenv( 'CI_COMMIT_TAG_MESSAGE' );
$changelog = is_string( $changelog ) ? trim( $changelog ) : '';

$payload = array(
	'version'      => $version,
	'tag'          => $tag,
	'package'      => sprintf( 'https://github.com/%s/releases/download/%s/endeavour.zip', $repository, $tag ),
	'sha256'       => hash_file( 'sha256', $zip_path ),
	'changelog'    => $changelog,
	'requires_php' => endeavour_release_header( $main, 'Requires PHP' ),
	'requires_wp'  => endeavour_release_header( $main, 'Requires at least' ),
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

echo "Built {$version} as {$slug}\n";

/**
 * A `Name: value` line from the plugin header.
 *
 * @param string $file Main plugin file.
 * @param string $name Header name, for example Version.
 */
function endeavour_release_header( string $file, string $name ): string {
	$contents = file_get_contents( $file );
	$pattern  = '/^\s*\*\s*' . preg_quote( $name, '/' ) . ':\s*(\S+)\s*$/m';

	if ( ! is_string( $contents ) || 1 !== preg_match( $pattern, $contents, $matches ) ) {
		fwrite( STDERR, "Missing header {$name} in endeavour.php.\n" );
		exit( 1 );
	}

	return $matches[1];
}

/**
 * A `define( 'NAME', 'value' )` line from the main plugin file.
 *
 * @param string $file Main plugin file.
 * @param string $name Constant name.
 */
function endeavour_release_define( string $file, string $name ): string {
	$contents = file_get_contents( $file );
	$pattern  = "/define\\(\\s*'" . preg_quote( $name, '/' ) . "',\\s*'([^']*)'\\s*\\)/";

	if ( ! is_string( $contents ) || 1 !== preg_match( $pattern, $contents, $matches ) ) {
		fwrite( STDERR, "Missing constant {$name} in endeavour.php.\n" );
		exit( 1 );
	}

	return $matches[1];
}

/**
 * Zip the plugin as wp-content/plugins/<slug>.
 *
 * @param string $root     Repository root.
 * @param string $zip_path Destination zip.
 * @param string $slug     Folder name inside the zip.
 */
function endeavour_release_zip( string $root, string $zip_path, string $slug ): void {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
		fwrite( STDERR, "Could not create the zip.\n" );
		exit( 1 );
	}

	$prefix = $slug . '/';
	$zip->addEmptyDir( $slug );
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

			$zip->addFile( $item->getPathname(), $relative );
		}
	}

	$zip->close();
}
