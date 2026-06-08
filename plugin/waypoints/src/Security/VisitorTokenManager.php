<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Security;

defined( 'ABSPATH' ) || exit;

final class VisitorTokenManager {
	private const COOKIE_NAME = 'plan_your_day_visitor';
	private const COOKIE_TTL = MONTH_IN_SECONDS;

	private ?string $visitor_token = null;

	public function get_endpoint_token(): string {
		$visitor_token = $this->get_or_create_visitor_token();

		if ( '' === $visitor_token ) {
			return '';
		}

		return hash_hmac( 'sha256', $visitor_token, $this->secret_key() );
	}

	public function validate_endpoint_token( string $request_token ): bool {
		$request_token = trim( sanitize_text_field( $request_token ) );
		$visitor_token = $this->get_cookie_token();

		if ( '' === $request_token || '' === $visitor_token ) {
			return false;
		}

		return hash_equals(
			hash_hmac( 'sha256', $visitor_token, $this->secret_key() ),
			$request_token
		);
	}

	private function get_or_create_visitor_token(): string {
		if ( null !== $this->visitor_token ) {
			return $this->visitor_token;
		}

		$cookie_token = $this->get_cookie_token();

		if ( '' !== $cookie_token ) {
			$this->visitor_token = $cookie_token;

			return $this->visitor_token;
		}

		if ( headers_sent() ) {
			$this->visitor_token = '';

			return $this->visitor_token;
		}

		try {
			$visitor_token = bin2hex( random_bytes( 24 ) );
		} catch ( \Throwable $exception ) {
			$this->visitor_token = '';

			return $this->visitor_token;
		}

		$cookie_options = [
			'expires'  => time() + self::COOKIE_TTL,
			'path'     => defined( 'COOKIEPATH' ) && is_string( COOKIEPATH ) && '' !== COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];

		if ( ! setcookie( self::COOKIE_NAME, $visitor_token, $cookie_options ) ) {
			$this->visitor_token = '';

			return $this->visitor_token;
		}

		$_COOKIE[ self::COOKIE_NAME ] = $visitor_token;
		$this->visitor_token          = $visitor_token;

		return $this->visitor_token;
	}

	private function get_cookie_token(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw cookie is scalar-checked, unslashed, sanitized, and regex-validated before use.
		$cookie_value = $_COOKIE[ self::COOKIE_NAME ] ?? '';

		if ( ! is_scalar( $cookie_value ) ) {
			return '';
		}

		$cookie_value = wp_unslash( $cookie_value );
		$cookie_value = strtolower( trim( sanitize_text_field( (string) $cookie_value ) ) );

		return 1 === preg_match( '/\A[a-f0-9]{48}\z/', $cookie_value ) ? $cookie_value : '';
	}

	private function secret_key(): string {
		return wp_salt( 'auth' ) . '|plan-your-day';
	}
}
