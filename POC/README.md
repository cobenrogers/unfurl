# Unfurl

**Unfurl obfuscated Google News URLs to reveal actual article sources.**

A tool for processing Google News RSS feeds using visible browser automation to extract real article URLs and metadata.

## The Problem

Google News RSS feeds contain obfuscated article URLs that don't redirect properly when accessed programmatically. Traditional approaches (HTTP redirects, URL decoding, APIs) are either broken or fragile.

## The Solution

**Use a real browser to click through articles naturally**, just like a human would. The browser handles all redirects (HTTP, JavaScript, meta refresh) automatically, regardless of how Google encodes them.

## Why This Approach Works

✅ **No URL decoding needed** - Browser follows redirects naturally
✅ **No bot detection** - Real browser with real clicks
✅ **Future-proof** - Works regardless of Google's URL encoding changes
✅ **Can be monitored** - Run in visible mode and watch it work
✅ **Perfect for low volume** - Process 100s of articles per month easily

## Installation

### Prerequisites
- Node.js 18+
- npm or yarn

### Quick Start

```bash
# Clone or navigate to the unfurl directory
cd unfurl

# Install dependencies
npm install

# Install Playwright browsers
npm run install-browsers

# Run unfurl (visible browser - watch it work!)
npm start

# Or run directly
node unfurl.js

# Run in headless mode
npm run headless

# Process only first 3 articles
node unfurl.js --limit=3
```

## How It Works

1. **Fetches Google News RSS feed** - Gets list of articles with obfuscated URLs
2. **Launches browser** - Real Chrome/Chromium instance (visible by default)
3. **Processes each article**:
   - Navigates to Google News URL
   - Waits for redirects to complete
   - Captures final destination URL
   - Extracts metadata (title, description, images)
4. **Saves results** - JSON file with all captured data

## Usage Examples

### Basic Usage (Watch it work!)
```bash
node unfurl.js
```
- Opens visible browser
- Processes first 5 articles
- You can watch it navigate and extract data
- Saves to `unfurl-results.json`

### Headless Mode (Background processing)
```bash
node unfurl.js --headless
```

### Process More Articles
```bash
node unfurl.js --limit=20
```

### Process All Articles
```bash
node unfurl.js --limit=999
```

## Output Format

Results are saved to `unfurl-results.json`:

```json
{
  "metadata": {
    "timestamp": "2026-02-07T12:00:00.000Z",
    "rssFeed": "https://news.google.com/rss/...",
    "totalArticles": 50,
    "processedArticles": 5,
    "headless": false
  },
  "results": [
    {
      "index": 1,
      "originalTitle": "Article Title from RSS",
      "googleNewsUrl": "https://news.google.com/rss/articles/...",
      "finalUrl": "https://www.actualsite.com/article",
      "status": "success",
      "metadata": {
        "pageTitle": "Full Article Title",
        "ogTitle": "Social Share Title",
        "ogDescription": "Article description...",
        "ogImage": "https://www.actualsite.com/images/featured.jpg",
        "ogUrl": "https://www.actualsite.com/article",
        "ogSiteName": "Publisher Name",
        "twitterImage": "https://www.actualsite.com/images/featured.jpg",
        "author": "John Doe"
      }
    }
  ],
  "summary": {
    "successful": 4,
    "redirectFailed": 1,
    "errors": 0,
    "withImages": 3
  }
}
```

## Configuration

Edit the `CONFIG` object in the script:

```javascript
const CONFIG = {
  rssUrl: 'https://news.google.com/rss/search?q=YOUR_SEARCH',
  outputFile: 'results.json',
  headless: false,
  limit: 5,
  slowMo: 500  // Milliseconds delay (easier to watch)
};
```

## Use Cases

### Personal/Research Use
- Extract article URLs and images from Google News feeds
- Research content sources
- Build article databases
- Monitor news coverage

### Content Aggregation
- Process news feeds weekly (100 articles = ~15 minutes)
- Extract source article URLs and featured images
- Build curated news collections
- Feed data into other systems

### Semi-Automated Workflow
1. Run once per week
2. Watch browser process articles (or run in background)
3. Review results JSON
4. Import into your database/CMS
5. Done!

## Potential as Standalone Product

This POC could evolve into:

### Browser Extension
- One-click processing of any Google News page
- Save results directly to browser storage
- Export to various formats

### Desktop App (Electron)
- Nice UI for configuration
- Visual progress monitoring
- Scheduled processing
- Multiple feed management

### Web Service
- Upload RSS feed URL
- Process and download results
- API for automation
- Shared result storage

### SaaS Tool
- Multi-user support
- Feed library
- Automatic scheduling
- Webhook integrations

## Limitations & Considerations

**Rate Limiting**
- Google may rate limit if you process thousands of articles
- For <100/month, no issues expected
- Add delays between articles if needed (already built in)

**Reliability**
- Some articles may not redirect properly (Google News errors)
- Some sites may block automated browsers
- Expected success rate: ~80-95%

**Performance**
- ~3-5 seconds per article
- 100 articles = ~10-15 minutes
- Can run in background (headless mode)

**Legal/Ethical**
- Respects robots.txt
- Simulates normal user behavior
- Low volume = no server impact
- For personal/research use

## Next Steps

To turn this into a real product:

1. **Add UI** - Web interface or desktop app
2. **Database** - Store results persistently
3. **Scheduling** - Automatic processing
4. **Multiple feeds** - Support multiple sources
5. **Export formats** - CSV, XML, API endpoints
6. **Error handling** - Better retry logic
7. **Notifications** - Email/webhook when complete

## License

This is a proof-of-concept for research purposes. Use responsibly.

## Questions?

This POC demonstrates that **browser automation is a viable, future-proof approach** for processing Google News feeds, especially for low-volume use cases.
