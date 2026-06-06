#!/usr/bin/env bash
set -euo pipefail

if [[ -x vendor/bin/phpstan ]]; then
  phpstan_bin="vendor/bin/phpstan"
else
  phpstan_bin="$(command -v phpstan || true)"
fi

if [[ -z "${phpstan_bin:-}" ]]; then
  printf 'PHPStan is not installed. Run composer install first.\n' >&2
  exit 1
fi

"$phpstan_bin" analyse --configuration phpstan.neon --no-progress "$@"
