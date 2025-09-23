<?php

namespace Tests\Unit\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Stakeholder;
use App\Models\Feedback;
use App\Models\Input;
use App\Models\Output;

/**
 * Base class for all performance tests.
 * Provides utilities for measuring query performance, creating test data, and validating performance requirements.
 */
abstract class BasePerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Performance thresholds (in milliseconds)
     */
    protected const MAX_QUERY_TIME_MS = 100;
    protected const MAX_ACCEPTABLE_QUERIES = 10;
    protected const MAX_BULK_OPERATION_TIME_MS = 500;
    protected const MAX_INDEX_SCAN_TIME_MS = 50;

    /**
     * Test data sizes for performance testing
     */
    protected const SMALL_DATASET_SIZE = 100;
    protected const MEDIUM_DATASET_SIZE = 1000;
    protected const LARGE_DATASET_SIZE = 5000;

    protected User $testUser;
    protected array $queryLog = [];
    protected float $startTime;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->testUser = User::factory()->create([
            'name' => 'Performance Test User',
            'email' => 'perf-test@example.com'
        ]);

        // Clear cache
        Cache::flush();

        // Reset query log
        $this->queryLog = [];
    }

    /**
     * Start monitoring query performance
     */
    protected function startQueryMonitoring(): void
    {
        $this->queryLog = [];
        $this->startTime = microtime(true);

        DB::enableQueryLog();

        DB::listen(function ($query) {
            $this->queryLog[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'explain' => $this->getQueryExplanation($query->sql, $query->bindings)
            ];
        });
    }

    /**
     * Stop monitoring and return performance metrics
     */
    protected function stopQueryMonitoring(): array
    {
        DB::disableQueryLog();

        $endTime = microtime(true);
        $totalTime = ($endTime - $this->startTime) * 1000; // Convert to milliseconds

        return [
            'total_time_ms' => $totalTime,
            'query_count' => count($this->queryLog),
            'queries' => $this->queryLog,
            'slow_queries' => $this->getSlowQueries(),
            'sequential_scans' => $this->getSequentialScans()
        ];
    }

    /**
     * Get query explanation for performance analysis
     */
    protected function getQueryExplanation(string $sql, array $bindings): ?array
    {
        try {
            // Replace ? placeholders with actual values for EXPLAIN
            $explainSql = $sql;
            foreach ($bindings as $binding) {
                $explainSql = preg_replace('/\?/', "'" . addslashes($binding) . "'", $explainSql, 1);
            }

            $explanation = DB::select("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) {$explainSql}");
            return json_decode($explanation[0]->{'QUERY PLAN'}, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get queries that exceeded performance thresholds
     */
    protected function getSlowQueries(): array
    {
        return array_filter($this->queryLog, function ($query) {
            return $query['time'] > self::MAX_QUERY_TIME_MS;
        });
    }

    /**
     * Detect queries using sequential scans instead of indexes
     */
    protected function getSequentialScans(): array
    {
        $sequentialScans = [];

        foreach ($this->queryLog as $query) {
            if ($query['explain'] && $this->hasSequentialScan($query['explain'])) {
                $sequentialScans[] = $query;
            }
        }

        return $sequentialScans;
    }

    /**
     * Check if query plan contains sequential scan
     */
    protected function hasSequentialScan(array $plan): bool
    {
        if (isset($plan[0]['Plan']['Node Type']) && $plan[0]['Plan']['Node Type'] === 'Seq Scan') {
            return true;
        }

        // Recursively check child plans
        if (isset($plan[0]['Plan']['Plans'])) {
            foreach ($plan[0]['Plan']['Plans'] as $childPlan) {
                if ($this->hasSequentialScan([['Plan' => $childPlan]])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assert that query performance meets requirements
     */
    protected function assertQueryPerformance(array $metrics, array $requirements = []): void
    {
        $maxTime = $requirements['max_time_ms'] ?? self::MAX_QUERY_TIME_MS;
        $maxQueries = $requirements['max_queries'] ?? self::MAX_ACCEPTABLE_QUERIES;

        $this->assertLessThanOrEqual(
            $maxTime,
            $metrics['total_time_ms'],
            "Total query time {$metrics['total_time_ms']}ms exceeded maximum {$maxTime}ms"
        );

        $this->assertLessThanOrEqual(
            $maxQueries,
            $metrics['query_count'],
            "Query count {$metrics['query_count']} exceeded maximum {$maxQueries}"
        );

        $this->assertEmpty(
            $metrics['slow_queries'],
            'Found slow queries: ' . json_encode($metrics['slow_queries'])
        );
    }

    /**
     * Assert that queries use proper indexes (no sequential scans)
     */
    protected function assertNoSequentialScans(array $metrics): void
    {
        $this->assertEmpty(
            $metrics['sequential_scans'],
            'Found queries using sequential scans instead of indexes: ' .
            json_encode(array_column($metrics['sequential_scans'], 'sql'))
        );
    }

    /**
     * Create test feedback data in bulk
     */
    protected function createTestFeedbackData(int $count = self::MEDIUM_DATASET_SIZE): array
    {
        // Create inputs and outputs first
        $inputs = Input::factory($count)->create();
        $outputs = [];

        foreach ($inputs as $input) {
            $outputs[] = Output::factory()->create(['input_id' => $input->id]);
        }

        // Create feedback records
        $feedbackData = [];
        foreach ($outputs as $output) {
            $feedbackData[] = Feedback::factory()->create([
                'output_id' => $output->id,
                'user_id' => $this->testUser->id,
                'type' => fake()->randomElement(['inline', 'behavioral']),
                'action' => fake()->randomElement(['thumbs_up', 'thumbs_down', 'edit', 'copy']),
                'signal_type' => fake()->randomElement(['explicit', 'passive']),
                'confidence' => fake()->randomFloat(2, 0, 1)
            ]);
        }

        return [
            'inputs' => $inputs,
            'outputs' => $outputs,
            'feedback' => $feedbackData
        ];
    }

    /**
     * Create test workstream hierarchy data
     */
    protected function createTestWorkstreamHierarchy(
        int $depth = 4,
        int $branchingFactor = 5
    ): array {
        return Workstream::createHierarchyFast($depth, $branchingFactor, $this->testUser);
    }

    /**
     * Create test release and stakeholder data
     */
    protected function createTestReleaseStakeholderData(int $releaseCount = 100, int $stakeholderCount = 50): array
    {
        // Create workstreams for releases
        $workstreams = Workstream::factory($releaseCount / 2)->create([
            'owner_id' => $this->testUser->id
        ]);

        // Create releases
        $releases = [];
        foreach ($workstreams as $workstream) {
            $releases[] = Release::factory()->create(['workstream_id' => $workstream->id]);
            $releases[] = Release::factory()->create(['workstream_id' => $workstream->id]);
        }

        // Create stakeholders
        $stakeholders = Stakeholder::factory($stakeholderCount)->create([
            'user_id' => $this->testUser->id
        ]);

        // Create stakeholder-release relationships
        $stakeholderReleases = [];
        foreach ($releases as $release) {
            $releaseStakeholders = fake()->randomElements(
                $stakeholders->toArray(),
                fake()->numberBetween(1, 5)
            );

            foreach ($releaseStakeholders as $stakeholder) {
                $stakeholderReleases[] = [
                    'release_id' => $release->id,
                    'user_id' => $stakeholder['user_id'],
                    'role' => fake()->randomElement(['owner', 'reviewer', 'approver', 'observer']),
                    'notification_preference' => fake()->randomElement(['email', 'slack', 'none']),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // Bulk insert stakeholder releases
        DB::table('stakeholder_releases')->insert($stakeholderReleases);

        return [
            'workstreams' => $workstreams,
            'releases' => $releases,
            'stakeholders' => $stakeholders,
            'stakeholder_releases' => $stakeholderReleases
        ];
    }

    /**
     * Check if a specific index exists
     */
    protected function assertIndexExists(string $table, string $indexName): void
    {
        $indexes = DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ", [$table, $indexName]);

        $this->assertNotEmpty(
            $indexes,
            "Index {$indexName} does not exist on table {$table}"
        );
    }

    /**
     * Check if composite index exists with specific columns
     */
    protected function assertCompositeIndexExists(string $table, array $columns): void
    {
        $columnList = implode(', ', $columns);

        $indexes = DB::select("
            SELECT i.relname as index_name,
                   array_agg(a.attname ORDER BY c.ordinality) as columns
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN unnest(ix.indkey) WITH ORDINALITY AS c(attnum, ordinality) ON true
            JOIN pg_attribute a ON t.oid = a.attrelid AND a.attnum = c.attnum
            WHERE t.relname = ?
            GROUP BY i.relname
            HAVING array_agg(a.attname ORDER BY c.ordinality) = ?
        ", [$table, '{' . $columnList . '}']);

        $this->assertNotEmpty(
            $indexes,
            "Composite index on columns [{$columnList}] does not exist on table {$table}"
        );
    }

    /**
     * Measure cache performance under concurrent access
     */
    protected function measureCachePerformance(callable $operation, int $iterations = 100): array
    {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $operation();
            $endTime = microtime(true);

            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }

        return [
            'iterations' => $iterations,
            'total_time_ms' => array_sum($times),
            'average_time_ms' => array_sum($times) / count($times),
            'min_time_ms' => min($times),
            'max_time_ms' => max($times),
            'times' => $times
        ];
    }

    /**
     * Simulate N+1 query scenario and measure
     */
    protected function simulateN1Scenario(callable $scenario): array
    {
        // Clear any existing query logs
        DB::flushQueryLog();

        // Run the scenario without transaction wrapper to avoid conflicts
        $this->startQueryMonitoring();
        $result = $scenario();
        $metrics = $this->stopQueryMonitoring();

        return array_merge($metrics, ['result' => $result]);
    }

    /**
     * Assert that no N+1 queries occurred
     */
    protected function assertNoN1Queries(array $metrics, int $expectedQueryCount): void
    {
        $this->assertLessThanOrEqual(
            $expectedQueryCount,
            $metrics['query_count'],
            "Expected maximum {$expectedQueryCount} queries, but {$metrics['query_count']} were executed. " .
            "This indicates a possible N+1 query problem."
        );
    }
}