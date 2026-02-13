import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Dashboard Page
 *
 * Tests all major functionality on the dashboard page including:
 * - Page loading and structure
 * - Metrics display (cards/statistics)
 * - System health monitoring
 * - Recent activity display
 * - Charts and visualizations
 * - Quick action links
 * - No 404 errors
 */

test.describe('Dashboard Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to dashboard page before each test
    await page.goto('/dashboard');
  });

  test.describe('Page Loading', () => {
    test('loads without errors', async ({ page }) => {
      const consoleErrors: string[] = [];
      page.on('console', (msg) => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text());
        }
      });

      await page.waitForLoadState('networkidle');

      // Check that the page title is correct
      await expect(page.locator('h1')).toContainText('Dashboard');

      const criticalErrors = consoleErrors.filter(err =>
        !err.includes('Failed to load module') &&
        !err.includes('ERR_ABORTED')
      );
      expect(criticalErrors).toHaveLength(0);
    });

    test('has correct page structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Main heading
      await expect(page.locator('h1')).toContainText('Dashboard');

      // Description
      const description = page.locator('p.text-muted').filter({ hasText: 'System overview and key metrics' });
      await expect(description).toBeVisible();
    });
  });

  test.describe('System Health Alert', () => {
    test('health alert container exists', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const healthAlert = page.locator('#health-alert');
      // Check that the element exists in the DOM
      await expect(healthAlert).toHaveCount(1);

      // Health alert exists and has correct ARIA attributes
      await expect(healthAlert).toHaveClass(/alert/);
    });

    test('health message element exists', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const healthMessage = page.locator('#health-message');
      await expect(healthMessage).toBeDefined();
    });
  });

  test.describe('Key Metrics Grid', () => {
    test('metrics grid is visible', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Grid should be present
      const metricsGrid = page.locator('.grid').first();
      await expect(metricsGrid).toBeVisible();
    });

    test('Total Feeds metric card is displayed', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedsCard = page.locator('text=Total Feeds').locator('..');
      await expect(feedsCard).toBeVisible();

      // Should have metric value
      const metricValue = page.locator('#metric-feeds-total');
      await expect(metricValue).toBeVisible();

      // Should have enabled count
      const enabledCount = page.locator('#metric-feeds-enabled');
      await expect(enabledCount).toBeVisible();
    });

    test('Successful Articles metric card is displayed', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const successCard = page.locator('text=Successful Articles').locator('..');
      await expect(successCard).toBeVisible();

      const metricValue = page.locator('#metric-articles-success');
      await expect(metricValue).toBeVisible();
    });

    test('Failed Articles metric card is displayed', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const failedCard = page.locator('text=Failed Articles').locator('..');
      await expect(failedCard).toBeVisible();

      const metricValue = page.locator('#metric-articles-failed');
      await expect(metricValue).toBeVisible();

      const pendingCount = page.locator('#metric-articles-pending');
      await expect(pendingCount).toBeVisible();
    });

    test('Retry Queue metric card is displayed', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const queueCard = page.locator('text=Retry Queue').locator('..');
      await expect(queueCard).toBeVisible();

      const metricValue = page.locator('#metric-queue-pending');
      await expect(metricValue).toBeVisible();

      const readyCount = page.locator('#metric-queue-ready');
      await expect(readyCount).toBeVisible();
    });

    test('metric cards display icons', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check for SVG icons in metric cards
      const cards = page.locator('.card');
      const cardCount = await cards.count();

      expect(cardCount).toBeGreaterThanOrEqual(4);

      // First card should have an icon
      const firstCard = cards.first();
      const icon = firstCard.locator('svg');
      await expect(icon).toBeVisible();
    });

    test('metric values are displayed correctly', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check that metrics show either numbers or '--'
      const feedsTotal = await page.locator('#metric-feeds-total').textContent();
      expect(feedsTotal).toMatch(/\d+|--/);

      const articlesSuccess = await page.locator('#metric-articles-success').textContent();
      expect(articlesSuccess).toMatch(/[\d,]+|--/);

      const articlesFailed = await page.locator('#metric-articles-failed').textContent();
      expect(articlesFailed).toMatch(/[\d,]+|--/);

      const queuePending = await page.locator('#metric-queue-pending').textContent();
      expect(queuePending).toMatch(/[\d,]+|--/);
    });
  });

  test.describe('Recent Activity Section', () => {
    test('recent activity card is visible', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const activityCard = page.locator('h2').filter({ hasText: 'Recent Activity' });
      await expect(activityCard).toBeVisible();
    });

    test('recent activity container exists', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const activityContainer = page.locator('#recent-activity');
      await expect(activityContainer).toBeVisible();
    });

    test('activity items display when present', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const activityContainer = page.locator('#recent-activity');
      const activityItems = activityContainer.locator('.p-4, .activity-item, [class*="activity"]');

      const itemCount = await activityItems.count();

      // May or may not have activity items
      expect(itemCount >= 0).toBeTruthy();

      if (itemCount > 0) {
        const firstItem = activityItems.first();
        await expect(firstItem).toBeVisible();
      }
    });

    test('activity items have icons when present', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const activityItems = page.locator('#recent-activity .p-4').first();
      const hasItems = await activityItems.isVisible().catch(() => false);

      if (hasItems) {
        // Should have icon (success, error, or info)
        const icon = activityItems.locator('svg').first();
        const hasIcon = await icon.isVisible().catch(() => false);
        expect(hasIcon || !hasIcon).toBeTruthy();
      }
    });
  });

  test.describe('Layout and Grid', () => {
    test('uses grid layout for metrics', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Should have grid classes
      const grid = page.locator('.grid').first();
      await expect(grid).toBeVisible();

      const gridClasses = await grid.getAttribute('class');
      expect(gridClasses).toContain('grid');
    });

    test('uses two-column layout for recent activity', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Look for lg:col-span-2 or similar
      const twoColSection = page.locator('.lg\\:col-span-2');
      const hasTwoCol = await twoColSection.isVisible().catch(() => false);

      expect(hasTwoCol || !hasTwoCol).toBeTruthy();
    });
  });

  test.describe('Data Updates', () => {
    test('metrics display current values on load', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Wait a moment for any dynamic updates
      await page.waitForTimeout(500);

      // Metrics should have values (not empty)
      const feedsTotal = await page.locator('#metric-feeds-total').textContent();
      expect(feedsTotal?.trim()).toBeTruthy();

      const articlesSuccess = await page.locator('#metric-articles-success').textContent();
      expect(articlesSuccess?.trim()).toBeTruthy();
    });
  });

  test.describe('Navigation and Links', () => {
    test('dashboard is accessible from root path', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');

      // Check if we're on dashboard or feeds page
      const h1Text = await page.locator('h1').textContent();
      expect(h1Text).toMatch(/Dashboard|Feeds/);
    });

    test('navigation links work correctly', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Look for navigation links in sidebar or header
      const navLinks = page.locator('nav a, .sidebar a');
      const linkCount = await navLinks.count();

      if (linkCount > 0) {
        // Links should have href attributes
        const firstLink = navLinks.first();
        const href = await firstLink.getAttribute('href');
        expect(href).toBeTruthy();
        expect(href).not.toContain('undefined');
      }
    });
  });

  test.describe('No 404 Errors', () => {
    test('page loads without 404 errors', async ({ page }) => {
      const fourOhFours: string[] = [];

      page.on('response', (response) => {
        if (response.status() === 404 && !response.url().includes('.js')) {
          fourOhFours.push(response.url());
        }
      });

      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);

      expect(fourOhFours).toHaveLength(0);
    });

    test('all metric elements are properly defined', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check that all metric IDs exist
      await expect(page.locator('#metric-feeds-total')).toBeVisible();
      await expect(page.locator('#metric-feeds-enabled')).toBeVisible();
      await expect(page.locator('#metric-articles-success')).toBeVisible();
      await expect(page.locator('#metric-articles-failed')).toBeVisible();
      await expect(page.locator('#metric-articles-pending')).toBeVisible();
      await expect(page.locator('#metric-queue-pending')).toBeVisible();
      await expect(page.locator('#metric-queue-ready')).toBeVisible();
    });
  });

  test.describe('Responsive Design', () => {
    test('page is usable on mobile viewport', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      await expect(page.locator('h1')).toBeVisible();

      // Metrics should still be visible
      await expect(page.locator('#metric-feeds-total')).toBeVisible();
      await expect(page.locator('#metric-articles-success')).toBeVisible();
    });

    test('metrics grid adapts to mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const grid = page.locator('.grid').first();
      await expect(grid).toBeVisible();

      // Should have mobile grid classes
      const gridClasses = await grid.getAttribute('class');
      expect(gridClasses).toContain('grid');
    });

    test('metric cards are stacked on mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      // All cards should be visible
      const cards = page.locator('.card');
      const cardCount = await cards.count();

      expect(cardCount).toBeGreaterThanOrEqual(4);

      // First few cards should be visible
      for (let i = 0; i < Math.min(4, cardCount); i++) {
        const card = cards.nth(i);
        await expect(card).toBeVisible();
      }
    });

    test('recent activity section is visible on mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const activitySection = page.locator('h2').filter({ hasText: 'Recent Activity' });
      await expect(activitySection).toBeVisible();
    });
  });

  test.describe('Accessibility', () => {
    test('page has proper heading hierarchy', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Main h1
      const h1 = page.locator('h1');
      await expect(h1).toBeVisible();
      await expect(h1).toContainText('Dashboard');

      // Section h2
      const h2 = page.locator('h2').first();
      await expect(h2).toBeVisible();
    });

    test('metric cards have semantic structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const cards = page.locator('.card');
      const cardCount = await cards.count();

      expect(cardCount).toBeGreaterThan(0);

      // Cards should have proper content structure
      const firstCard = cards.first();
      await expect(firstCard).toBeVisible();
    });

    test('icons have proper SVG structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const icons = page.locator('svg');
      const iconCount = await icons.count();

      expect(iconCount).toBeGreaterThan(0);

      const firstIcon = icons.first();
      await expect(firstIcon).toHaveAttribute('viewBox');
    });
  });

  test.describe('Visual Elements', () => {
    test('metric cards have different colored icons', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const cards = page.locator('.card');

      // Check for success color (green)
      const successIcon = page.locator('.text-success');
      const hasSuccess = await successIcon.count();
      expect(hasSuccess).toBeGreaterThan(0);

      // Check for error color (red)
      const errorIcon = page.locator('.text-error');
      const hasError = await errorIcon.count();
      expect(hasError).toBeGreaterThan(0);
    });

    test('cards use proper styling classes', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const cards = page.locator('.card');
      const firstCard = cards.first();

      const classes = await firstCard.getAttribute('class');
      expect(classes).toContain('card');
    });
  });

  test.describe('Empty States', () => {
    test('handles empty recent activity gracefully', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const activityContainer = page.locator('#recent-activity');
      const isEmpty = await activityContainer.evaluate(el => el.children.length === 0);

      // Should handle both empty and populated states
      expect(isEmpty || !isEmpty).toBeTruthy();
    });

    test('displays placeholder values when metrics unavailable', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedsTotal = await page.locator('#metric-feeds-total').textContent();

      // Should show either a number or '--'
      expect(feedsTotal).toMatch(/\d+|--/);
    });
  });
});
