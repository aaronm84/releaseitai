# Performance Test Suite

This comprehensive performance test suite is designed to identify and prevent performance bottlenecks in the ReleaseIt application. The tests follow Test-Driven Development (TDD) principles and will **initially FAIL** to guide your optimization work.

## Overview

The performance test suite consists of 5 main categories:

1. **N+1 Query Prevention Tests** - Detect and prevent database N+1 query problems
2. **Database Query Optimization Tests** - Ensure queries execute within time limits
3. **Memory Usage Tests** - Verify memory usage stays within reasonable limits
4. **API Response Time Tests** - Test API endpoint performance under load
5. **Hierarchy Performance Tests** - Optimize workstream hierarchy operations

## Quick Start

### Run All Performance Tests
```bash
php artisan test tests/Feature/Performance/
```

### Run Individual Test Categories
```bash
# N+1 Query Prevention
php artisan test tests/Feature/Performance/NPlusOneQueryPreventionTest.php

# Database Optimization
php artisan test tests/Feature/Performance/DatabaseQueryOptimizationTest.php

# Memory Usage
php artisan test tests/Feature/Performance/MemoryUsageTest.php

# API Response Times
php artisan test tests/Feature/Performance/ApiResponseTimeTest.php

# Hierarchy Performance
php artisan test tests/Feature/Performance/HierarchyPerformanceTest.php
```

## Expected Results

### Initial Test Run (Before Optimization)
❌ **Most tests will FAIL** - this is expected and intentional!

The tests define strict performance requirements that guide optimization work:
- Hierarchy traversal should complete in <50ms
- Loading 1000 workstreams should use <50MB memory
- API endpoints should respond in <200ms
- N+1 queries should be eliminated through eager loading

### After Optimization
✅ Tests should PASS once proper optimizations are implemented

## Test Categories Explained

### 1. N+1 Query Prevention Tests

**Purpose**: Detect expensive N+1 query patterns in relationship loading

**Key Tests**:
- `hierarchy_traversal_with_50_child_workstreams_should_use_only_3_queries()`
- `loading_release_with_all_stakeholders_should_not_cause_n_plus_one()`
- `rollup_reporting_across_100_workstreams_should_minimize_queries()`

**Common Failures & Solutions**:
```php
// ❌ This causes N+1 queries
foreach ($workstreams as $workstream) {
    echo $workstream->owner->name; // Queries database for each owner
}

// ✅ Use eager loading instead
$workstreams = Workstream::with('owner')->get();
foreach ($workstreams as $workstream) {
    echo $workstream->owner->name; // Owner already loaded
}
```

### 2. Database Query Optimization Tests

**Purpose**: Ensure database queries execute within acceptable time limits

**Key Tests**:
- `workstream_hierarchy_queries_should_complete_under_100ms()`
- `workstream_search_queries_should_use_proper_indexes()`
- `aggregation_queries_should_be_optimized()`

**Required Database Indexes**:
```sql
-- Workstreams
CREATE INDEX idx_workstreams_status ON workstreams(status);
CREATE INDEX idx_workstreams_type ON workstreams(type);
CREATE INDEX idx_workstreams_parent ON workstreams(parent_workstream_id);
CREATE INDEX idx_workstreams_status_type ON workstreams(status, type);

-- Releases
CREATE INDEX idx_releases_status ON releases(status);
CREATE INDEX idx_releases_target_date ON releases(target_date);
CREATE INDEX idx_releases_workstream_status ON releases(workstream_id, status);

-- Communications
CREATE INDEX idx_communications_priority ON communications(priority);
CREATE INDEX idx_communications_channel ON communications(channel);
CREATE INDEX idx_communications_date ON communications(communication_date);
```

### 3. Memory Usage Tests

**Purpose**: Verify memory usage stays within limits for large datasets

**Key Tests**:
- `loading_1000_workstreams_should_not_exceed_50mb_memory()`
- `processing_large_communication_dataset_should_manage_memory_efficiently()`
- `large_result_set_processing_should_use_streaming()`

