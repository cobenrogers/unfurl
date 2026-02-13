#!/usr/bin/env node
/**
 * Unfurl - Google News Feed Processor
 *
 * Unfurls obfuscated Google News URLs to reveal actual article sources.
 *
 * Features:
 * 1. Fetches Google News RSS feeds
 * 2. Uses visible browser automation to follow redirects
 * 3. Captures final article URLs and metadata
 * 4. Saves results for later use
 *
 * Why this approach works:
 * - Real browser = no bot detection
 * - Visible = you can watch and monitor
 * - Handles ANY redirect mechanism (HTTP, JS, meta refresh)
 * - Future-proof (works regardless of Google's encoding changes)
 *
 * Usage:
 *   node unfurl.js [--headless] [--limit=N]
 *
 * Options:
 *   --headless    Run browser in headless mode (invisible)
 *   --limit=N     Only process first N articles (default: 5)
 */

const { chromium } = require('playwright');
const https = require('https');
const fs = require('fs');
const path = require('path');

// Configuration
const CONFIG = {
  rssUrl: 'https://news.google.com/rss/search?q=IBD+inflammatory+bowel+disease&hl=en-US&gl=US&ceid=US:en',
  outputFile: 'unfurl-results.json',
  headless: process.argv.includes('--headless'),
  limit: parseInt(process.argv.find(arg => arg.startsWith('--limit='))?.split('=')[1]) || 5,
  slowMo: 500  // Milliseconds to slow down operations (easier to watch)
};

/**
 * Fetch and parse Google News RSS feed
 */
async function fetchRssFeed(url) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          // Extract articles from RSS XML
          const articles = [];
          const itemRegex = /<item>(.*?)<\/item>/gs;
          const matches = data.matchAll(itemRegex);

          for (const match of matches) {
            const item = match[1];

            const title = item.match(/<title><!\[CDATA\[(.*?)\]\]><\/title>/)?.[1] ||
                         item.match(/<title>(.*?)<\/title>/)?.[1];
            const link = item.match(/<link>(.*?)<\/link>/)?.[1];
            const pubDate = item.match(/<pubDate>(.*?)<\/pubDate>/)?.[1];
            const source = item.match(/<source.*?>(.*?)<\/source>/)?.[1];

            if (title && link) {
              articles.push({ title, link, pubDate, source });
            }
          }

          resolve(articles);
        } catch (error) {
          reject(error);
        }
      });
    }).on('error', reject);
  });
}

/**
 * Process a single article by navigating to it in the browser
 */
async function processArticle(page, article, index, total) {
  console.log(`\n[${ index + 1 }/${ total }] Processing: ${article.title}`);
  console.log(`  Google News URL: ${article.link.substring(0, 80)}...`);

  const result = {
    index: index + 1,
    originalTitle: article.title,
    googleNewsUrl: article.link,
    pubDate: article.pubDate,
    source: article.source,
    timestamp: new Date().toISOString()
  };

  try {
    // Navigate to Google News URL
    console.log(`  → Navigating...`);
    await page.goto(article.link, {
      waitUntil: 'domcontentloaded',
      timeout: 30000
    });

    // Wait for any redirects/JavaScript to complete
    await page.waitForTimeout(3000);

    // Get final URL
    const finalUrl = page.url();
    result.finalUrl = finalUrl;

    // Check if we actually left Google News
    const finalDomain = new URL(finalUrl).hostname;
    const isStillOnGoogle = finalDomain.includes('google.com');

    if (isStillOnGoogle) {
      console.log(`  ⚠️  Still on Google (${finalDomain})`);
      result.status = 'redirect_failed';
      result.error = 'Did not redirect away from Google News';
    } else {
      console.log(`  ✓ Redirected to: ${finalDomain}`);

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

      result.metadata = metadata;
      result.status = 'success';

      console.log(`  ✓ Title: ${metadata.ogTitle || metadata.pageTitle || 'N/A'}`);
      console.log(`  ✓ Image: ${metadata.ogImage ? 'Found' : 'Not found'}`);
      if (metadata.ogImage) {
        console.log(`    ${metadata.ogImage.substring(0, 60)}...`);
      }
    }

  } catch (error) {
    console.log(`  ✗ Error: ${error.message}`);
    result.status = 'error';
    result.error = error.message;
  }

  return result;
}

