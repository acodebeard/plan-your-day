<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Security;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class ClientIpResolver {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function resolve( array $server = [] ): string {
		$server = [] !== $server ? $server : $_SERVER;
		$remote = trim( (string) ( $server['REMOTE_ADDR'] ?? '' ) );

		if ( '' === $remote || false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		$trusted_proxies = $this->settings->get_trusted_proxy_cidrs();

		if ( [] === $trusted_proxies || ! $this->ip_matches_any_cidr( $remote, $trusted_proxies ) ) {
			return $remote;
		}

		$forwarded = trim( (string) ( $server['HTTP_X_FORWARDED_FOR'] ?? '' ) );

		if ( '' === $forwarded ) {
			return $remote;
		}

		foreach ( array_reverse( array_map( 'trim', explode( ',', $forwarded ) ) ) as $candidate ) {
			if ( '' === $candidate || false === filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				continue;
			}

			if ( ! $this->ip_matches_any_cidr( $candidate, $trusted_proxies ) ) {
				return $candidate;
			}
		}

		return $remote;
	}

	private function ip_matches_any_cidr( string $ip, array $cidrs ): bool {
		foreach ( $cidrs as $cidr ) {
			if ( $this->ip_in_cidr( $ip, (string) $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	private function ip_in_cidr( string $ip, string $cidr ): bool {
		if ( '' === $cidr ) {
			return false;
		}

		$parts     = explode( '/', $cidr, 2 );
		$subnet    = $parts[0];
		$mask_bits = isset( $parts[1] ) ? (int) $parts[1] : -1;
		$ip_bin    = @inet_pton( $ip );
		$subnet_bin = @inet_pton( $subnet );

		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		if ( $mask_bits < 0 ) {
			return $ip_bin === $subnet_bin;
		}

		$byte_length = strlen( $ip_bin );
		$max_bits    = $byte_length * 8;

		if ( $mask_bits > $max_bits ) {
			return false;
		}

		$full_bytes = intdiv( $mask_bits, 8 );
		$remaining  = $mask_bits % 8;

		if ( $full_bytes > 0 && 0 !== substr_compare( $ip_bin, $subnet_bin, 0, $full_bytes ) ) {
			return false;
		}

		if ( 0 === $remaining ) {
			return true;
		}

		$mask = chr( ( 0xFF << ( 8 - $remaining ) ) & 0xFF );

		return ( ord( $ip_bin[ $full_bytes ] ) & ord( $mask ) ) === ( ord( $subnet_bin[ $full_bytes ] ) & ord( $mask ) );
	}
}
