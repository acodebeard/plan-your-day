<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

defined( 'ABSPATH' ) || exit;

final class WordPressGoogleHttpTransport implements GoogleHttpTransportInterface {
	public function get( string $url, array $args = [] ) {
		return wp_remote_get( $url, $args );
	}

	public function post( string $url, array $args = [] ) {
		return wp_remote_post( $url, $args );
	}
}
