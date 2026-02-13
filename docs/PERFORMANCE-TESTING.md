# Performance Testing Documentation

**Last Updated:** 2026-02-07
**Status:** ✅ Complete (Task 6.3)

## Overview

Comprehensive performance test suite that verifies the Unfurl application meets all performance requirements and identifies potential bottlenecks before they impact production.

## Test File Location

```
tests/Performance/PerformanceTest.php
```

## Running Performance Tests

```bash
# Run all performance tests
composer test:performance

# Run with PHPUnit directly
phpunit --testsuite Performance

# Run specific test
phpunit --filter testBulkArticleProcessingPerformance tests/Performance/PerformanceTest.php
```

## Performance Requirements

| Requirement | Target | Test Coverage |
|-------------|--------|---------------|
| Article list page query | < 2 seconds | ✓ testArticleListQueryPerformance |
| Article search query | < 200ms | ✓ testArticleSearchPerformance |
| Filter queries | < 150ms | ✓ testFilterQueryPerformance |
| RSS feed generation (uncached) | < 1 second | ✓ testRssFeedGenerationUncached |
| RSS feed generation (cached) | < 100ms | ✓ testRssFeedGenerationCached |
| Single article processing | < 10MB memory | ✓ testSingleArticleMemoryUsage |
| Batch processing (100 articles) | < 256MB memory | ✓ testBatchProcessingMemoryUsage |
| Bulk processing (100 articles) | < 10 minutes | ✓ testBulkArticleProcessingPerformance |

## Test Suite Details

### 1. Bulk Article Processing Performance

**Test:** `testBulkArticleProcessingPerformance()`

**Purpose:** Verify that processing 100 articles completes within 10 minutes (600 seconds)

**Metrics Collected:**
- Total processing time
- Time per article
- Memory usage
- Query count
- Queries per article

**Success Criteria:**
- Total time < 600 seconds
- Average time per article < 6 seconds
- Memory usage < 256MB

**Implementation Details:**
```php
// Creates 100 test articles
// Measures time and memory for creation + marking as processed
// Calculates per-article metrics
```

---

### 2. Article List Query Performance

**Test:** `testArticleListQueryPerformance()`

**Purpose:** Verify paginated article list queries are fast enough for UI

**Metrics Collected:**
- Query execution time
- Result count
- Filter parameters

**Success Criteria:**
- Query time < 100ms

**Test Data:**
- 1000 articles created
- Filtered by topic and status
- 20 results per page

---

### 3. Article Search Performance

**Test:** `testArticleSearchPerformance()`

**Purpose:** Verify full-text search performance

**Metrics Collected:**
- Search query time
- Result count
- Search term

**Success Criteria:**
- Query time < 200ms

**Note:** Skipped on SQLite (requires MySQL MATCH...AGAINST)

---

### 4. Filter Query Performance

**Test:** `testFilterQueryPerformance()`

**Purpose:** Verify complex filter queries with multiple conditions

**Metrics Collected:**
- Total matching articles
- Result count
- Query time

**Success Criteria:**
- Query time < 150ms

**Filters Tested:**
- Topic filter
- Status filter
- Date range (from/to)

---

### 5. RSS Feed Generation (Uncached)

**Test:** `testRssFeedGenerationUncached()`

**Purpose:** Verify RSS generation without cache meets performance requirements

**Metrics Collected:**
- Generation time
- Memory usage
- XML size (KB)

**Success Criteria:**
- Generation time < 1000ms (1 second)
- Memory usage < 50MB

**Test Data:**
- 100 articles
- Full RSS 2.0 XML with content:encoded

---

### 6. RSS Feed Generation (Cached)

**Test:** `testRssFeedGenerationCached()`

**Purpose:** Verify cache significantly improves RSS generation performance

**Metrics Collected:**
- Cache retrieval time
- Speedup factor

**Success Criteria:**
- Cache time < 100ms
- Speedup > 5x

