#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
RELEASE_JSON="${PLUGIN_DIR}/release.json"
DISTIGNORE_FILE="${PLUGIN_DIR}/.distignore"

if ! command -v composer >/dev/null 2>&1; then
	echo "composer is required to build the release zip." >&2
	exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
	echo "zip is required to build the release zip." >&2
	exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
	echo "rsync is required to build the release zip." >&2
	exit 1
fi

read_release_meta() {
	php /dev/stdin "$RELEASE_JSON" "${PLUGIN_DIR}/plan-your-day.php" <<'PHP'
<?php
$release = json_decode(file_get_contents($argv[1]), true);
$plugin  = file_get_contents($argv[2]);

if ( ! is_array( $release ) || empty( $release['slug'] ) || empty( $release['version'] ) || ! isset( $release['schemaVersion'] ) || empty( $release['artifact'] ) ) {
	fwrite( STDERR, "release.json must contain non-empty slug, version, schemaVersion, and artifact values.\n" );
	exit( 1 );
}

if ( ! is_string( $plugin ) || '' === $plugin ) {
	fwrite( STDERR, "Unable to read the main plugin file.\n" );
	exit( 1 );
}

if ( ! preg_match( "/define\\(\\s*'PLAN_YOUR_DAY_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $plugin, $version_match ) ) {
	fwrite( STDERR, "Unable to read PLAN_YOUR_DAY_VERSION from the main plugin file.\n" );
	exit( 1 );
}

if ( ! preg_match( "/define\\(\\s*'PLAN_YOUR_DAY_SCHEMA_VERSION'\\s*,\\s*(\\d+)\\s*\\)/", $plugin, $schema_match ) ) {
	fwrite( STDERR, "Unable to read PLAN_YOUR_DAY_SCHEMA_VERSION from the main plugin file.\n" );
	exit( 1 );
}

$runtime_version = $version_match[1];
$runtime_schema  = (int) $schema_match[1];
$release_schema  = (int) $release['schemaVersion'];
$artifact_name   = basename( $release['artifact'] );
$expected_name   = sprintf( '%s-%s.zip', $release['slug'], $release['version'] );

if ( $release['version'] !== $runtime_version ) {
	fwrite( STDERR, "release.json version must match PLAN_YOUR_DAY_VERSION.\n" );
	exit( 1 );
}

if ( $release_schema !== $runtime_schema ) {
	fwrite( STDERR, "release.json schemaVersion must match PLAN_YOUR_DAY_SCHEMA_VERSION.\n" );
	exit( 1 );
}

if ( $artifact_name !== $expected_name ) {
	fwrite( STDERR, "release.json artifact name must match the plugin slug and version.\n" );
	exit( 1 );
}

echo $release['slug'], "\n", $release['version'], "\n", $release['artifact'], "\n";
PHP
}

mapfile -t RELEASE_META < <(read_release_meta)
SLUG="${RELEASE_META[0]}"
VERSION="${RELEASE_META[1]}"
ARTIFACT_CONFIG_PATH="${RELEASE_META[2]}"
ARTIFACT_NAME="$(basename "${ARTIFACT_CONFIG_PATH}")"

if [[ "${ARTIFACT_CONFIG_PATH}" = /* ]]; then
	ARTIFACT_DIR="$(dirname "${ARTIFACT_CONFIG_PATH}")"
else
	ARTIFACT_DIR="${PLUGIN_DIR}/$(dirname "${ARTIFACT_CONFIG_PATH}")"
fi

mkdir -p "${ARTIFACT_DIR}"
ARTIFACT_DIR="$(cd "${ARTIFACT_DIR}" && pwd)"
ARTIFACT_PATH="${ARTIFACT_DIR}/${ARTIFACT_NAME}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

STAGE_ROOT="${TMP_DIR}/stage"
STAGE_PLUGIN_DIR="${STAGE_ROOT}/${SLUG}"

mkdir -p "${STAGE_ROOT}"

rsync -a --delete --exclude-from="${DISTIGNORE_FILE}" "${PLUGIN_DIR}/" "${STAGE_PLUGIN_DIR}/"

(
	cd "${STAGE_PLUGIN_DIR}"
	COMPOSER_ROOT_VERSION="${VERSION}" composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
)

if [[ ! -f "${STAGE_PLUGIN_DIR}/vendor/autoload.php" ]]; then
	echo "Release build failed: vendor/autoload.php is missing from the staged plugin." >&2
	exit 1
fi

rm -f "${STAGE_PLUGIN_DIR}/composer.json" "${STAGE_PLUGIN_DIR}/composer.lock"
find "${STAGE_PLUGIN_DIR}" -type d -exec chmod 755 {} +
find "${STAGE_PLUGIN_DIR}" -type f -exec chmod 644 {} +
rm -f "${ARTIFACT_PATH}"

(
	cd "${STAGE_ROOT}"
	zip -qr "${ARTIFACT_PATH}" "${SLUG}"
)

echo "Built ${ARTIFACT_PATH}"
