<?php
/**
 * Sign and verify Endeavour release manifests.
 *
 * This file stays free of WordPress so the release build can use the same
 * rules as the site that installs the release.
 *
 * @package Endeavour
 */

/**
 * Stable JSON for a manifest payload.
 *
 * Object keys are sorted. Lists keep their order. The build and the site
 * must produce the same bytes, because those bytes are what the signature
 * covers.
 *
 * @param array $payload Manifest fields.
 * @throws JsonException When the payload cannot be encoded.
 */
function endeavour_update_canonical( array $payload ): string {
	return json_encode(
		endeavour_update_sort_keys( $payload ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
	);
}

/**
 * Sort associative arrays recursively.
 *
 * @param array $value Payload or a nested value.
 * @return array
 */
function endeavour_update_sort_keys( array $value ): array {
	foreach ( $value as $key => $item ) {
		if ( is_array( $item ) ) {
			$value[ $key ] = endeavour_update_sort_keys( $item );
		}
	}

	if ( array_is_list( $value ) ) {
		return $value;
	}

	ksort( $value );

	return $value;
}

/**
 * Sign a manifest payload.
 *
 * @param array  $payload Manifest fields.
 * @param string $secret  Raw 64-byte Ed25519 secret key.
 * @throws InvalidArgumentException When the key has the wrong length.
 */
function endeavour_update_sign( array $payload, string $secret ): string {
	if ( SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
		throw new InvalidArgumentException( 'The signing key has the wrong length.' );
	}

	return base64_encode(
		sodium_crypto_sign_detached( endeavour_update_canonical( $payload ), $secret )
	);
}

/**
 * Verify a manifest signature.
 *
 * Returns false instead of throwing, so a broken or hostile manifest only
 * fails the update check and never fatals the admin request.
 *
 * @param array  $payload   Manifest fields.
 * @param string $signature Base64 detached signature.
 * @param string $public    Base64 Ed25519 public key.
 */
function endeavour_update_verify( array $payload, string $signature, string $public ): bool {
	if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		return false;
	}

	$decoded_signature = base64_decode( $signature, true );
	$decoded_public    = base64_decode( $public, true );

	if ( ! is_string( $decoded_signature ) || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $decoded_signature ) ) {
		return false;
	}

	if ( ! is_string( $decoded_public ) || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $decoded_public ) ) {
		return false;
	}

	try {
		return sodium_crypto_sign_verify_detached(
			$decoded_signature,
			endeavour_update_canonical( $payload ),
			$decoded_public
		);
	} catch ( JsonException | SodiumException $error ) {
		unset( $error );
		return false;
	}
}
