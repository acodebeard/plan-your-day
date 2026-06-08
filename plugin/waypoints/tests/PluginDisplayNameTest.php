<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use PHPUnit\Framework\TestCase;

	final class PluginDisplayNameTest extends TestCase {
		public function test_front_facing_metadata_uses_waypoints_display_name(): void {
			self::assertStringContainsString( 'Plugin Name: Waypoints', $this->fixture( 'plan-your-day.php' ) );
			self::assertStringContainsString( 'Text Domain: waypoints', $this->fixture( 'plan-your-day.php' ) );
			self::assertStringContainsString( "define( 'PLAN_YOUR_DAY_TEXT_DOMAIN', 'waypoints' );", $this->fixture( 'plan-your-day.php' ) );

			$release = json_decode( $this->fixture( 'release.json' ), true );
			self::assertIsArray( $release );
			self::assertSame( 'Waypoints', $release['name'] ?? null );
			self::assertSame( 'waypoints', $release['slug'] ?? null );
			self::assertSame( '../../dist/waypoints-1.0.zip', $release['artifact'] ?? null );

			$block = json_decode( $this->fixture( 'blocks/planner/block.json' ), true );
			self::assertIsArray( $block );
			self::assertSame( 'waypoints/planner', $block['name'] ?? null );
			self::assertSame( 'Waypoints', $block['title'] ?? null );
			self::assertSame( 'Render the Waypoints planner through the block editor.', $block['description'] ?? null );
			self::assertSame( 'waypoints', $block['textdomain'] ?? null );
			self::assertSame( 'waypoints-block-editor', $block['editorScript'] ?? null );
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

			self::assertStringContainsString( "__( 'Waypoints', 'waypoints' )", $script );
			self::assertStringNotContainsString( "__( 'Plan Your Day', 'waypoints' )", $script );
		}

		public function test_shortcode_exposes_waypoints_tag_with_legacy_alias(): void {
			$source = $this->fixture( 'src/Frontend/PlannerShortcode.php' );

			self::assertStringContainsString( "public const TAG = 'waypoints';", $source );
			self::assertStringContainsString( "private const LEGACY_TAG = 'plan_your_day';", $source );
			self::assertStringContainsString( 'add_shortcode( self::TAG', $source );
			self::assertStringContainsString( 'add_shortcode( self::LEGACY_TAG', $source );
		}

	private function fixture( string $path ): string {
		$content = file_get_contents( dirname( __DIR__ ) . '/' . $path );

		self::assertIsString( $content );

		return $content;
	}
}
