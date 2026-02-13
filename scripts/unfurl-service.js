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
  waitAfterNavigation: 5000 // Increased wait time for full page load
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
      // Extract metadata and content from final page
      const metadata = await page.evaluate(() => {
        const getMeta = (property) => {
          let elem = document.querySelector(`meta[property="${property}"]`);
          if (elem) return elem.getAttribute('content');
          elem = document.querySelector(`meta[name="${property}"]`);
          if (elem) return elem.getAttribute('content');
          return null;
        };

        // Extract article content
        const extractArticleContent = () => {
          // Try multiple selectors for article content
          const selectors = [
            'article',
            '[role="article"]',
            '.article-content',
            '.entry-content',
            '.post-content',
            'main article',
            'main'
          ];

          for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
              // Get all paragraphs within the article
              const paragraphs = element.querySelectorAll('p');
              if (paragraphs.length > 0) {
                const text = Array.from(paragraphs)
                  .map(p => p.textContent.trim())
                  .filter(t => t.length > 50) // Filter out short paragraphs
                  .join('\n\n');
                if (text.length > 200) {
                  return text;
                }
              }
            }
          }
          return null;
        };

        const articleContent = extractArticleContent();

        return {
          pageTitle: document.title,
          ogTitle: getMeta('og:title'),
          ogDescription: getMeta('og:description'),
          ogImage: getMeta('og:image'),
          ogUrl: getMeta('og:url'),
          ogSiteName: getMeta('og:site_name'),
          twitterImage: getMeta('twitter:image'),
          twitterCard: getMeta('twitter:card'),
          author: getMeta('author'),
          articleContent: articleContent,
          wordCount: articleContent ? articleContent.split(/\s+/).length : 0
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
      result.articleContent = metadata.articleContent;
      result.wordCount = metadata.wordCount;
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

  // Launch browser with stealth options
  const browser = await chromium.launch({
    headless: CONFIG.headless,
    args: [
      '--disable-blink-features=AutomationControlled',
      '--disable-dev-shm-usage',
      '--no-sandbox'
    ]
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    viewport: { width: 1920, height: 1080 },
    locale: 'en-US',
    timezoneId: 'America/New_York',
    permissions: [],
    extraHTTPHeaders: {
      'Accept-Language': 'en-US,en;q=0.9',
      'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
    }
  });

  // Remove automation indicators
  await context.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', {
      get: () => false
    });
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
