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
$GLOBALS['plan_your_day_test_object_cache'] = [];
$GLOBALS['plan_your_day_use_ext_object_cache'] = false;
$GLOBALS['plan_your_day_test_filters'] = [];

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

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10 ): bool {
		$GLOBALS['plan_your_day_test_filters'][ $hook_name ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed {
		$callbacks_by_priority = $GLOBALS['plan_your_day_test_filters'][ $hook_name ] ?? [];

		if ( ! is_array( $callbacks_by_priority ) ) {
			return $value;
		}

		ksort( $callbacks_by_priority );

		foreach ( $callbacks_by_priority as $callbacks ) {
			if ( ! is_array( $callbacks ) ) {
				continue;
			}

			foreach ( $callbacks as $callback ) {
				$value = $callback( $value, ...$args );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option_name, mixed $value, mixed $autoload = null ): bool {
		$GLOBALS['plan_your_day_test_options'][ $option_name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $option_name, mixed $value, string $deprecated = '', mixed $autoload = null ): bool {
		if ( array_key_exists( $option_name, $GLOBALS['plan_your_day_test_options'] ) ) {
			return false;
		}

		$GLOBALS['plan_your_day_test_options'][ $option_name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option_name ): bool {
		unset( $GLOBALS['plan_your_day_test_options'][ $option_name ] );

		return true;
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache(): bool {
		return ! empty( $GLOBALS['plan_your_day_use_ext_object_cache'] );
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		$found = isset( $GLOBALS['plan_your_day_test_object_cache'][ $group ] )
			&& array_key_exists( $key, $GLOBALS['plan_your_day_test_object_cache'][ $group ] );

		if ( $found ) {
			return $GLOBALS['plan_your_day_test_object_cache'][ $group ][ $key ];
		}

		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( string $key, mixed $value, string $group = '', int $expire = 0 ): bool {
		$GLOBALS['plan_your_day_test_object_cache'][ $group ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_cache_add' ) ) {
	function wp_cache_add( string $key, mixed $value, string $group = '', int $expire = 0 ): bool {
		if ( isset( $GLOBALS['plan_your_day_test_object_cache'][ $group ] )
			&& array_key_exists( $key, $GLOBALS['plan_your_day_test_object_cache'][ $group ] ) ) {
			return false;
		}

		$GLOBALS['plan_your_day_test_object_cache'][ $group ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( string $key, string $group = '' ): bool {
		unset( $GLOBALS['plan_your_day_test_object_cache'][ $group ][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return $GLOBALS['plan_your_day_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration ): bool {
		$GLOBALS['plan_your_day_test_transients'][ $transient ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['plan_your_day_test_transients'][ $transient ] );

		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( array $response ): string {
		return (string) ( $response['body'] ?? '' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		$query = http_build_query( $args );

		if ( '' === $query ) {
			return $url;
		}

		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $query;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, ?string $domain = null ): string {
		return 1 === $number ? $single : $plural;
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

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = [];

		public function __construct(
			private string $method = 'GET',
			private string $route = ''
		) {
		}

		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}

		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
		}

		public function get_method(): string {
			return $this->method;
		}

		public function get_route(): string {
			return $this->route;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct(
			private mixed $data = null,
			private int $status = 200
		) {
		}

		public function get_data(): mixed {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
