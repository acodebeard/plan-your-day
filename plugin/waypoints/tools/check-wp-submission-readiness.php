#!/usr/bin/env php
<?php
declare( strict_types=1 );

/**
 * Checks WordPress.org submission metadata against the intended public identity.
 *
 * This tool is intentionally stricter than regular development checks. It is
 * meant for release-candidate packaging, where a failure is preferable to
 * submitting the plugin under the wrong permanent WordPress.org slug.
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This tool must be run from the command line.\n" );
	exit( 1 );
}

$options = parse_options( $argv );

if ( isset( $options['help'] ) ) {
	echo "Usage: php tools/check-wp-submission-readiness.php --plugin-dir=. [--artifact=/path/to.zip] [--expected-name='Waypoints: Trip Planner'] [--expected-slug=waypoints-trip-planner]\n";
	exit( 0 );
}

$expected_name = trim( (string) ( $options['expected-name'] ?? 'Waypoints: Trip Planner' ) );
$expected_slug = trim( (string) ( $options['expected-slug'] ?? 'waypoints-trip-planner' ) );
$plugin_dir    = realpath( (string) ( $options['plugin-dir'] ?? dirname( __DIR__ ) ) );
$artifact      = isset( $options['artifact'] ) ? (string) $options['artifact'] : '';
$errors        = [];
$plugin_version = '';

if ( '' === $expected_name ) {
	$errors[] = 'Expected plugin name must not be empty.';
}

if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $expected_slug ) ) {
	$errors[] = 'Expected plugin slug must contain only lowercase letters, numbers, and hyphens.';
}

if ( false === $plugin_dir || ! is_dir( $plugin_dir ) ) {
	$errors[] = 'Plugin directory does not exist.';
} else {
	$main_file = find_main_plugin_file( $plugin_dir, $errors );

	if ( null !== $main_file ) {
		$plugin_source  = read_text_file( $main_file, $errors );
		$plugin_headers = parse_plugin_headers( $plugin_source );
		$plugin_version = trim( $plugin_headers['Version'] ?? '' );

		if ( $expected_name !== trim( $plugin_headers['Plugin Name'] ?? '' ) ) {
			$errors[] = sprintf( 'Plugin header Plugin Name must be "%s".', $expected_name );
		}

		if ( $expected_slug !== trim( $plugin_headers['Text Domain'] ?? '' ) ) {
			$errors[] = sprintf( 'Plugin header Text Domain must be "%s".', $expected_slug );
		}

		if ( '' === $plugin_version ) {
			$errors[] = 'Plugin header Version must not be empty.';
		}

		if ( '' === trim( $plugin_headers['Requires at least'] ?? '' ) ) {
			$errors[] = 'Plugin header Requires at least must not be empty.';
		}

		if ( '' === trim( $plugin_headers['Requires PHP'] ?? '' ) ) {
			$errors[] = 'Plugin header Requires PHP must not be empty.';
		}

		if ( 'GPL-2.0-or-later' !== trim( $plugin_headers['License'] ?? '' ) ) {
			$errors[] = 'Plugin header License must be "GPL-2.0-or-later".';
		}

		$runtime_version = extract_php_constant( $plugin_source, 'PLAN_YOUR_DAY_VERSION' );
		if ( null !== $runtime_version && '' !== $plugin_version && $runtime_version !== $plugin_version ) {
			$errors[] = 'PLAN_YOUR_DAY_VERSION must match the plugin header Version.';
		}

		$runtime_text_domain = extract_php_constant( $plugin_source, 'PLAN_YOUR_DAY_TEXT_DOMAIN' );
		if ( null !== $runtime_text_domain && $runtime_text_domain !== $expected_slug ) {
			$errors[] = sprintf( 'PLAN_YOUR_DAY_TEXT_DOMAIN must be "%s".', $expected_slug );
		}
	}

	check_readme( $plugin_dir, $expected_name, $plugin_version, $errors );
	check_release_json( $plugin_dir, $expected_name, $expected_slug, $plugin_version, $errors );
}

if ( '' !== $artifact ) {
	check_artifact( $artifact, $expected_slug, $errors );
}

if ( [] !== $errors ) {
	echo "WP submission readiness scan failed:\n";
	foreach ( array_values( array_unique( $errors ) ) as $error ) {
		echo ' - ', $error, "\n";
	}
	exit( 1 );
}

echo "WP submission readiness scan passed.\n";
exit( 0 );

/**
 * @return array<string,string|bool>
 */
