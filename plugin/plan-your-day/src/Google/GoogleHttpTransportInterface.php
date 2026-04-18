<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

defined( 'ABSPATH' ) || exit;

interface GoogleHttpTransportInterface {
	/**
	 * @return array|\WP_Error
	 */
	public function get( string $url, array $args = [] );

	/**
	 * @return array|\WP_Error
	 */
	public function post( string $url, array $args = [] );
}
