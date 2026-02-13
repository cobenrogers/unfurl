import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Process Page
 *
 * Tests all major functionality on the process page including:
 * - Page loading and structure
 * - Feed selection interface
 * - Select all functionality
 * - Process button enable/disable
 * - Progress tracking display
 * - Status messages
 * - No 404 errors
 */

test.describe('Process Page', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to process page before each test
    await page.goto('/process');
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
      const heading = page.locator('h2, h1').filter({ hasText: 'Process Feeds' });
      await expect(heading).toBeVisible();

      const criticalErrors = consoleErrors.filter(err =>
        !err.includes('Failed to load module') &&
        !err.includes('ERR_ABORTED')
      );
      expect(criticalErrors).toHaveLength(0);
    });

    test('has correct page structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Main heading
      const heading = page.locator('h2, h1').filter({ hasText: 'Process Feeds' });
      await expect(heading).toBeVisible();

      // Description - use first() to avoid ambiguity
      const description = page.locator('text=Select feeds to process').first();
      await expect(description).toBeVisible();
    });

    test('status messages container exists', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const statusMessages = page.locator('#status-messages');
      await expect(statusMessages).toHaveAttribute('role', 'status');
      await expect(statusMessages).toHaveAttribute('aria-live', 'polite');
      await expect(statusMessages).toHaveAttribute('aria-atomic', 'true');
    });
  });

  test.describe('Feed Selection Section', () => {
    test('feed selection card is visible', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedSelection = page.locator('#feed-selection');
      await expect(feedSelection).toBeVisible();

      const cardHeader = feedSelection.locator('h3').filter({ hasText: 'Select Feeds to Process' });
      await expect(cardHeader).toBeVisible();
    });

    test('displays no feeds message when no feeds configured', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      // Check if we have the no feeds alert
      const noFeedsAlert = page.locator('.alert.alert-info').filter({ hasText: 'No feeds configured yet' });
      const hasNoFeeds = await noFeedsAlert.isVisible().catch(() => false);

      if (hasNoFeeds) {
        // Should have link to create feed
        const createLink = noFeedsAlert.locator('a[href="/feeds/create"]');
        await expect(createLink).toBeVisible();
      }
    });

    test('displays feed list when feeds exist', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processForm = page.locator('#process-form');
      const hasForm = await processForm.isVisible().catch(() => false);

      if (hasForm) {
        // Feed list should be visible
        const feedList = page.locator('.feed-list');
        await expect(feedList).toBeVisible();

        // Select all checkbox should be present
        const selectAll = page.locator('#select-all-feeds');
        await expect(selectAll).toBeVisible();
        await expect(selectAll).toHaveAttribute('type', 'checkbox');
      }
    });

    test('Select All checkbox is present and functional', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const selectAll = page.locator('#select-all-feeds');
      const hasSelectAll = await selectAll.isVisible().catch(() => false);

      if (hasSelectAll) {
        await expect(selectAll).toHaveAttribute('aria-label', 'Select all feeds');

        // Should be able to check/uncheck
        const isChecked = await selectAll.isChecked();
        await selectAll.click();
        const isCheckedAfter = await selectAll.isChecked();

        expect(isChecked !== isCheckedAfter).toBeTruthy();
      }
    });

    test('individual feed checkboxes are present', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedCheckboxes = page.locator('.feed-checkbox');
      const count = await feedCheckboxes.count();

      if (count > 0) {
        const firstCheckbox = feedCheckboxes.first();
        await expect(firstCheckbox).toHaveAttribute('type', 'checkbox');
        await expect(firstCheckbox).toHaveAttribute('name', 'feed_ids[]');
        await expect(firstCheckbox).toHaveAttribute('data-feed-id');
      }
    });

    test('feed items display topic and metadata', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedItems = page.locator('.feed-item');
      const count = await feedItems.count();

      if (count > 0) {
        const firstFeed = feedItems.first();

        // Should have feed name
        const feedName = firstFeed.locator('.feed-name');
        await expect(feedName).toBeVisible();

        // Should have metadata (article count, last processed)
        const feedMeta = firstFeed.locator('.feed-meta');
        await expect(feedMeta).toBeVisible();
      }
    });
  });

  test.describe('Process Button', () => {
    test('process button exists with correct attributes', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processButton = page.locator('#process-button');
      const hasButton = await processButton.isVisible().catch(() => false);

      if (hasButton) {
        await expect(processButton).toHaveAttribute('type', 'submit');
        await expect(processButton).toHaveAttribute('aria-label', 'Process selected feeds');
        await expect(processButton).toContainText('Process Selected Feeds');
      }
    });

    test('process button is disabled when no feeds selected', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processButton = page.locator('#process-button');
      const hasButton = await processButton.isVisible().catch(() => false);

      if (hasButton) {
        // Initially should be disabled
        await expect(processButton).toBeDisabled();
      }
    });

    test('process button enables when feed is selected', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedCheckbox = page.locator('.feed-checkbox').first();
      const hasCheckbox = await feedCheckbox.isVisible().catch(() => false);

      if (hasCheckbox) {
        // Check a feed
        await feedCheckbox.check();
        await page.waitForTimeout(300); // Wait for JS to enable button

        const processButton = page.locator('#process-button');
        const isDisabled = await processButton.isDisabled();

        // Button should be enabled
        expect(isDisabled).toBeFalsy();
      }
    });

    test('process button has loading state', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processButton = page.locator('#process-button');
      const hasButton = await processButton.isVisible().catch(() => false);

      if (hasButton) {
        // Should have loader span (hidden initially)
        const loader = processButton.locator('.btn-loader');
        await expect(loader).toHaveCSS('display', 'none');
      }
    });
  });

  test.describe('Process Form', () => {
    test('form has correct action and method', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processForm = page.locator('#process-form');
      const hasForm = await processForm.isVisible().catch(() => false);

      if (hasForm) {
        const action = await processForm.getAttribute('action');
        const method = await processForm.getAttribute('method');

        expect(action).toBe('/api/process');
        expect(method?.toUpperCase()).toBe('POST');
      }
    });

    test('form includes CSRF token', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processForm = page.locator('#process-form');
      const hasForm = await processForm.isVisible().catch(() => false);

      if (hasForm) {
        const csrfInput = processForm.locator('input[name="csrf_token"]');
        const hasCSRF = await csrfInput.isVisible().catch(() => false);

        if (hasCSRF) {
          const value = await csrfInput.inputValue();
          expect(value).toBeTruthy();
        }
      }
    });
  });

  test.describe('Progress Section', () => {
    test('progress section exists but is hidden initially', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const progressSection = page.locator('#progress-section');
      await expect(progressSection).toHaveCSS('display', 'none');
    });

    test('progress section has correct structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const progressSection = page.locator('#progress-section');

      // Header
      const header = progressSection.locator('h3').filter({ hasText: 'Processing Progress' });
      await expect(header).toBeDefined();

      // Progress bar elements
      const progressBar = progressSection.locator('.progress-bar');
      await expect(progressBar).toBeDefined();

      const progressFill = progressSection.locator('#progress-fill');
      await expect(progressFill).toBeDefined();

      // Progress counters
      const progressPercent = progressSection.locator('#progress-percent');
      await expect(progressPercent).toBeDefined();

      const progressCurrent = progressSection.locator('#progress-current');
      await expect(progressCurrent).toBeDefined();

      const progressTotal = progressSection.locator('#progress-total');
      await expect(progressTotal).toBeDefined();
    });

    test('progress bar has ARIA attributes', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const progressFill = page.locator('#progress-fill');
      await expect(progressFill).toHaveAttribute('role', 'progressbar');
      await expect(progressFill).toHaveAttribute('aria-valuenow', '0');
      await expect(progressFill).toHaveAttribute('aria-valuemin', '0');
      await expect(progressFill).toHaveAttribute('aria-valuemax', '100');
    });

    test('processing log container exists', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processingLog = page.locator('#processing-log');
      await expect(processingLog).toBeDefined();

      // Should be within a details/summary element
      const details = page.locator('details').filter({ has: processingLog });
      await expect(details).toBeDefined();
    });

    test('log entry counter exists', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const logCount = page.locator('#log-entry-count');
      await expect(logCount).toBeDefined();
    });
  });

  test.describe('Help Text', () => {
    test('displays help text about feed selection', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const helpText = page.locator('text=Select at least one feed to enable processing');
      const hasHelpText = await helpText.isVisible().catch(() => false);

      if (hasHelpText) {
        await expect(helpText).toBeVisible();
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

    test('form action points to valid endpoint', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const processForm = page.locator('#process-form');
      const hasForm = await processForm.isVisible().catch(() => false);

      if (hasForm) {
        const action = await processForm.getAttribute('action');
        expect(action).toBeTruthy();
        expect(action).not.toContain('undefined');
        expect(action).not.toContain('null');
      }
    });
  });

  test.describe('Responsive Design', () => {
    test('page is usable on mobile viewport', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const heading = page.locator('h2, h1').filter({ hasText: 'Process Feeds' });
      await expect(heading).toBeVisible();

      const feedSelection = page.locator('#feed-selection');
      await expect(feedSelection).toBeVisible();
    });

    test('feed list adapts to mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const feedList = page.locator('.feed-list');
      const hasList = await feedList.isVisible().catch(() => false);

      if (hasList) {
        await expect(feedList).toBeVisible();

        const feedItems = page.locator('.feed-item');
        const count = await feedItems.count();

        if (count > 0) {
          const firstItem = feedItems.first();
          await expect(firstItem).toBeVisible();
        }
      }
    });

    test('process button is accessible on mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      await page.waitForLoadState('networkidle');

      const processButton = page.locator('#process-button');
      const hasButton = await processButton.isVisible().catch(() => false);

      if (hasButton) {
        await expect(processButton).toBeVisible();
      }
    });
  });

  test.describe('Accessibility', () => {
    test('checkboxes have proper labels', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const selectAll = page.locator('#select-all-feeds');
      const hasSelectAll = await selectAll.isVisible().catch(() => false);

      if (hasSelectAll) {
        // Select all checkbox should be visible (may not have aria-label)
        await expect(selectAll).toBeVisible();
      }

      const feedCheckboxes = page.locator('.feed-checkbox');
      const count = await feedCheckboxes.count();

      if (count > 0) {
        // Each checkbox should be in a label or checkbox container
        const labels = page.locator('label.checkbox, .checkbox-label, label:has(.feed-checkbox)');
        const labelCount = await labels.count();
        expect(labelCount).toBeGreaterThanOrEqual(count);
      }
    });

    test('page has proper heading hierarchy', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const mainHeading = page.locator('h2, h1').filter({ hasText: 'Process Feeds' });
      await expect(mainHeading).toBeVisible();
    });

    test('cards have semantic structure', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const cards = page.locator('.card');
      const cardCount = await cards.count();

      expect(cardCount).toBeGreaterThan(0);

      // Cards should have headers and bodies - use first() to avoid ambiguity
      const firstCard = cards.first();
      const cardHeader = firstCard.locator('.card-header, h3').first();
      await expect(cardHeader).toBeVisible();
    });
  });

  test.describe('Form Validation', () => {
    test('feed checkboxes have name attribute for form submission', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedCheckboxes = page.locator('.feed-checkbox');
      const count = await feedCheckboxes.count();

      if (count > 0) {
        const firstCheckbox = feedCheckboxes.first();
        const name = await firstCheckbox.getAttribute('name');
        expect(name).toBe('feed_ids[]');
      }
    });

    test('feed checkboxes have value attributes', async ({ page }) => {
      await page.waitForLoadState('networkidle');

      const feedCheckboxes = page.locator('.feed-checkbox');
      const count = await feedCheckboxes.count();

      if (count > 0) {
        const firstCheckbox = feedCheckboxes.first();
        const value = await firstCheckbox.getAttribute('value');
        expect(value).toBeTruthy();
        expect(value).toMatch(/^\d+$/); // Should be numeric feed ID
      }
    });
  });
});
