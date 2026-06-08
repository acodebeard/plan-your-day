<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Support;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class DebugLogger {
	public static function is_enabled(): bool {
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			return true;
		}

		if ( function_exists( 'apply_filters' ) ) {
			return true === (bool) apply_filters( 'plan_your_day_debug_logging', false );
		}

		return false;
	}

	public static function log( string $event, array $context = [] ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$encoded_context = json_encode( self::normalize( $context ), JSON_UNESCAPED_SLASHES );

		if ( false === $encoded_context ) {
			$encoded_context = '{"encoding":"failed"}';
		}

		error_log( sprintf( '[plan-your-day] %s %s', $event, $encoded_context ) );
	}

	private static function normalize( mixed $value, string $key = '' ): mixed {
		if ( $value instanceof WP_Error ) {
			return [
				'wp_error' => [
					'code'    => $value->get_error_code(),
					'message' => $value->get_error_message(),
					'data'    => self::normalize( $value->get_error_data(), 'data' ),
				],
			];
		}

		if ( is_array( $value ) ) {
			$normalized = [];

			foreach ( $value as $item_key => $item_value ) {
				$normalized_key                = is_string( $item_key ) ? $item_key : (string) $item_key;
				$normalized[ $normalized_key ] = self::normalize( $item_value, $normalized_key );
			}

			return $normalized;
		}

		if ( is_object( $value ) ) {
			return [
				'object' => get_class( $value ),
			];
		}

		if ( is_string( $value ) ) {
			if ( self::should_redact( $key ) ) {
				return '[redacted]';
			}

			return self::truncate( self::redact_url_secrets( $value ) );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return self::truncate( (string) $value );
		}

		return gettype( $value );
	}

	private static function should_redact( string $key ): bool {
		$key = strtolower( $key );

		foreach ( [ 'token', 'api_key', 'authorization', 'cookie', 'secret' ] as $sensitive_fragment ) {
			if ( str_contains( $key, $sensitive_fragment ) ) {
				return true;
			}
		}

		return false;
	}

	private static function redact_url_secrets( string $value ): string {
		return (string) preg_replace( '/([?&](?:key|api_key|token)=)[^&]+/i', '$1[redacted]', $value );
	}

	private static function truncate( string $value, int $limit = 500 ): string {
		if ( strlen( $value ) <= $limit ) {
			return $value;
		}

		return substr( $value, 0, $limit ) . '...';
	}
}
