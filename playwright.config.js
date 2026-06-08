const { defineConfig } = require('@playwright/test');

const baseURL = process.env.PLAN_YOUR_DAY_BROWSER_BASE_URL || 'http://127.0.0.1:9080';

module.exports = defineConfig({
  testDir: './tests/browser',
  fullyParallel: false,
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  webServer: {
    command:
      'mkdir -p tmp && rm -f tmp/waypoints-browser-state.json && ' +
      `PLAN_YOUR_DAY_BROWSER_BASE_URL=${baseURL} ` +
      'PLAN_YOUR_DAY_BROWSER_STATE_FILE=tmp/waypoints-browser-state.json ' +
      'php -S 127.0.0.1:9080 -t . plugin/waypoints/tests/browser-app/router.php',
    url: `${baseURL}/__health`,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
});
