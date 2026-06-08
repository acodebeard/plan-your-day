#!/usr/bin/env bash
set -euo pipefail

if [[ ! -x vendor/bin/phpstan ]]; then
  printf 'PHPStan is not installed. Run composer install first.\n' >&2
  exit 1
fi

vendor/bin/phpstan analyse --configuration phpstan.neon --no-progress "$@"
