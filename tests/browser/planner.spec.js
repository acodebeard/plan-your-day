const { test, expect } = require('@playwright/test');

function trackBrowserErrors(page) {
  const errors = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });

  page.on('pageerror', (error) => {
    errors.push(error.message);
  });

  return async () => {
    expect(errors).toEqual([]);
  };
}

function isPlannerResponse(response, routeName) {
  if (response.request().method() !== 'POST') {
    return false;
  }

  return new URL(response.url()).pathname === `/wp-json/plan-your-day/v1/${routeName}`;
}

async function waitForPlannerResponse(page, routeName, action) {
  const [response] = await Promise.all([
    page.waitForResponse((candidate) => isPlannerResponse(candidate, routeName)),
    action(),
  ]);

  expect(response.ok()).toBeTruthy();

  return response;
}

test.beforeEach(async ({ request }) => {
  const response = await request.post('/__reset');
  expect(response.status()).toBe(204);
});

test('planner assets stay scoped to planner renders', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.goto('/plain');
  await expect(page.locator('[data-plan-root]')).toHaveCount(0);
  await expect(page.locator('link[href*="plan.min.css"]')).toHaveCount(0);
  await expect(page.locator('script[src*="plan.min.js"]')).toHaveCount(0);

  await page.goto('/shortcode');
  await expect(page.locator('[data-plan-root]')).toBeVisible();
  await expect(page.locator('link[href*="plan.min.css"]')).toHaveCount(1);
  await expect(page.locator('script[src*="plan.min.js"]')).toHaveCount(1);

  await page.goto('/block');
  await expect(page.locator('[data-plan-root]')).toBeVisible();
  await expect(page.locator('link[href*="plan.min.css"]')).toHaveCount(1);
  await expect(page.locator('script[src*="plan.min.js"]')).toHaveCount(1);

  await assertNoBrowserErrors();
});

test('shortcode planner covers browse, load more, add, reorder, remove, and clear-trip flow', async ({
  page,
}) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.goto('/shortcode');
  await expect(page.locator('[data-plan-root]')).toBeVisible();
  await expect(page.locator('[data-plan-start-toggle]')).toBeVisible();

  const coffeeButton = page.locator('[data-plan-category-button][data-category-key="coffee"]');
  await coffeeButton.focus();
  await waitForPlannerResponse(page, 'browse', () => page.keyboard.press('Enter'));

  const results = page.locator('[data-plan-results-list] > li');
  await expect(results).toHaveCount(2);
  await expect(results.nth(0)).toContainText('Harbor Coffee');
  await expect(results.nth(1)).toContainText('Sunrise Cafe');
  await expect(page.locator('[data-plan-load-more-button]')).toBeVisible();

  await waitForPlannerResponse(page, 'browse', () =>
    page.locator('[data-plan-load-more-button]').click()
  );

  await expect(results).toHaveCount(4);
  await expect(results.nth(2)).toContainText('Coastal Roasters');
  await expect(results.nth(3)).toContainText('Boardwalk Espresso');
  await expect(page.locator('[data-plan-load-more-button]')).toHaveCount(0);

  await waitForPlannerResponse(page, 'route', () =>
    page.locator('[data-plan-results-list] button[data-place-id="coffee-1"]').click()
  );

  const tripItems = page.locator('[data-plan-trip-list] > li');
  await expect(tripItems).toHaveCount(1);
  await expect(tripItems.nth(0)).toContainText('Harbor Coffee');
  await expect(
    page.locator('[data-waypoint-id="coffee-1"] button[name="remove_waypoint"]')
  ).toBeFocused();

  await waitForPlannerResponse(page, 'route', () =>
    page.locator('[data-plan-results-list] button[data-place-id="coffee-2"]').click()
  );

  await expect(tripItems).toHaveCount(2);
  await expect(tripItems.nth(1)).toContainText('Sunrise Cafe');
  await expect(
    page.locator('[data-waypoint-id="coffee-2"] button[name="remove_waypoint"]')
  ).toBeFocused();

  await waitForPlannerResponse(page, 'route', () =>
    page.locator('button[name="move_waypoint"][value="coffee-2:up"]').click()
  );

  await expect(tripItems.nth(0)).toContainText('Sunrise Cafe');
  await expect(page.locator('[data-plan-trip-heading]')).toBeFocused();

  await waitForPlannerResponse(page, 'route', () =>
    page.locator('[data-waypoint-id="coffee-1"] button[name="remove_waypoint"]').click()
  );

  await expect(tripItems).toHaveCount(1);
  await expect(tripItems.nth(0)).toContainText('Sunrise Cafe');
  await expect(
    page.locator('[data-waypoint-id="coffee-2"] button[name="remove_waypoint"]')
  ).toBeFocused();

  await waitForPlannerResponse(page, 'route', () => page.locator('[data-plan-clear-trip]').click());

  await expect(page.locator('[data-plan-trip-empty]')).toBeVisible();
  await expect(page.locator('[data-plan-trip-heading]')).toBeFocused();

  await assertNoBrowserErrors();
});

test('block render boots the planner and category browse works', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.goto('/block');
  await expect(page.locator('[data-plan-root]')).toBeVisible();
  await expect(page.locator('[data-plan-start-toggle]')).toBeVisible();

  await waitForPlannerResponse(page, 'browse', () =>
    page.locator('[data-plan-category-button][data-category-key="food"]').click()
  );

  await expect(page.locator('[data-plan-results-list]')).toContainText('Harbor Bistro');
  await expect(page.locator('[data-plan-results-list]')).toContainText('Market Kitchen');

  await assertNoBrowserErrors();
});

test('start options toggle stays usable in a narrow viewport', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/shortcode');

  const toggle = page.locator('[data-plan-start-toggle]');
  const panel = page.locator('[data-plan-start-panel]');

  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(panel).toBeVisible();

  await toggle.focus();
  await page.keyboard.press('Enter');

  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(panel).toBeHidden();

  await page.keyboard.press('Enter');

  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(panel).toBeVisible();

  await assertNoBrowserErrors();
});