/**
 * Main processing function
 */
async function main() {
  console.log('=====================================');
  console.log('Unfurl - Google News Feed Processor');
  console.log('=====================================\n');

  console.log(`Configuration:`);
  console.log(`  RSS Feed: ${CONFIG.rssUrl}`);
  console.log(`  Headless: ${CONFIG.headless ? 'Yes' : 'No (visible browser)'}`);
  console.log(`  Limit: ${CONFIG.limit} articles`);
  console.log(`  Output: ${CONFIG.outputFile}\n`);

  // Step 1: Fetch RSS feed
  console.log('Step 1: Fetching RSS feed...');
  const articles = await fetchRssFeed(CONFIG.rssUrl);
  console.log(`✓ Found ${articles.length} articles in feed\n`);

  // Limit articles if specified
  const articlesToProcess = articles.slice(0, CONFIG.limit);
  console.log(`Processing first ${articlesToProcess.length} articles...\n`);

  // Step 2: Launch browser
  console.log('Step 2: Launching browser...');
  const browser = await chromium.launch({
    headless: CONFIG.headless,
    slowMo: CONFIG.slowMo
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();
  console.log(`✓ Browser launched ${CONFIG.headless ? '(headless)' : '(you can watch it work!)'}\n`);

  // Step 3: Process each article
  console.log('Step 3: Processing articles...');
  console.log('─'.repeat(60));

  const results = [];
  for (let i = 0; i < articlesToProcess.length; i++) {
    const result = await processArticle(page, articlesToProcess[i], i, articlesToProcess.length);
    results.push(result);

    // Small delay between articles
    if (i < articlesToProcess.length - 1) {
      await page.waitForTimeout(1000);
    }
  }

  // Step 4: Close browser
  console.log('\n' + '─'.repeat(60));
  console.log('\nStep 4: Closing browser...');
  await browser.close();
  console.log('✓ Browser closed\n');

  // Step 5: Save results
  console.log('Step 5: Saving results...');
  const outputPath = path.join(__dirname, CONFIG.outputFile);

  const output = {
    metadata: {
      timestamp: new Date().toISOString(),
      rssFeed: CONFIG.rssUrl,
      totalArticles: articles.length,
      processedArticles: results.length,
      headless: CONFIG.headless
    },
    results: results,
    summary: {
      successful: results.filter(r => r.status === 'success').length,
      redirectFailed: results.filter(r => r.status === 'redirect_failed').length,
      errors: results.filter(r => r.status === 'error').length,
      withImages: results.filter(r => r.metadata?.ogImage).length
    }
  };

  fs.writeFileSync(outputPath, JSON.stringify(output, null, 2));
  console.log(`✓ Results saved to: ${outputPath}\n`);

  // Step 6: Summary
  console.log('=====================================');
  console.log('Summary');
  console.log('=====================================');
  console.log(`Total processed: ${output.summary.successful + output.summary.redirectFailed + output.summary.errors}`);
  console.log(`✓ Successful: ${output.summary.successful}`);
  console.log(`⚠️  Redirect failed: ${output.summary.redirectFailed}`);
  console.log(`✗ Errors: ${output.summary.errors}`);
  console.log(`🖼️  With images: ${output.summary.withImages}`);
  console.log('\n=====================================');
  console.log('POC Complete!');
  console.log('=====================================\n');

  // Display sample results
  const successfulResults = results.filter(r => r.status === 'success');
  if (successfulResults.length > 0) {
    console.log('Sample successful result:');
    console.log('─'.repeat(60));
    const sample = successfulResults[0];
    console.log(`Title: ${sample.originalTitle}`);
    console.log(`Final URL: ${sample.finalUrl}`);
    console.log(`Image: ${sample.metadata?.ogImage || 'N/A'}`);
    console.log('─'.repeat(60));
  }

  console.log(`\nFull results available in: ${CONFIG.outputFile}`);
}

// Run the POC
main().catch(error => {
  console.error('\n✗ Fatal error:', error.message);
  console.error(error.stack);
  process.exit(1);
});
