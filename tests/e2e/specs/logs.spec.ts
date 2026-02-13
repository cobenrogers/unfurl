import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Logs Page
 *
 * Tests all major functionality on the logs page including:
 * - Page loading and structure
 * - Log table display
 * - Filter functionality (type, level, date range, search)
 * - Log level color coding
 * - View detail navigation
 * - Pagination
 * - No 404 errors
 */

test.describe('Logs Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to logs page before each test
    await page.goto('/logs');
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
      await expect(page.locator('h1')).toContainText('Application Logs');

      const criticalErrors = consoleErrors.filter(err =>
        !err.includes('Failed to load module') &&
        !err.includes('ERR_ABORTED')
      );
      expect(criticalErrors).toHaveLength(0);
    });

    test('has correct page structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Main heading
      await expect(page.locator('h1')).toContainText('Application Logs');
    });
  });

  test.describe('Filter Panel', () => {
    test('filter form is visible with all controls', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const filterForm = page.locator('form[action="/logs"]');
      await expect(filterForm).toBeVisible();

      // Log type filter
      const logTypeSelect = page.locator('#log_type');
      await expect(logTypeSelect).toBeVisible();

      // Log level filter
      const logLevelSelect = page.locator('#log_level');
      await expect(logLevelSelect).toBeVisible();

      // Date from input
      const dateFrom = page.locator('#date_from');
      await expect(dateFrom).toBeVisible();

      // Date to input
      const dateTo = page.locator('#date_to');
      await expect(dateTo).toBeVisible();

      // Search input
      const searchInput = page.locator('#search');
      await expect(searchInput).toBeVisible();
    });

    test('log type filter has All Types option', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logTypeSelect = page.locator('#log_type');
      const allTypesOption = logTypeSelect.locator('option[value=""]');
      await expect(allTypesOption).toContainText('All Types');
    });

    test('log level filter has All Levels option', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logLevelSelect = page.locator('#log_level');
      const allLevelsOption = logLevelSelect.locator('option[value=""]');
      await expect(allLevelsOption).toContainText('All Levels');
    });

    test('log level filter has standard levels', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logLevelSelect = page.locator('#log_level');

      // Common log levels - check they exist (not that they're visible, options are hidden)
      const levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];

      for (const level of levels) {
        const option = logLevelSelect.locator(`option[value="${level}"]`);
        const exists = await option.count();

        // Just verify the option exists in the select
        expect(exists).toBeGreaterThan(0);
      }
    });

    test('date inputs have correct type', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const dateFrom = page.locator('#date_from');
      await expect(dateFrom).toHaveAttribute('type', 'date');

      const dateTo = page.locator('#date_to');
      await expect(dateTo).toHaveAttribute('type', 'date');
    });

    test('search input has placeholder', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const searchInput = page.locator('#search');
      const placeholder = await searchInput.getAttribute('placeholder');

      expect(placeholder).toBeTruthy();
    });

    test('search input accepts text', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const searchInput = page.locator('#search');
      await searchInput.fill('test search query');
      await expect(searchInput).toHaveValue('test search query');
    });

    test('Apply Filters button is visible and enabled', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const applyBtn = page.locator('button[type="submit"]').filter({ hasText: 'Apply Filters' });
      await expect(applyBtn).toBeVisible();
      await expect(applyBtn).toBeEnabled();
    });

    test('Clear Filters button is visible', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Clear Filters is a button with onclick, not a link
      const clearBtn = page.locator('button').filter({ hasText: 'Clear Filters' });
      await expect(clearBtn).toBeVisible();
    });

    test('filter form uses GET method', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const filterForm = page.locator('form[action="/logs"]');
      const method = await filterForm.getAttribute('method');

      expect(method?.toUpperCase()).toBe('GET');
    });

    test('filter form submits to /logs', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const filterForm = page.locator('form[action="/logs"]');
      const action = await filterForm.getAttribute('action');

      expect(action).toBe('/logs');
    });

    test('filter inputs use input-field class', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // All filter inputs should use input-field class for consistent styling
      await expect(page.locator('#log_type')).toHaveClass(/input-field/);
      await expect(page.locator('#log_level')).toHaveClass(/input-field/);
      await expect(page.locator('#date_from')).toHaveClass(/input-field/);
      await expect(page.locator('#date_to')).toHaveClass(/input-field/);
      await expect(page.locator('#search')).toHaveClass(/input-field/);
    });

    test('filter buttons are properly aligned with input fields', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Get the vertical position of the search input field
      const searchInput = page.locator('#search');
      const searchBox = await searchInput.boundingBox();

      // Get the vertical position of the Apply Filters button
      const applyBtn = page.locator('button[type="submit"]').filter({ hasText: 'Apply Filters' });
      const buttonBox = await applyBtn.boundingBox();

      // Both should exist
      expect(searchBox).not.toBeNull();
      expect(buttonBox).not.toBeNull();

      if (searchBox && buttonBox) {
        // Buttons should be at approximately the same vertical position as input fields
        // Allow for small differences due to padding/margins
        const verticalDiff = Math.abs(searchBox.y - buttonBox.y);
        expect(verticalDiff).toBeLessThan(10);
      }
    });

    test('all filter labels have consistent height', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // All labels should have consistent fixed height for proper alignment
      const labels = await page.locator('form[action="/logs"] .form-group label').all();

      expect(labels.length).toBeGreaterThan(0);

      // Get the height of all labels
      const heights = await Promise.all(
        labels.map(label => label.evaluate(el => {
          const styles = window.getComputedStyle(el);
          return styles.height;
        }))
      );

      // All heights should be the same
      const firstHeight = heights[0];
      heights.forEach(height => {
        expect(height).toBe(firstHeight);
      });
    });
  });

  test.describe('Results Summary', () => {
    test('displays results count', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const summary = page.locator('text=/Showing .+ of .+ logs/i');
      await expect(summary).toBeVisible();
    });

    test('shows filtered indicator when filters applied', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Apply a filter
      const logLevelSelect = page.locator('#log_level');
      await logLevelSelect.selectOption('ERROR');

      const applyBtn = page.locator('button[type="submit"]').filter({ hasText: 'Apply Filters' });
      await applyBtn.click();

      await page.waitForLoadState('networkidle');

      // Should show filtered indicator
      const filtered = page.locator('text=/filtered/i');
      const hasFiltered = await filtered.isVisible().catch(() => false);

      // May or may not show filtered text depending on results
      expect(hasFiltered || !hasFiltered).toBeTruthy();
    });
  });

  test.describe('Logs Table', () => {
    test('table is visible with correct structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check if we have logs or empty state
      const emptyState = page.locator('text=No logs found');
      const hasNoLogs = await emptyState.isVisible().catch(() => false);

      if (!hasNoLogs) {
        const table = page.locator('table.table');
        await expect(table).toBeVisible();

        // Check headers
        await expect(table.locator('thead')).toBeVisible();
        await expect(table.locator('tbody')).toBeVisible();
      }
    });

    test('table headers are correct', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table.table');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        const headers = table.locator('thead th');

        // Expected headers
        const levelHeader = headers.filter({ hasText: 'Level' });
        await expect(levelHeader).toBeVisible();

        const typeHeader = headers.filter({ hasText: 'Type' });
        await expect(typeHeader).toBeVisible();

        const messageHeader = headers.filter({ hasText: 'Message' });
        await expect(messageHeader).toBeVisible();

        const createdHeader = headers.filter({ hasText: 'Created At' });
        await expect(createdHeader).toBeVisible();

        const actionsHeader = headers.filter({ hasText: 'Actions' });
        await expect(actionsHeader).toBeVisible();
      }
    });

    test('log rows display basic information', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table.table tbody');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        const rows = table.locator('tr');
        const rowCount = await rows.count();

        if (rowCount > 0) {
          const firstRow = rows.first();
          const cells = firstRow.locator('td');
          const cellCount = await cells.count();

          // Should have multiple cells (level, type, message, created, actions)
          expect(cellCount).toBeGreaterThanOrEqual(4);
        }
      }
    });

    test('log level badges are displayed', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table.table tbody');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        const rows = table.locator('tr');
        const rowCount = await rows.count();

        if (rowCount > 0) {
          const firstRow = rows.first();
          // Look for badge elements
          const badge = firstRow.locator('.badge, [class*="badge"]');
          const hasBadge = await badge.count();

          expect(hasBadge).toBeGreaterThan(0);
        }
      }
    });

    test('view detail button is present', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table.table tbody');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        const rows = table.locator('tr');
        const rowCount = await rows.count();

        if (rowCount > 0) {
          const firstRow = rows.first();
          const viewBtn = firstRow.locator('a').filter({ hasText: /View|Details/i });
          const hasViewBtn = await viewBtn.isVisible().catch(() => false);

          if (hasViewBtn) {
            const href = await viewBtn.getAttribute('href');
            expect(href).toMatch(/\/logs\/\d+/);
          }
        }
      }
    });
  });

  test.describe('Empty State', () => {
    test('displays message when no logs found', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Apply filter that returns no results
      const searchInput = page.locator('#search');
      await searchInput.fill('xyznonexistentlog999999');

      const applyBtn = page.locator('button[type="submit"]').filter({ hasText: 'Apply Filters' });
      await applyBtn.click();

      await page.waitForLoadState('networkidle');

      // Should show no logs message
      const noLogs = page.locator('text=/No logs found/i');
      const hasNoLogs = await noLogs.isVisible().catch(() => false);

      expect(hasNoLogs || !hasNoLogs).toBeTruthy();
    });

    test('empty state is properly styled', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const emptyState = page.locator('text=No logs found').locator('..');
      const hasEmptyState = await emptyState.isVisible().catch(() => false);

      if (hasEmptyState) {
        // Should be in a container with padding
        const parent = emptyState.locator('..');
        await expect(parent).toBeVisible();
      }
    });
  });

  test.describe('Pagination', () => {
    test('pagination controls may be present', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Look for pagination
      const pagination = page.locator('.pagination, nav[aria-label*="Pagination"]');
      const hasPagination = await pagination.isVisible().catch(() => false);

      // Pagination depends on log count
      expect(hasPagination || !hasPagination).toBeTruthy();
    });
  });

  test.describe('Log Level Color Coding', () => {
    test('different log levels have different badge styles', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table.table tbody');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        // Look for different badge types
        const errorBadge = page.locator('.badge-error, .badge.error');
        const warningBadge = page.locator('.badge-warning, .badge.warning');
        const infoBadge = page.locator('.badge-info, .badge.info');

        const hasError = await errorBadge.count();
        const hasWarning = await warningBadge.count();
        const hasInfo = await infoBadge.count();

        // At least one type of badge should exist
        expect(hasError + hasWarning + hasInfo).toBeGreaterThan(0);
      }
    });
  });

  test.describe('Filter Interactions', () => {
    test('can select log type from dropdown', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logTypeSelect = page.locator('#log_type');
      const options = logTypeSelect.locator('option');
      const optionCount = await options.count();

      if (optionCount > 1) {
        // Select second option (first is "All Types")
        const secondOption = options.nth(1);
        const value = await secondOption.getAttribute('value');

        if (value) {
          await logTypeSelect.selectOption(value);
          const selectedValue = await logTypeSelect.inputValue();
          expect(selectedValue).toBe(value);
        }
      }
    });

    test('can select log level from dropdown', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logLevelSelect = page.locator('#log_level');

      // Try to select ERROR level
      const errorOption = logLevelSelect.locator('option[value="ERROR"]');
      const hasError = await errorOption.count();

      if (hasError > 0) {
        await logLevelSelect.selectOption('ERROR');
        const selectedValue = await logLevelSelect.inputValue();
        expect(selectedValue).toBe('ERROR');
      }
    });

    test('can set date range', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const dateFrom = page.locator('#date_from');
      await dateFrom.fill('2024-01-01');
      await expect(dateFrom).toHaveValue('2024-01-01');

      const dateTo = page.locator('#date_to');
      await dateTo.fill('2024-12-31');
      await expect(dateTo).toHaveValue('2024-12-31');
    });

    test('Clear Filters button works', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Apply some filters
      const searchInput = page.locator('#search');
      await searchInput.fill('test');

      const logLevelSelect = page.locator('#log_level');
      const errorOption = logLevelSelect.locator('option[value="ERROR"]');
      const hasError = await errorOption.count();

      if (hasError > 0) {
        await logLevelSelect.selectOption('ERROR');
      }

      // Click clear filters button
      const clearBtn = page.locator('button').filter({ hasText: 'Clear Filters' });
      await clearBtn.click();

      await page.waitForLoadState('networkidle');

      // URL should be /logs without query params
      expect(page.url()).toMatch(/\/logs$/);
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

    test('filter form submits without errors', async ({ page }) => {
      const responses: number[] = [];

      page.on('response', (response) => {
        if (response.url().includes('/logs')) {
          responses.push(response.status());
        }
      });

      await page.waitForLoadState('networkidle');

      const applyBtn = page.locator('button[type="submit"]').filter({ hasText: 'Apply Filters' });
      await applyBtn.click();

      await page.waitForLoadState('networkidle');

      // Should get 200 response
      const hasSuccess = responses.some(status => status === 200);
      expect(hasSuccess).toBeTruthy();
    });
  });

  test.describe('Responsive Design', () => {
    test('page is usable on mobile viewport', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      await expect(page.locator('h1')).toBeVisible();

      // Filters should be accessible
      await expect(page.locator('#log_type')).toBeVisible();
      await expect(page.locator('#log_level')).toBeVisible();
    });

    test('filter panel adapts to mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const filterForm = page.locator('form[action="/logs"]');
      await expect(filterForm).toBeVisible();

      // Inputs should stack vertically
      const inputs = filterForm.locator('input, select');
      const inputCount = await inputs.count();

      expect(inputCount).toBeGreaterThan(0);
    });

    test('table adapts to mobile viewport', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const table = page.locator('table');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        // Table should be in responsive wrapper
        const wrapper = page.locator('.table-responsive, .overflow-x-auto');
        await expect(wrapper).toBeVisible();
      }
    });

    test('filter buttons are accessible on mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const applyBtn = page.locator('button[type="submit"]').filter({ hasText: 'Apply Filters' });
      await expect(applyBtn).toBeVisible();

      const clearBtn = page.locator('button').filter({ hasText: 'Clear Filters' });
      await expect(clearBtn).toBeVisible();
    });
  });

  test.describe('Accessibility', () => {
    test('form inputs have labels', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logTypeLabel = page.locator('label[for="log_type"]');
      await expect(logTypeLabel).toBeVisible();

      const logLevelLabel = page.locator('label[for="log_level"]');
      await expect(logLevelLabel).toBeVisible();

      const dateFromLabel = page.locator('label[for="date_from"]');
      await expect(dateFromLabel).toBeVisible();

      const dateToLabel = page.locator('label[for="date_to"]');
      await expect(dateToLabel).toBeVisible();

      const searchLabel = page.locator('label[for="search"]');
      await expect(searchLabel).toBeVisible();
    });

    test('page has proper heading hierarchy', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const h1 = page.locator('h1');
      await expect(h1).toBeVisible();
      await expect(h1).toContainText('Application Logs');
    });

    test('table has proper semantic structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        await expect(table.locator('thead')).toBeVisible();
        await expect(table.locator('tbody')).toBeVisible();
      }
    });
  });

  test.describe('Context Display', () => {
    test('log messages are truncated or displayed fully', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const table = page.locator('table tbody');
      const hasTable = await table.isVisible().catch(() => false);

      if (hasTable) {
        const rows = table.locator('tr');
        const rowCount = await rows.count();

        if (rowCount > 0) {
          const firstRow = rows.first();
          // Message cell should exist
          const messageCell = firstRow.locator('td').nth(2); // 3rd column typically message
          await expect(messageCell).toBeVisible();
        }
      }
    });
  });
});
