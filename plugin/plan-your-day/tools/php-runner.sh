#!/usr/bin/env bash
set -euo pipefail

has_required_extensions() {
  local php_bin="$1"
  local required_extensions=("dom" "SimpleXML" "xml" "xmlwriter")
  local extension

  for extension in "${required_extensions[@]}"; do
    if ! "$php_bin" -m 2>/dev/null | grep -qx "$extension"; then
      return 1
    fi
  done

  return 0
}

pick_php_binary() {
  local candidates=()
  local candidate

  if [[ -n "${PLAN_YOUR_DAY_PHP_BIN:-}" ]]; then
    candidates+=("${PLAN_YOUR_DAY_PHP_BIN}")
  fi

  if [[ -x "/opt/lampp/bin/php" ]]; then
    candidates+=("/opt/lampp/bin/php")
  fi

  if command -v php >/dev/null 2>&1; then
    candidates+=("$(command -v php)")
  fi

  for candidate in "${candidates[@]}"; do
    if [[ -x "$candidate" ]] && has_required_extensions "$candidate"; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done

  return 1
}

main() {
  local php_bin

  if [[ $# -eq 0 ]]; then
    echo "Usage: tools/php-runner.sh <script-or-binary> [args...]" >&2
    exit 1
  fi

  if ! php_bin="$(pick_php_binary)"; then
    cat >&2 <<'EOF'
Unable to find a PHP CLI binary with the required XML/DOM extensions.
Set PLAN_YOUR_DAY_PHP_BIN to a compatible binary or install dom, SimpleXML, xml, and xmlwriter for the active CLI PHP.
EOF
    exit 1
  fi

  exec "$php_bin" "$@"
}

main "$@"