function parse_options( array $argv ): array {
	$options = [];

	foreach ( array_slice( $argv, 1 ) as $argument ) {
		if ( '--help' === $argument || '-h' === $argument ) {
			$options['help'] = true;
			continue;
		}

		if ( ! str_starts_with( $argument, '--' ) ) {
			continue;
		}

		$argument = substr( $argument, 2 );
		$parts    = explode( '=', $argument, 2 );
		$key      = $parts[0];
		$value    = $parts[1] ?? '1';

		$options[ $key ] = $value;
	}

	return $options;
}

/**
 * @param list<string> $errors
 */
function find_main_plugin_file( string $plugin_dir, array &$errors ): ?string {
	$candidates = [];

	foreach ( glob( $plugin_dir . '/*.php' ) ?: [] as $file ) {
		$content = file_get_contents( $file );

		if ( is_string( $content ) && preg_match( '/Plugin Name\s*:/i', $content ) ) {
			$candidates[] = $file;
		}
	}

	if ( [] === $candidates ) {
		$errors[] = 'No main plugin file with a Plugin Name header was found.';
		return null;
	}

	if ( count( $candidates ) > 1 ) {
		$errors[] = 'More than one root PHP file contains a Plugin Name header.';
		return null;
	}

	return $candidates[0];
}

/**
 * @param list<string> $errors
 */
function read_text_file( string $path, array &$errors ): string {
	$content = file_get_contents( $path );

	if ( ! is_string( $content ) ) {
		$errors[] = sprintf( 'Unable to read %s.', basename( $path ) );
		return '';
	}

	return $content;
}

/**
 * @return array<string,string>
 */
function parse_plugin_headers( string $source ): array {
	$headers = [];
	$fields  = [
		'Plugin Name',
		'Version',
		'Requires at least',
		'Requires PHP',
		'Text Domain',
		'License',
		'License URI',
	];

	foreach ( $fields as $field ) {
		if ( preg_match( '/^[ \t*\/]*' . preg_quote( $field, '/' ) . ':\s*(.+)$/mi', $source, $matches ) ) {
			$headers[ $field ] = trim( $matches[1] );
		}
	}

	return $headers;
}

function extract_php_constant( string $source, string $constant ): ?string {
	if ( preg_match( "/define\\(\\s*'" . preg_quote( $constant, '/' ) . "'\\s*,\\s*'([^']*)'\\s*\\)/", $source, $matches ) ) {
		return $matches[1];
	}

	return null;
}

/**
 * @param list<string> $errors
 */
function check_readme( string $plugin_dir, string $expected_name, string $plugin_version, array &$errors ): void {
	$readme_path = $plugin_dir . '/readme.txt';

	if ( ! is_file( $readme_path ) ) {
		$errors[] = 'readme.txt must exist.';
		return;
	}

	$readme = read_text_file( $readme_path, $errors );

	if ( ! str_starts_with( ltrim( $readme ), '=== ' . $expected_name . ' ===' ) ) {
		$errors[] = sprintf( 'readme.txt title must be "=== %s ===".', $expected_name );
	}

	$stable_tag = readme_header_value( $readme, 'Stable tag' );
	if ( '' === $stable_tag ) {
		$errors[] = 'readme.txt Stable tag must not be empty.';
	} elseif ( '' !== $plugin_version && $stable_tag !== $plugin_version ) {
		$errors[] = 'readme.txt Stable tag must match the plugin header Version.';
	}

	if ( '' === readme_header_value( $readme, 'Requires at least' ) ) {
		$errors[] = 'readme.txt Requires at least must not be empty.';
	}

	if ( '' === readme_header_value( $readme, 'Requires PHP' ) ) {
		$errors[] = 'readme.txt Requires PHP must not be empty.';
	}

	if ( '' === readme_header_value( $readme, 'Tested up to' ) ) {
		$errors[] = 'readme.txt Tested up to must not be empty.';
	}

	if ( ! preg_match( '/^== External services ==$/mi', $readme ) ) {
		$errors[] = 'readme.txt must document external services.';
	}

	if ( ! str_contains( $readme, 'https://policies.google.com/privacy' ) ) {
		$errors[] = 'readme.txt external services section must link to the Google privacy policy.';
	}

	if ( ! str_contains( $readme, 'https://cloud.google.com/maps-platform/terms' ) ) {
		$errors[] = 'readme.txt external services section must link to Google Maps Platform terms.';
	}

	if ( preg_match( '/\bnot yet\b|production QA|release hardening/i', $readme ) ) {
		$errors[] = 'readme.txt must not describe the plugin as not production ready.';
	}
}