**Memory Optimization Techniques**:
```php
// ❌ Loads everything into memory
$allReleases = Release::with('stakeholders')->get(); // Potentially huge memory usage

// ✅ Use chunking for large datasets
Release::with('stakeholders')->chunk(100, function ($releases) {
    foreach ($releases as $release) {
        // Process each release
    }
});

// ✅ Use lazy collections for streaming
Release::with('stakeholders')->lazy(500)->each(function ($release) {
    // Process one by one without loading all into memory
});
```

### 4. API Response Time Tests

**Purpose**: Ensure API endpoints respond within time limits under realistic load

**Key Tests**:
- `workstream_listing_api_should_respond_within_200ms()`
- `release_dashboard_api_should_handle_large_datasets()`
- `communication_history_api_should_handle_large_volumes()`

**API Optimization Strategies**:
```php
// ❌ Slow API endpoint
Route::get('/api/workstreams', function () {
    return Workstream::all(); // No pagination, no eager loading
});

// ✅ Optimized API endpoint
Route::get('/api/workstreams', function (Request $request) {
    return Workstream::with('owner')
        ->when($request->status, fn($q, $status) => $q->where('status', $status))
        ->paginate(50);
});
```

### 5. Hierarchy Performance Tests

**Purpose**: Optimize workstream hierarchy operations and traversal

**Key Tests**:
- `hierarchy_traversal_should_complete_under_50ms()`
- `permission_inheritance_calculation_should_be_efficient()`
- `rollup_reporting_should_complete_under_200ms()`

**Hierarchy Optimization Techniques**:
```php
// ❌ Inefficient hierarchy traversal
public function getAllDescendants(): Collection
{
    $descendants = collect();
    foreach ($this->childWorkstreams as $child) {
        $descendants->push($child);
        $descendants = $descendants->merge($child->getAllDescendants()); // N+1 queries
    }
    return $descendants;
}

// ✅ Optimized with eager loading
public function getAllDescendants(): Collection
{
    return $this->descendantsAndSelf()->where('id', '!=', $this->id);
}

public function descendantsAndSelf()
{
    return Workstream::where('hierarchy_path', 'like', $this->hierarchy_path . '%');
}
```

## Performance Optimization Workflow

### 1. Run Tests to Identify Bottlenecks
```bash
php artisan test tests/Feature/Performance/ --verbose
```

### 2. Analyze Failing Tests
- Note which tests fail and their error messages
- Focus on the most critical performance issues first
- Prioritize based on user impact and frequency of use

### 3. Implement Optimizations

#### Database Indexes
Create migration for performance indexes:
```bash
php artisan make:migration add_performance_indexes
```

#### Eager Loading
Update models to use eager loading:
```php
// In Workstream model
protected $with = ['owner']; // Always load owner

// In controllers
$workstreams = Workstream::with(['childWorkstreams.owner', 'releases'])->get();
```

#### Caching
Implement caching for expensive operations:
```php
public function buildHierarchyTree(): array
{
    return Cache::remember("workstream_hierarchy_{$this->id}", 3600, function () {
        return $this->generateHierarchyTree();
    });
}
```

#### Memory Management
Use chunking for large datasets:
```php
Communication::with('participants')->chunk(200, function ($communications) {
    foreach ($communications as $communication) {
        // Process without loading all into memory
    }
});
```

### 4. Re-run Tests to Verify Improvements
```bash
php artisan test tests/Feature/Performance/
```

### 5. Monitor and Iterate
- Continue until all critical tests pass
- Monitor performance in production
- Add new tests as new features are developed

## Performance Targets

