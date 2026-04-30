<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
use PHPUnit\Framework\TestCase;

final class InterfaceCopyTest extends TestCase {
	public function test_sanitize_keeps_optional_blank_and_falls_back_required_blank_values(): void {
		$sanitized = InterfaceCopy::sanitize(
			[
				'hero_title'                  => '   ',
				'hero_intro'                  => '   ',
				'view_place_in_google_maps_aria' => '<strong>View {place}</strong>',
			]
		);

		self::assertSame( 'Plan Your Day', $sanitized['hero_title'] );
		self::assertSame( '', $sanitized['hero_intro'] );
		self::assertSame( 'View {place}', $sanitized['view_place_in_google_maps_aria'] );
	}

	public function test_resolve_values_returns_defaults_for_missing_keys(): void {
		$resolved = InterfaceCopy::resolve_values(
			[
				'hero_intro' => '',
			]
		);

		self::assertSame( 'Plan Your Day', $resolved['hero_title'] );
		self::assertSame( '', $resolved['hero_intro'] );
		self::assertSame( 'Add to trip', $resolved['add_to_trip'] );
	}

	public function test_format_replaces_named_tokens(): void {
		self::assertSame(
			'Results for Coffee',
			InterfaceCopy::format( 'Results for {search}', [ 'search' => 'Coffee' ] )
		);
	}
}
