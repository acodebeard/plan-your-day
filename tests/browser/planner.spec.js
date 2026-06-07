const { test, expect } = require('@playwright/test');

const START_PANEL_ANIMATION_MS = 480;

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
  request,
}) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);
  const initialHtmlResponse = await request.get('/shortcode');
  const initialHtml = await initialHtmlResponse.text();

  expect(initialHtml).toContain('data-plan-waypoint-status');
  expect(initialHtml).toContain('Add some waypoints!');

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

  const waypointStatus = page.locator('[data-plan-waypoint-status]');
  await expect(waypointStatus).toBeVisible();
  await expect(waypointStatus).toHaveText('Add some waypoints!');

  const firstAddButton = page.locator('[data-plan-results-list] button[data-place-id="coffee-1"]');
  await firstAddButton.scrollIntoViewIfNeeded();
  const scrollBeforeAdd = await page.evaluate(() => window.scrollY);

  await waitForPlannerResponse(page, 'route', () =>
    firstAddButton.click()
  );

  const tripItems = page.locator('[data-plan-trip-list] > li');
  await expect(tripItems).toHaveCount(1);
  await expect(tripItems.nth(0)).toContainText('Harbor Coffee');
  await expect(waypointStatus).toHaveText('1 waypoint added');
  const scrollAfterAdd = await page.evaluate(() => window.scrollY);
  expect(Math.abs(scrollAfterAdd - scrollBeforeAdd)).toBeLessThanOrEqual(1);
  await expect(
    page.locator('[data-waypoint-id="coffee-1"] button[name="remove_waypoint"]')
  ).not.toBeFocused();

  await waypointStatus.click();
  await expect
    .poll(() =>
      page.evaluate(() => {
        const previewCard = document.querySelector('[data-plan-preview-card]');

        return previewCard instanceof HTMLElement ? Math.round(previewCard.getBoundingClientRect().top) : -1;
      })
    )
    .toBeLessThan(80);

  await waitForPlannerResponse(page, 'route', () =>
    page.locator('[data-plan-results-list] button[data-place-id="coffee-2"]').click()
  );

  await expect(tripItems).toHaveCount(2);
  await expect(tripItems.nth(1)).toContainText('Sunrise Cafe');
  await expect(waypointStatus).toHaveText('2 waypoints added');
  await expect(
    page.locator('[data-waypoint-id="coffee-2"] button[name="remove_waypoint"]')
  ).not.toBeFocused();

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
  await expect(waypointStatus).toHaveText('Add some waypoints!');
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

test('custom start shows a found status when address results are ready', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.goto('/shortcode');
  await expect(page.locator('[data-plan-root]')).toBeVisible();

  await waitForPlannerResponse(page, 'browse', () =>
    page.locator('[data-plan-category-button][data-category-key="coffee"]').click()
  );

  await waitForPlannerResponse(page, 'browse', () =>
    page.locator('.plan-your-day__start-option').filter({ hasText: 'Custom starting point' }).click()
  );

  const customStart = page.locator('[data-plan-custom-start]');
  await customStart.fill('Union Station');

  await waitForPlannerResponse(page, 'browse', () => customStart.blur());

  const customStartWrap = page.locator('[data-plan-custom-start-wrap]');
  await expect(customStartWrap).toHaveAttribute('data-plan-custom-start-state', 'found');
  await expect(page.locator('[data-plan-custom-start-indicator]')).toBeVisible();
  await expect(page.locator('[data-plan-custom-start-status]')).toHaveText(
    'Starting address found. Results are ready.'
  );
  await expect(page.locator('[data-plan-results-list]')).toContainText('Harbor Coffee');

  await customStart.fill('Not a real address');

  await waitForPlannerResponse(page, 'browse', () => customStart.blur());

  await expect(customStartWrap).toHaveAttribute('data-plan-custom-start-state', 'not_found');
  await expect(page.locator('[data-plan-custom-start-indicator]')).toBeVisible();
  await expect(page.locator('[data-plan-custom-start-status]')).toHaveText(
    'Starting address was not found.'
  );

  await assertNoBrowserErrors();
});

test('visitor color mode toggle persists across reloads', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.goto('/shortcode');

  const root = page.locator('[data-plan-root]');
  const toggle = page.locator('[data-plan-color-mode-toggle]');

  await expect(root).toHaveAttribute('data-plan-color-mode', 'light');
  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');
  await expect(root).toHaveCSS('font-family', /Noto Sans/);

  await toggle.click();

  await expect(root).toHaveAttribute('data-plan-color-mode', 'dark');
  await expect(toggle).toHaveAttribute('aria-pressed', 'true');
  await expect(root).toHaveCSS('--pyd-primary-text', '#232323');

  await page.reload();

  await expect(root).toHaveAttribute('data-plan-color-mode', 'dark');
  await expect(toggle).toHaveAttribute('aria-pressed', 'true');

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

test('start options stay open until manually toggled', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/shortcode');

  const panel = page.locator('[data-plan-start-panel]');

  await expect(panel).toBeVisible();
  await page.evaluate(() => window.scrollTo(0, 180));
  await page.waitForTimeout(START_PANEL_ANIMATION_MS + 100);
  await expect(panel).toBeVisible();

  await page.locator('.plan-your-day__start-option').filter({ hasText: 'Custom starting point' }).click();
  await expect(panel).toBeVisible();

  const customStartInput = page.locator('[data-plan-custom-start]');
  await customStartInput.fill('Union Station');
  await customStartInput.blur();
  await page.waitForTimeout(START_PANEL_ANIMATION_MS + 100);
  await expect(panel).toBeVisible();

  await page.locator('.plan-your-day__start-option').filter({ hasText: 'Test Harbor' }).click();
  await page.waitForTimeout(START_PANEL_ANIMATION_MS + 100);
  await expect(panel).toBeVisible();

  await assertNoBrowserErrors();
});

test('planner cards stay inside a constrained content column', async ({ page }) => {
  const assertNoBrowserErrors = trackBrowserErrors(page);

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/narrow-shortcode');
  await expect(page.locator('[data-plan-root]')).toBeVisible();

  const overflow = await page.evaluate(() => {
    const surface = document.querySelector('.plan-your-day__surface');
    const cards = Array.from(document.querySelectorAll('.plan-your-day__card'));

    if (!(surface instanceof HTMLElement)) {
      return ['planner surface was not rendered'];
    }

    const surfaceRect = surface.getBoundingClientRect();

    return cards
      .map((card) => {
        const cardRect = card.getBoundingClientRect();

        return {
          className: card.className,
          leftOverflow: Math.max(0, surfaceRect.left - cardRect.left),
          rightOverflow: Math.max(0, cardRect.right - surfaceRect.right),
        };
      })
      .filter((card) => card.leftOverflow > 0.5 || card.rightOverflow > 0.5);
  });

  expect(overflow).toEqual([]);

  await assertNoBrowserErrors();
});
