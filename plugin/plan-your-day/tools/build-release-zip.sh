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
	php -r '
		$release = json_decode(file_get_contents($argv[1]), true);
		if (!is_array($release) || empty($release["slug"]) || empty($release["version"])) {
			fwrite(STDERR, "release.json must contain non-empty slug and version values.\n");
			exit(1);
		}
		echo $release["slug"], "\n", $release["version"], "\n";
	' "$RELEASE_JSON"
}

mapfile -t RELEASE_META < <(read_release_meta)
SLUG="${RELEASE_META[0]}"
VERSION="${RELEASE_META[1]}"
DIST_DIR="${PLUGIN_DIR}/dist"
ARTIFACT_NAME="${SLUG}-${VERSION}.zip"
ARTIFACT_PATH="${DIST_DIR}/${ARTIFACT_NAME}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

STAGE_ROOT="${TMP_DIR}/stage"
STAGE_PLUGIN_DIR="${STAGE_ROOT}/${SLUG}"

mkdir -p "${STAGE_ROOT}" "${DIST_DIR}"

rsync -a --delete --exclude-from="${DISTIGNORE_FILE}" "${PLUGIN_DIR}/" "${STAGE_PLUGIN_DIR}/"

(
	cd "${STAGE_PLUGIN_DIR}"
	COMPOSER_ROOT_VERSION="${VERSION}" composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
)

if [[ ! -f "${STAGE_PLUGIN_DIR}/vendor/autoload.php" ]]; then
	echo "Release build failed: vendor/autoload.php is missing from the staged plugin." >&2
	exit 1
fi

rm -f "${ARTIFACT_PATH}"

(
	cd "${STAGE_ROOT}"
	zip -qr "${ARTIFACT_PATH}" "${SLUG}"
)

echo "Built ${ARTIFACT_PATH}"