**Implementation:**
- First call populates cache
- Second call retrieves from cache

---

### 7. Single Article Memory Usage

**Test:** `testSingleArticleMemoryUsage()`

**Purpose:** Ensure single article processing doesn't consume excessive memory

**Metrics Collected:**
- Word count
- Memory used

**Success Criteria:**
- Memory usage < 10MB

**Test Article:**
- 5000 words of content
- Full metadata (og:, twitter:, etc.)

---

### 8. Batch Processing Memory Usage

**Test:** `testBatchProcessingMemoryUsage()`

**Purpose:** Verify batch operations stay within memory limits

**Metrics Collected:**
- Article count
- Memory used
- Peak memory

**Success Criteria:**
- Memory usage < 256MB
- Peak memory < 256MB

**Test Data:**
- 100 articles processed sequentially

---

### 9. Query Count Optimization

**Test:** `testQueryCountOptimization()`

**Purpose:** Verify no N+1 query problems

**Metrics Collected:**
- Article count
- Estimated query count
- Query time

**Success Criteria:**
- Query time < 20ms (indicates efficient query)
- Single query (no N+1)

---

### 10. Index Usage Verification

**Test:** `testIndexUsageVerification()`

**Purpose:** Ensure all queries use appropriate database indexes

**Metrics Collected:**
- Query time for different filter types
- Result counts

**Success Criteria:**
- Each query < 50ms (indicates index usage)

**Filter Patterns Tested:**
- By Topic
- By Status
- By Date Range

---

### 11. Cache Effectiveness

**Test:** `testCacheEffectiveness()`

**Purpose:** Measure cache performance improvement

**Metrics Collected:**
- Uncached time (first generation)
- Average cached time (10 subsequent calls)
- Speedup factor
- Cache hit rate

**Success Criteria:**
- Cached time < uncached time / 5 (5x speedup minimum)

**Implementation:**
- 1 uncached generation
- 10 cached generations
- Calculate average and speedup

---

### 12. Memory Leak Detection

**Test:** `testMemoryLeakDetection()`

**Purpose:** Detect memory leaks in repeated operations

**Metrics Collected:**
- Initial memory
- Final memory
- Memory growth
- Growth rate (%)

**Success Criteria:**
- Memory growth < 50% over 20 iterations

**Implementation:**
- 20 iterations of same operation
- Memory snapshots after each iteration
- Calculate growth rate

---

## Performance Report

After running tests, a comprehensive report is generated at:

```
docs/PERFORMANCE-REPORT.md
```

### Report Contents

1. **Environment Information**
   - PHP version
   - Database type
   - Memory limit

2. **Performance Requirements Summary**
   - All requirements with status indicators

3. **Detailed Test Results**
   - All metrics from each test
   - Organized by test name

4. **Recommendations**
   - Database optimization tips
   - Caching strategies
   - Memory management advice
   - Scalability suggestions

5. **Bottleneck Analysis**
   - Identified bottlenecks
   - Performance trends
   - Next steps for optimization

## Metrics Collected

### Timing Metrics
- **Execution Time**: Total time for operation (ms/s)
- **Time Per Item**: Average time per article/operation
- **Query Time**: Database query execution time

### Memory Metrics
- **Memory Used**: Memory consumed by operation
- **Peak Memory**: Maximum memory usage
- **Memory Growth**: Change in memory over iterations
- **Growth Rate**: Percentage increase in memory

### Database Metrics
- **Query Count**: Total queries executed
- **Queries Per Item**: Average queries per article
- **Result Count**: Number of records returned

### Cache Metrics
- **Cache Hit Rate**: Percentage of cache hits
- **Cache Time**: Time to retrieve from cache
- **Speedup Factor**: Performance improvement from caching

## Test Environment

### Default Configuration
- **Database:** SQLite in-memory (for consistency)
- **Cache:** File-based in temp directory
- **Data:** Generated test data (not production data)

