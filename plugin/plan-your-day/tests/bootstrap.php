<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'PLAN_YOUR_DAY_TEXT_DOMAIN' ) ) {
	define( 'PLAN_YOUR_DAY_TEXT_DOMAIN', 'plan-your-day' );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * 24 * 60 * 60 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 30 * 24 * 60 * 60 );
}

if ( ! defined( 'COOKIEPATH' ) ) {
	define( 'COOKIEPATH', '/' );
}

if ( ! defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN', '' );
}

$GLOBALS['plan_your_day_test_options'] = [];

if ( ! function_exists( '__' ) ) {
	function __( string $text, ?string $domain = null ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strip_tags( (string) $value );
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

		return trim( is_string( $value ) ? $value : '' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strip_tags( (string) $value );
		$value = str_replace( [ "\r\n", "\r" ], "\n", $value );
		$value = preg_replace( "/[^\S\n]+/", ' ', $value );

		return trim( is_string( $value ) ? $value : '' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = strtolower( strip_tags( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( is_string( $value ) ? $value : '', '-' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option_name, mixed $default = false ): mixed {
		return $GLOBALS['plan_your_day_test_options'][ $option_name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option_name, mixed $value ): bool {
		$GLOBALS['plan_your_day_test_options'][ $option_name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, "/\\" ) . '/';
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return 'tests-' . $scheme;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct(
			private string $code = '',
			private string $message = '',
			private mixed $data = null
		) {
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
