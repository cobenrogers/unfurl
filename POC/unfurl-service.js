#!/usr/bin/env node
/**
 * Unfurl Service - Process articles via browser automation
 *
 * Accepts article data via stdin (JSON array)
 * Outputs processed results to stdout (JSON)
 *
 * Usage:
 *   echo '[{"id":1,"url":"..."}]' | node unfurl-service.js
 *   node unfurl-service.js --headless < articles.json
 */

const { chromium } = require('playwright');

// Configuration
const CONFIG = {
  headless: process.argv.includes('--headless') || process.env.HEADLESS === 'true',
  timeout: 30000,
  waitAfterNavigation: 3000
};

/**
 * Process a single article
 */
async function processArticle(page, article) {
  const result = {
    id: article.id,
    googleNewsUrl: article.url,
    status: 'pending'
  };

  try {
    // Navigate to Google News URL
    await page.goto(article.url, {
      waitUntil: 'domcontentloaded',
      timeout: CONFIG.timeout
    });

    // Wait for redirects to complete
    await page.waitForTimeout(CONFIG.waitAfterNavigation);

    // Get final URL
    const finalUrl = page.url();
    result.finalUrl = finalUrl;

    // Check if we redirected away from Google
    const finalDomain = new URL(finalUrl).hostname;
    const isStillOnGoogle = finalDomain.includes('google.com');

    if (isStillOnGoogle) {
      result.status = 'redirect_failed';
      result.error = 'Did not redirect away from Google News';
    } else {
      // Extract metadata from final page
      const metadata = await page.evaluate(() => {
        const getMeta = (property) => {
          let elem = document.querySelector(`meta[property="${property}"]`);
          if (elem) return elem.getAttribute('content');
          elem = document.querySelector(`meta[name="${property}"]`);
          if (elem) return elem.getAttribute('content');
          return null;
        };

        return {
          pageTitle: document.title,
          ogTitle: getMeta('og:title'),
          ogDescription: getMeta('og:description'),
          ogImage: getMeta('og:image'),
          ogUrl: getMeta('og:url'),
          ogSiteName: getMeta('og:site_name'),
          twitterImage: getMeta('twitter:image'),
          twitterCard: getMeta('twitter:card'),
          author: getMeta('author')
        };
      });

      result.pageTitle = metadata.pageTitle;
      result.ogTitle = metadata.ogTitle;
      result.ogDescription = metadata.ogDescription;
      result.ogImage = metadata.ogImage;
      result.ogUrl = metadata.ogUrl;
      result.ogSiteName = metadata.ogSiteName;
      result.twitterImage = metadata.twitterImage;
      result.twitterCard = metadata.twitterCard;
      result.author = metadata.author;
      result.status = 'success';
    }
  } catch (error) {
    result.status = 'error';
    result.error = error.message;
  }

  return result;
}

/**
 * Main processing function
 */
async function main() {
  // Read articles from stdin
  let inputData = '';

  // Check if we have stdin data
  if (process.stdin.isTTY) {
    console.error('Error: No input data provided. Pipe JSON array to stdin.');
    console.error('Example: echo \'[{"id":1,"url":"https://..."}]\' | node unfurl-service.js');
    process.exit(1);
  }

  // Read from stdin
  for await (const chunk of process.stdin) {
    inputData += chunk;
  }

  let articles;
  try {
    articles = JSON.parse(inputData);
  } catch (error) {
    console.error('Error: Invalid JSON input');
    process.exit(1);
  }

  if (!Array.isArray(articles)) {
    console.error('Error: Input must be a JSON array');
    process.exit(1);
  }

  // Launch browser
  const browser = await chromium.launch({
    headless: CONFIG.headless
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();

  // Process each article
  const results = [];
  for (const article of articles) {
    const result = await processArticle(page, article);
    results.push(result);

    // Small delay between articles
    if (articles.indexOf(article) < articles.length - 1) {
      await page.waitForTimeout(1000);
    }
  }

  // Close browser
  await browser.close();

  // Output results as JSON
  console.log(JSON.stringify(results, null, 2));
}

// Run the service
main().catch(error => {
  console.error('Fatal error:', error.message);
  process.exit(1);
});
