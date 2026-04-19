<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Security;

defined( 'ABSPATH' ) || exit;

final class RequestOriginValidator {
	public function is_same_site_request( array $server ): bool {
		$expected_host_header = (string) ( $server['HTTP_HOST'] ?? '' );
		$expected_host        = wp_parse_url( 'http://' . $expected_host_header, PHP_URL_HOST );
		$expected_port        = wp_parse_url( 'http://' . $expected_host_header, PHP_URL_PORT );

		if ( ! is_string( $expected_host ) || '' === $expected_host ) {
			return true;
		}

		$fetch_site = strtolower( trim( (string) ( $server['HTTP_SEC_FETCH_SITE'] ?? '' ) ) );

		if ( '' !== $fetch_site && ! in_array( $fetch_site, [ 'same-origin', 'none' ], true ) ) {
			$fetch_mode = strtolower( trim( (string) ( $server['HTTP_SEC_FETCH_MODE'] ?? '' ) ) );
			$fetch_dest = strtolower( trim( (string) ( $server['HTTP_SEC_FETCH_DEST'] ?? '' ) ) );
			$fetch_user = trim( (string) ( $server['HTTP_SEC_FETCH_USER'] ?? '' ) );

			if (
				'navigate' !== $fetch_mode ||
				'document' !== $fetch_dest ||
				'?1' !== $fetch_user
			) {
				return false;
			}
		}

		foreach ( [ 'HTTP_ORIGIN', 'HTTP_REFERER' ] as $header ) {
			$value = (string) ( $server[ $header ] ?? '' );

			if ( '' === $value ) {
				continue;
			}

			$candidate_host = wp_parse_url( $value, PHP_URL_HOST );
			$candidate_port = wp_parse_url( $value, PHP_URL_PORT );

			if (
				! is_string( $candidate_host ) ||
				0 !== strcasecmp( $candidate_host, $expected_host ) ||
				( null !== $expected_port && $candidate_port !== $expected_port )
			) {
				return false;
			}
		}

		return true;
	}
}