function readme_header_value( string $readme, string $field ): string {
	if ( preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+)$/mi', $readme, $matches ) ) {
		return trim( $matches[1] );
	}

	return '';
}

/**
 * @param list<string> $errors
 */
function check_release_json( string $plugin_dir, string $expected_name, string $expected_slug, string $plugin_version, array &$errors ): void {
	$release_path = $plugin_dir . '/release.json';

	if ( ! is_file( $release_path ) ) {
		$errors[] = 'release.json must exist.';
		return;
	}

	$release = json_decode( read_text_file( $release_path, $errors ), true );

	if ( ! is_array( $release ) ) {
		$errors[] = 'release.json must contain a JSON object.';
		return;
	}

	if ( ( $release['name'] ?? null ) !== $expected_name ) {
		$errors[] = sprintf( 'release.json name must be "%s".', $expected_name );
	}

	if ( ( $release['slug'] ?? null ) !== $expected_slug ) {
		$errors[] = sprintf( 'release.json slug must be "%s".', $expected_slug );
	}

	if ( '' !== $plugin_version && ( $release['version'] ?? null ) !== $plugin_version ) {
		$errors[] = 'release.json version must match the plugin header Version.';
	}

	if ( empty( $release['artifact'] ) || ! is_string( $release['artifact'] ) ) {
		$errors[] = 'release.json artifact must not be empty.';
		return;
	}

	$version       = is_string( $release['version'] ?? null ) ? $release['version'] : $plugin_version;
	$artifact_name = basename( $release['artifact'] );
	$expected_name = $expected_slug . '-' . $version . '.zip';

	if ( $expected_name !== $artifact_name ) {
		$errors[] = sprintf( 'release.json artifact basename must be "%s".', $expected_name );
	}
}

/**
 * @param list<string> $errors
 */
function check_artifact( string $artifact, string $expected_slug, array &$errors ): void {
	$artifact_path = realpath( $artifact );

	if ( false === $artifact_path || ! is_file( $artifact_path ) ) {
		$errors[] = 'Release artifact does not exist.';
		return;
	}

	try {
		$archive = new PharData( $artifact_path );
	} catch ( Exception $exception ) {
		$errors[] = 'Release artifact must be a readable zip archive.';
		return;
	}

	$paths = [];
	foreach ( new RecursiveIteratorIterator( $archive, RecursiveIteratorIterator::SELF_FIRST ) as $entry ) {
		if ( ! $entry instanceof SplFileInfo ) {
			continue;
		}

		$paths[] = normalize_archive_path( $entry->getPathName(), $artifact_path );
	}

	$paths = array_values( array_filter( array_unique( $paths ) ) );
	$roots = [];

	foreach ( $paths as $path ) {
		$root = explode( '/', $path, 2 )[0] ?? '';
		if ( '' !== $root ) {
			$roots[ $root ] = true;
		}
	}

	if ( [ $expected_slug ] !== array_keys( $roots ) ) {
		$errors[] = sprintf( 'Artifact top-level directory must be "%s".', $expected_slug );
	}

	$required_paths = [
		$expected_slug . '/readme.txt',
		$expected_slug . '/composer.json',
		$expected_slug . '/vendor/autoload.php',
	];

	foreach ( $required_paths as $required_path ) {
		if ( ! in_array( $required_path, $paths, true ) ) {
			$errors[] = sprintf( 'Artifact must contain %s.', $required_path );
		}
	}

	foreach ( $paths as $path ) {
		if ( preg_match( '#^' . preg_quote( $expected_slug, '#' ) . '/(?:tests|tools|docs|node_modules|\.github)(?:/|$)#', $path ) ) {
			$errors[] = sprintf( 'Artifact must not contain %s.', $path );
		}

		if ( preg_match( '#^' . preg_quote( $expected_slug, '#' ) . '/(?:composer\.lock|phpstan\.neon|phpcs\.xml\.dist|phpunit\.xml(?:\.dist)?|DECISIONS\.md|\.distignore)$#', $path ) ) {
			$errors[] = sprintf( 'Artifact must not contain %s.', $path );
		}
	}
}

function normalize_archive_path( string $path, string $artifact_path ): string {
	$path = str_replace( '\\', '/', $path );

	$prefix = 'phar://' . str_replace( '\\', '/', $artifact_path ) . '/';
	if ( str_starts_with( $path, $prefix ) ) {
		return substr( $path, strlen( $prefix ) );
	}

	return ltrim( $path, '/' );
}
