<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use PHPUnit\Framework\TestCase;

final class PluginDisplayNameTest extends TestCase {
	public function test_front_facing_metadata_uses_waypoints_display_name(): void {
		self::assertStringContainsString( 'Plugin Name: Waypoints', $this->fixture( 'plan-your-day.php' ) );

		$release = json_decode( $this->fixture( 'release.json' ), true );
		self::assertIsArray( $release );
		self::assertSame( 'Waypoints', $release['name'] ?? null );

		$block = json_decode( $this->fixture( 'blocks/planner/block.json' ), true );
		self::assertIsArray( $block );
		self::assertSame( 'Waypoints', $block['title'] ?? null );
		self::assertSame( 'Render the Waypoints planner through the block editor.', $block['description'] ?? null );
	}

	public function test_public_readme_uses_waypoints_display_name(): void {
		$readme = $this->fixture( 'readme.txt' );

		self::assertStringContainsString( '=== Waypoints ===', $readme );
		self::assertStringContainsString( 'Waypoints is a configurable day planning plugin for WordPress.', $readme );
		self::assertStringContainsString( 'Open Settings > Waypoints', $readme );
		self::assertStringNotContainsString( 'Plan Your Day', $readme );
	}

	public function test_block_editor_placeholder_uses_waypoints_display_name(): void {
		$script = $this->fixture( 'assets/js/plan-block.js' );

		self::assertStringContainsString( "__( 'Waypoints', 'plan-your-day' )", $script );
		self::assertStringNotContainsString( "__( 'Plan Your Day', 'plan-your-day' )", $script );
	}

	private function fixture( string $path ): string {
		$content = file_get_contents( dirname( __DIR__ ) . '/' . $path );

		self::assertIsString( $content );

		return $content;
	}
}