### Why SQLite for Performance Tests?
1. **Consistency**: Same performance across all runs
2. **Speed**: No network overhead
3. **Isolation**: No interference from production data
4. **Portability**: Works on any development machine

### Production Testing
For production-like performance testing:
1. Run against MySQL database
2. Use production-sized datasets
3. Test with actual network latency
4. Monitor in production environment

## Interpreting Results

### Good Performance Indicators
✓ All query times < target thresholds
✓ Memory usage stable (< 1% growth rate)
✓ Cache speedup > 5x
✓ No N+1 query problems

### Warning Signs
⚠️ Query times approaching thresholds
⚠️ Memory growth > 10%
⚠️ Cache speedup < 2x
⚠️ High query counts

### Critical Issues
❌ Query times exceed thresholds
❌ Memory growth > 50%
❌ Memory leaks detected
❌ N+1 query problems

## Continuous Integration

### Adding to CI/CD Pipeline

```yaml
# .github/workflows/tests.yml
- name: Run Performance Tests
  run: composer test:performance

- name: Upload Performance Report
  uses: actions/upload-artifact@v3
  with:
    name: performance-report
    path: docs/PERFORMANCE-REPORT.md
```

### Performance Regression Testing

Set up baseline performance metrics and alert on regressions:

```bash
# Store baseline metrics
composer test:performance > baseline-performance.txt

# Compare on each run
composer test:performance > current-performance.txt
diff baseline-performance.txt current-performance.txt
```

## Troubleshooting

### Tests Running Slowly
1. Check database is SQLite (not MySQL over network)
2. Verify no other processes competing for resources
3. Check disk I/O (especially for cache tests)

### Memory Limit Errors
1. Increase PHP memory limit: `php -d memory_limit=512M vendor/bin/phpunit`
2. Reduce test data size
3. Run tests individually

### Inconsistent Results
1. Ensure no background processes running
2. Run multiple times and average results
3. Use dedicated testing environment

## Best Practices

### Writing Performance Tests

1. **Use Consistent Data**
   - Generate test data programmatically
   - Use same data size across test runs
   - Reset database state in setUp()

2. **Measure What Matters**
   - Focus on user-facing operations
   - Test realistic scenarios
   - Include edge cases

3. **Set Realistic Thresholds**
   - Base on actual requirements
   - Account for environment differences
   - Leave headroom for growth

4. **Document Bottlenecks**
   - Explain why slowness occurs
   - Provide optimization suggestions
   - Track improvements over time

### Maintaining Performance Tests

1. **Update Thresholds**
   - Review quarterly
   - Adjust for new features
   - Tighten as optimizations improve

2. **Add New Tests**
   - Test new features
   - Cover edge cases discovered in production
   - Test user-reported slow operations

3. **Monitor Trends**
   - Track metrics over time
   - Watch for gradual degradation
   - Investigate sudden changes

## Future Enhancements

### Potential Additions

1. **API Endpoint Performance**
   - Test API processing endpoint
   - Measure feed fetch time
   - Test rate limiting performance

2. **Concurrent Operations**
   - Test multiple simultaneous users
   - Measure database connection pool
   - Test cache contention

3. **Large Dataset Testing**
   - Test with 10K+ articles
   - Measure scaling characteristics
   - Identify breaking points

4. **Real-World Scenarios**
   - Test actual RSS feed parsing
   - Test Google News URL decoding
   - Test article content extraction

## Related Documentation

- [Requirements: Performance Requirements](requirements/REQUIREMENTS.md#9-performance-requirements)
- [Architecture: Database Schema](../sql/schema.sql)
- [Testing: Integration Tests](../tests/Integration/)
- [Testing: Unit Tests](../tests/Unit/)

## Support

For questions or issues with performance testing:
1. Check this documentation
2. Review performance report
3. Check test output for detailed error messages
4. Consult CLAUDE.md for project context

---

**Performance testing ensures Unfurl remains fast and responsive as it scales.**
