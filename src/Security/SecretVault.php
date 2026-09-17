<?php
declare(strict_types=1);

namespace WordPressFetch\Security;

final class SecretVault {
	public function available(): bool {
		return function_exists( 'sodium_crypto_secretbox' ); }

	public function encrypt( string $plaintext ): string {
		if ( ! $this->available() ) {
			throw new \RuntimeException( 'Libsodium is required for credential storage.' ); }
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $this->key() );
		return 'v1:' . base64_encode( $nonce . $cipher );
	}

	public function decrypt( string $encoded ): string {
		if ( ! str_starts_with( $encoded, 'v1:' ) || ! $this->available() ) {
			return ''; }
		$raw = base64_decode( substr( $encoded, 3 ), true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return ''; }
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $this->key() );
		return false === $plain ? '' : $plain;
	}

	private function key(): string {
		$material = defined( 'WPFETCH_SECRET_KEY' ) ? (string) WPFETCH_SECRET_KEY : wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		return sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
