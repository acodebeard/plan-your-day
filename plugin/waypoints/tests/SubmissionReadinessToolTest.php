<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use PHPUnit\Framework\TestCase;

final class SubmissionReadinessToolTest extends TestCase {
	private string $tool_path;

	protected function setUp(): void {
		parent::setUp();

		$this->tool_path = dirname( __DIR__ ) . '/tools/check-wp-submission-readiness.php';
	}

	public function test_accepts_submission_ready_source_and_artifact(): void {
		$workspace  = $this->make_workspace();
		$plugin_dir = $workspace . '/waypoints';
		$artifact   = $workspace . '/waypoints-1.0.zip';

		$this->write_plugin_fixture( $plugin_dir, 'waypoints', '1.0' );
		$this->zip_fixture( $workspace, 'waypoints', $artifact );

		$result = $this->run_tool(
			[
				'--plugin-dir=' . $plugin_dir,
				'--artifact=' . $artifact,
				'--expected-name=Waypoints',
				'--expected-slug=waypoints',
			]
		);

		self::assertSame( 0, $result['status'], $result['output'] );
		self::assertStringContainsString( 'WP submission readiness scan passed.', $result['output'] );
	}

	public function test_rejects_legacy_plan_your_day_submission_identity(): void {
		$workspace  = $this->make_workspace();
		$plugin_dir = $workspace . '/plan-your-day';
		$artifact   = $workspace . '/plan-your-day-1.0.zip';

		$this->write_plugin_fixture( $plugin_dir, 'plan-your-day', '1.0' );
		$this->zip_fixture( $workspace, 'plan-your-day', $artifact );

		$result = $this->run_tool(
			[
				'--plugin-dir=' . $plugin_dir,
				'--artifact=' . $artifact,
				'--expected-name=Waypoints',
				'--expected-slug=waypoints',
			]
		);

		self::assertSame( 1, $result['status'], $result['output'] );
		self::assertStringContainsString( 'release.json slug must be "waypoints".', $result['output'] );
		self::assertStringContainsString( 'Plugin header Text Domain must be "waypoints".', $result['output'] );
		self::assertStringContainsString( 'PLAN_YOUR_DAY_TEXT_DOMAIN must be "waypoints".', $result['output'] );
		self::assertStringContainsString( 'Artifact top-level directory must be "waypoints".', $result['output'] );
	}

	public function test_rejects_not_production_ready_readme_copy(): void {
		$workspace  = $this->make_workspace();
		$plugin_dir = $workspace . '/waypoints';

		$this->write_plugin_fixture( $plugin_dir, 'waypoints', '1.0', "Not yet. Production QA and release hardening are still in progress.\n" );

		$result = $this->run_tool(
			[
				'--plugin-dir=' . $plugin_dir,
				'--expected-name=Waypoints',
				'--expected-slug=waypoints',
			]
		);

		self::assertSame( 1, $result['status'], $result['output'] );
		self::assertStringContainsString( 'readme.txt must not describe the plugin as not production ready.', $result['output'] );
	}

	public function test_rejects_composer_metadata_in_submission_artifact(): void {
		$workspace  = $this->make_workspace();
		$plugin_dir = $workspace . '/waypoints';
		$artifact   = $workspace . '/waypoints-1.0.zip';

		$this->write_plugin_fixture( $plugin_dir, 'waypoints', '1.0' );
		file_put_contents( $plugin_dir . '/composer.json', "{}\n" );
		$this->zip_fixture( $workspace, 'waypoints', $artifact );

		$result = $this->run_tool(
			[
				'--plugin-dir=' . $plugin_dir,
				'--artifact=' . $artifact,
				'--expected-name=Waypoints',
				'--expected-slug=waypoints',
			]
		);

		self::assertSame( 1, $result['status'], $result['output'] );
		self::assertStringContainsString( 'Artifact must not contain waypoints/composer.json.', $result['output'] );
	}

	/**
	 * @return array{status:int, output:string}
	 */
	private function run_tool( array $arguments ): array {
		$command = array_merge(
			[
				PHP_BINARY,
				$this->tool_path,
			],
			$arguments
		);

		$escaped_command = implode( ' ', array_map( 'escapeshellarg', $command ) );
		$output          = [];
		$status          = 0;

		exec( $escaped_command . ' 2>&1', $output, $status );

		return [
			'status' => $status,
			'output' => implode( "\n", $output ),
		];
	}

	private function make_workspace(): string {
		$workspace = sys_get_temp_dir() . '/plan-your-day-readiness-' . bin2hex( random_bytes( 8 ) );

		self::assertTrue( mkdir( $workspace, 0777, true ) );

		return $workspace;
	}

	private function write_plugin_fixture( string $plugin_dir, string $slug, string $version, string $production_note = '' ): void {
		self::assertTrue( mkdir( $plugin_dir . '/vendor', 0777, true ) );

		$display_name = 'Waypoints';

		file_put_contents(
			$plugin_dir . '/' . $slug . '.php',
			<<<PHP
			<?php
			/**
			 * Plugin Name: {$display_name}
			 * Description: A configurable day planning plugin for WordPress.
			 * Version: {$version}
			 * Requires at least: 6.8
			 * Requires PHP: 8.2
			 * Author: acodebeard
			 * Text Domain: {$slug}
			 * Domain Path: /languages
			 * License: GPL-2.0-or-later
			 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
			 */

			define( 'PLAN_YOUR_DAY_VERSION', '{$version}' );
			define( 'PLAN_YOUR_DAY_SCHEMA_VERSION', 5 );
			define( 'PLAN_YOUR_DAY_TEXT_DOMAIN', '{$slug}' );
			PHP
		);

		file_put_contents(
			$plugin_dir . '/readme.txt',
			<<<README
			=== {$display_name} ===
			Contributors: acodebeard
			Tags: planning, maps, wayfinding
			Requires at least: 6.8
			Tested up to: 7.0
			Requires PHP: 8.2
			Stable tag: {$version}
			License: GPLv2 or later
			License URI: https://www.gnu.org/licenses/gpl-2.0.html

			Waypoints is a configurable day planning plugin for WordPress.

			== Description ==

			Waypoints helps visitors search for places, collect stops, and open a route in Google Maps.

			{$production_note}
			== External services ==

			Waypoints uses Google services for place results, geocoding, embedded map previews, and Google Maps handoff links.

			* https://policies.google.com/privacy
			* https://cloud.google.com/maps-platform/terms

			== Changelog ==

			= {$version} =
			* Prepared for WordPress.org submission.
			README
		);

		file_put_contents(
			$plugin_dir . '/release.json',
			json_encode(
				[
					'name'          => $display_name,
					'slug'          => $slug,
					'version'       => $version,
					'schemaVersion' => 5,
					'artifact'      => '../../dist/' . $slug . '-' . $version . '.zip',
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		);

		file_put_contents( $plugin_dir . '/vendor/autoload.php', "<?php\n" );
	}

	private function zip_fixture( string $workspace, string $slug, string $artifact ): void {
		$command = sprintf(
			'cd %s && zip -qr %s %s',
			escapeshellarg( $workspace ),
			escapeshellarg( $artifact ),
			escapeshellarg( $slug )
		);
		$output  = [];
		$status  = 0;

		exec( $command . ' 2>&1', $output, $status );

		self::assertSame( 0, $status, implode( "\n", $output ) );
	}
}
