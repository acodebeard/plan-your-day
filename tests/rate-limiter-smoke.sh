#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
work_dir="$(mktemp -d)"
www_dir="${work_dir}/www"
server_log="${work_dir}/php-server.log"
rate_dir="$(php -r 'echo sys_get_temp_dir() . "/dkc_plan_rate";')"
server_pid=""

cleanup() {
	if [[ -n "${server_pid}" ]] && kill -0 "${server_pid}" 2>/dev/null; then
		kill "${server_pid}" 2>/dev/null || true
		wait "${server_pid}" 2>/dev/null || true
	fi

	rm -rf "${work_dir}"
}

trap cleanup EXIT

mkdir -p "${www_dir}"
cp "${repo_root}/index.php" "${repo_root}/plan.css" "${repo_root}/plan.js" "${www_dir}/"
cp -R "${repo_root}/icons" "${www_dir}/icons"

cat > "${www_dir}/c88e3e98.php" <<'PHP'
<?php
if (!defined('DKC_PLAN_STANDALONE_BOOTSTRAP')) {
	http_response_code(403);
	exit;
}

if (!defined('DKC_PLAN_GOOGLE_API_KEY')) {
	define('DKC_PLAN_GOOGLE_API_KEY', '');
}
PHP

port="${DKC_PLAN_TEST_PORT:-$((20000 + RANDOM % 20000))}"
base_url="http://127.0.0.1:${port}"

php -S "127.0.0.1:${port}" -t "${www_dir}" > "${server_log}" 2>&1 &
server_pid="$!"

for _ in $(seq 1 50); do
	if curl -fsS "${base_url}/" >/dev/null 2>&1; then
		break
	fi
	sleep 0.1
done

if ! curl -fsS "${base_url}/" >/dev/null 2>&1; then
	echo "Failed to start PHP server on ${base_url}" >&2
	cat "${server_log}" >&2
	exit 1
fi

extract_nonce() {
	local html_file="$1"

	php -r '
		$html = file_get_contents($argv[1]);
		if (!preg_match("#<script type=\"application/json\" data-plan-config>(.*?)</script>#s", $html, $matches)) {
			fwrite(STDERR, "Planner config script not found\n");
			exit(2);
		}
		$config = json_decode($matches[1], true);
		if (!is_array($config) || empty($config["ajaxNonce"])) {
			fwrite(STDERR, "Planner ajaxNonce not found\n");
			exit(3);
		}
		echo $config["ajaxNonce"];
	' "${html_file}"
}

new_session_nonce() {
	local cookie_jar="$1"
	local html_file="${work_dir}/page-$(basename "${cookie_jar}").html"

	curl -fsS -c "${cookie_jar}" "${base_url}/" > "${html_file}"
	extract_nonce "${html_file}"
}

browse_status() {
	local cookie_jar="$1"
	local nonce="$2"
	local body_file="${work_dir}/body-$(basename "${cookie_jar}")-${RANDOM}.json"

	curl -sS -b "${cookie_jar}" -o "${body_file}" -w "%{http_code}" \
		-H 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8' \
		--data-urlencode "nonce=${nonce}" \
		--data-urlencode "category=coffee" \
		"${base_url}/?action=dkc_plan_browse"
}

reset_rate_limit() {
	rm -rf "${rate_dir}"
	mkdir -p "${rate_dir}"
}

expect_limited_on_61st_with_changing_cookies() {
	local status=""

	reset_rate_limit

	for request_number in $(seq 1 61); do
		local cookie_jar="${work_dir}/changing-${request_number}.cookies"
		local nonce

		nonce="$(new_session_nonce "${cookie_jar}")"
		status="$(browse_status "${cookie_jar}" "${nonce}")"

		if [[ "${request_number}" -lt 61 && "${status}" != "200" ]]; then
			echo "Expected request ${request_number} with changing cookies to return 200, got ${status}" >&2
			exit 1
		fi
	done

	if [[ "${status}" != "429" ]]; then
		echo "Expected 61st request with changing cookies to return 429, got ${status}" >&2
		exit 1
	fi
}

expect_limited_on_61st_with_same_cookie() {
	local status=""
	local cookie_jar="${work_dir}/same.cookies"
	local nonce

	reset_rate_limit
	nonce="$(new_session_nonce "${cookie_jar}")"

	for request_number in $(seq 1 61); do
		status="$(browse_status "${cookie_jar}" "${nonce}")"

		if [[ "${request_number}" -lt 61 && "${status}" != "200" ]]; then
			echo "Expected request ${request_number} with same cookie to return 200, got ${status}" >&2
			exit 1
		fi
	done

	if [[ "${status}" != "429" ]]; then
		echo "Expected 61st request with same cookie to return 429, got ${status}" >&2
		exit 1
	fi
}

expect_limited_on_61st_with_changing_cookies
expect_limited_on_61st_with_same_cookie

echo "Rate limiter smoke test passed."