| Operation | Target | Test |
|-----------|--------|------|
| Hierarchy traversal (50 children) | <50ms | `hierarchy_traversal_should_complete_under_50ms()` |
| Loading 1000 workstreams | <50MB memory | `loading_1000_workstreams_should_not_exceed_50mb_memory()` |
| API workstream listing | <200ms | `workstream_listing_api_should_respond_within_200ms()` |
| Release dashboard load | <300ms | `release_dashboard_api_should_handle_large_datasets()` |
| Rollup reporting (100 workstreams) | <200ms | `rollup_reporting_across_100_workstreams_should_minimize_queries()` |
| Permission inheritance | <75ms | `permission_inheritance_calculation_should_be_efficient()` |

## Common Performance Issues & Solutions

### Issue: N+1 Queries in Hierarchy Traversal
**Symptom**: `hierarchy_traversal_with_50_child_workstreams_should_use_only_3_queries()` fails with 50+ queries
**Solution**: Implement eager loading in `buildHierarchyTree()` method

### Issue: Slow Search Queries
**Symptom**: `workstream_search_queries_should_use_proper_indexes()` fails with >100ms execution time
**Solution**: Add database indexes on frequently searched columns

### Issue: High Memory Usage
**Symptom**: `loading_1000_workstreams_should_not_exceed_50mb_memory()` fails with >50MB usage
**Solution**: Implement chunking and lazy loading for large datasets

### Issue: Slow API Responses
**Symptom**: `workstream_listing_api_should_respond_within_200ms()` fails
**Solution**: Add pagination, eager loading, and response caching

### Issue: Inefficient Rollup Reporting
**Symptom**: `rollup_reporting_across_100_workstreams_should_minimize_queries()` fails with many queries
**Solution**: Optimize with bulk queries and reduce nested loops

## Advanced Optimization Techniques

### 1. Materialized Path for Hierarchies
```php
// Add to migration
$table->string('hierarchy_path')->index();

// Update on save
public function updateHierarchyPath()
{
    $path = $this->parent ? $this->parent->hierarchy_path . '/' . $this->id : $this->id;
    $this->update(['hierarchy_path' => $path]);
}

// Efficient descendant queries
public function descendants()
{
    return static::where('hierarchy_path', 'like', $this->hierarchy_path . '/%');
}
```

### 2. Database-Level Recursive Queries
```php
public function getAllDescendants(): Collection
{
    return DB::select("
        WITH RECURSIVE descendant_tree AS (
            SELECT id, parent_workstream_id, name, 1 as level
            FROM workstreams
            WHERE parent_workstream_id = ?

            UNION ALL

            SELECT w.id, w.parent_workstream_id, w.name, dt.level + 1
            FROM workstreams w
            INNER JOIN descendant_tree dt ON w.parent_workstream_id = dt.id
        )
        SELECT * FROM descendant_tree
    ", [$this->id]);
}
```

### 3. Query Result Caching
```php
public function scopeCached($query, $key, $ttl = 3600)
{
    return Cache::remember($key, $ttl, function () use ($query) {
        return $query->get();
    });
}

// Usage
$workstreams = Workstream::where('status', 'active')->cached('active_workstreams', 1800);
```

## Continuous Performance Monitoring

### 1. Add Performance Tests to CI/CD
```yaml
# .github/workflows/performance.yml
- name: Run Performance Tests
  run: php artisan test tests/Feature/Performance/ --stop-on-failure
```

### 2. Performance Regression Detection
- Run performance tests on every pull request
- Set up alerts for performance regressions
- Track performance metrics over time

### 3. Production Monitoring
- Monitor query execution times
- Track memory usage patterns
- Set up alerts for slow API responses
- Use tools like Laravel Telescope for query analysis

## Getting Help

If performance tests continue to fail after implementing optimizations:

1. **Review Query Logs**: Enable query logging to see actual SQL queries
2. **Check Database Indexes**: Verify indexes are created and being used
3. **Profile Memory Usage**: Use Xdebug or similar tools for memory profiling
4. **Analyze API Responses**: Use tools like Laravel Telescope or Clockwork
5. **Consider Architecture Changes**: For persistent issues, consider architectural improvements

Remember: These tests are designed to guide optimization work. Failing tests indicate areas that need attention, not problems with the tests themselves.