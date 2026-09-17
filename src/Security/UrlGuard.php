<?php
declare(strict_types=1);

namespace WordPressFetch\Security;

final class UrlGuard {
	public function validate( string $url ): bool {
		if ( ! wp_http_validate_url( $url ) ) {
			return false; }
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false; }
		$host = rtrim( strtolower( (string) $parts['host'] ), '.' );
		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) || $this->isNumericEncodedHost( $host ) ) {
			return false; }
		$ips = array();
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips[] = $host; } else {
			$records = @dns_get_record( $host, DNS_A | DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! is_array( $records ) || array() === $records ) {
				return false; }
			foreach ( $records as $record ) {
				if ( isset( $record['ip'] ) ) {
					$ips[] = $record['ip'];
				} if ( isset( $record['ipv6'] ) ) {
					$ips[] = $record['ipv6']; }
			}
			}
			foreach ( $ips as $ip ) {
				if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return false; }
			}
			return array() !== $ips;
	}

	private function isNumericEncodedHost( string $host ): bool {
		return 1 === preg_match( '/^(?:0x[0-9a-f]+|[0-9]+)$/i', $host );
	}
}
