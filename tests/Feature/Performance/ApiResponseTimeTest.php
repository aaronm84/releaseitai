<?php

namespace Tests\Feature\Performance;

use App\Models\Workstream;
use App\Models\Release;
use App\Models\User;
use App\Models\Communication;
use App\Models\ChecklistItemAssignment;
use App\Models\StakeholderRelease;
use App\Models\WorkstreamPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiResponseTimeTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user for API tests
        $this->user = User::factory()->create();
    }

    protected function measureApiResponseTime(string $method, string $uri, array $data = []): float
    {
        $start = microtime(true);

        switch (strtolower($method)) {
            case 'get':
                $response = $this->actingAs($this->user)->getJson($uri);
                break;
            case 'post':
                $response = $this->actingAs($this->user)->postJson($uri, $data);
                break;
            case 'put':
                $response = $this->actingAs($this->user)->putJson($uri, $data);
                break;
            case 'patch':
                $response = $this->actingAs($this->user)->patchJson($uri, $data);
                break;
            case 'delete':
                $response = $this->actingAs($this->user)->deleteJson($uri);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
        }

        $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds

        // Ensure the response was successful for valid performance measurement
        if ($response->status() >= 400) {
            $this->fail("API call failed with status {$response->status()}: {$response->content()}");
        }

        return $duration;
    }

    protected function assertApiResponseTimeUnder(float $maxTimeMs, string $method, string $uri, array $data = [], string $message = ''): void
    {
        $responseTime = $this->measureApiResponseTime($method, $uri, $data);

        $this->assertLessThan(
            $maxTimeMs,
            $responseTime,
            $message . " Expected API response time under {$maxTimeMs}ms, but took {$responseTime}ms for {$method} {$uri}"
        );
    }

    /** @test */
    public function workstream_listing_api_should_respond_within_200ms()
    {
        // Given: Large number of workstreams
        Workstream::factory()->count(500)->create();

        // Create some API routes for testing (these would normally be in routes/api.php)
        Route::get('/api/workstreams', function (Request $request) {
            return response()->json([
                'data' => Workstream::with('owner')
                    ->paginate($request->get('per_page', 50))
            ]);
        });

        // When & Then: API should respond quickly
        $this->assertApiResponseTimeUnder(
            200,
            'GET',
            '/api/workstreams',
            [],
            'Workstream listing with 500 records'
        );

        // Test with filtering
        Route::get('/api/workstreams/filter', function (Request $request) {
            return response()->json([
                'data' => Workstream::with('owner')
                    ->when($request->get('status'), function ($query, $status) {
                        $query->where('status', $status);
                    })
                    ->when($request->get('type'), function ($query, $type) {
                        $query->where('type', $type);
                    })
                    ->paginate($request->get('per_page', 50))
            ]);
        });

        $this->assertApiResponseTimeUnder(
            150,
            'GET',
            '/api/workstreams/filter?status=active&type=initiative',
            [],
            'Filtered workstream listing'
        );
    }

    /** @test */
    public function workstream_hierarchy_api_should_load_efficiently()
    {
        // Given: Complex hierarchy
        $root = Workstream::factory()->create(['parent_workstream_id' => null]);
        $children = Workstream::factory()->count(25)->create(['parent_workstream_id' => $root->id]);

        foreach ($children->take(5) as $child) {
            Workstream::factory()->count(10)->create(['parent_workstream_id' => $child->id]);
        }

        // Create hierarchy API endpoint
        Route::get('/api/workstreams/{workstream}/hierarchy', function (Workstream $workstream) {
            return response()->json([
                'data' => $workstream->buildHierarchyTree()
            ]);
        });

        // When & Then: Hierarchy loading should be fast
        $this->assertApiResponseTimeUnder(
            100,
            'GET',
            "/api/workstreams/{$root->id}/hierarchy",
            [],
            'Loading workstream hierarchy with 75 total workstreams'
        );
    }

    /** @test */
    public function release_dashboard_api_should_handle_large_datasets()
    {
        // Given: Large dataset for dashboard
        $workstreams = Workstream::factory()->count(20)->create();

        foreach ($workstreams as $workstream) {
            $releases = Release::factory()->count(50)->create(['workstream_id' => $workstream->id]);

            foreach ($releases->take(10) as $release) {
                ChecklistItemAssignment::factory()->count(15)->create([
                    'release_id' => $release->id,
                    'status' => fake()->randomElement(['pending', 'in_progress', 'completed'])
                ]);
            }
        }

        // Create dashboard API endpoint
        Route::get('/api/dashboard/releases', function (Request $request) {
            return response()->json([
                'data' => Release::with(['workstream', 'checklistItemAssignments'])
                    ->when($request->get('workstream_id'), function ($query, $workstreamId) {
                        $query->where('workstream_id', $workstreamId);
                    })
                    ->when($request->get('status'), function ($query, $status) {
                        $query->where('status', $status);
                    })
                    ->paginate($request->get('per_page', 20)),
                'stats' => [
                    'total_releases' => Release::count(),
                    'in_progress' => Release::where('status', 'in_progress')->count(),
                    'completed' => Release::where('status', 'completed')->count(),
                ]
            ]);
        });

        // When & Then: Dashboard should load quickly
        $this->assertApiResponseTimeUnder(
            300,
            'GET',
            '/api/dashboard/releases',
            [],
            'Release dashboard with 1000 releases and aggregated stats'
        );
    }

    /** @test */
    public function release_details_api_should_load_complete_information_quickly()
    {
        // Given: Release with comprehensive data
        $release = Release::factory()->create();

        // Add stakeholders
        $stakeholders = User::factory()->count(15)->create();
        foreach ($stakeholders as $stakeholder) {
            StakeholderRelease::factory()->create([
                'release_id' => $release->id,
                'user_id' => $stakeholder->id
            ]);
        }

        // Add tasks
        ChecklistItemAssignment::factory()->count(30)->create([
            'release_id' => $release->id
        ]);

        // Add communications
        Communication::factory()->count(25)->create([
            'release_id' => $release->id
        ]);

        // Create release details API endpoint
        Route::get('/api/releases/{release}', function (Release $release) {
            return response()->json([
                'data' => [
                    'release' => $release->load([
                        'workstream.owner',
                        'stakeholders',
                        'checklistItemAssignments',
                        'communications.participants.user',
                        'approvalRequests'
                    ]),
                    'stats' => [
                        'task_completion' => [
                            'total' => $release->checklistItemAssignments()->count(),
                            'completed' => $release->checklistItemAssignments()->where('status', 'completed')->count(),
                        ],
                        'approval_status' => $release->getApprovalStatus(),
                    ]
                ]
            ]);
        });

        // When & Then: Complete release details should load quickly
        $this->assertApiResponseTimeUnder(
            250,
            'GET',
            "/api/releases/{$release->id}",
            [],
            'Complete release details with all relationships'
        );
    }

    /** @test */
    public function communication_history_api_should_handle_large_volumes()
    {
        // Given: Release with extensive communication history
        $release = Release::factory()->create();
        $users = User::factory()->count(20)->create();

        $communications = Communication::factory()->count(200)->create([
            'release_id' => $release->id
        ]);

        foreach ($communications as $communication) {
            foreach ($users->random(3) as $user) {
                \App\Models\CommunicationParticipant::factory()->create([
                    'communication_id' => $communication->id,
                    'user_id' => $user->id
                ]);
            }
        }

        // Create communication history API endpoint
        Route::get('/api/releases/{release}/communications', function (Release $release, Request $request) {
            return response()->json([
                'data' => $release->communications()
                    ->with(['participants.user', 'initiatedBy'])
                    ->when($request->get('channel'), function ($query, $channel) {
                        $query->where('channel', $channel);
                    })
                    ->when($request->get('date_from'), function ($query, $dateFrom) {
                        $query->where('communication_date', '>=', $dateFrom);
                    })
                    ->orderBy('communication_date', 'desc')
                    ->paginate($request->get('per_page', 50))
            ]);
        });

        // When & Then: Communication history should load efficiently
        $this->assertApiResponseTimeUnder(
            200,
            'GET',
            "/api/releases/{$release->id}/communications",
            [],
            'Communication history with 200 communications and participants'
        );

        // Test filtered communication history
        $this->assertApiResponseTimeUnder(
            150,
            'GET',
            "/api/releases/{$release->id}/communications?channel=email&per_page=25",
            [],
            'Filtered communication history'
        );
    }

    /** @test */
    public function bulk_operations_api_should_complete_within_time_limits()
    {
        // Given: Data for bulk operations
        $workstreams = Workstream::factory()->count(100)->create(['status' => Workstream::STATUS_DRAFT]);

        // Create bulk update API endpoint
        Route::patch('/api/workstreams/bulk-update', function (Request $request) {
            $ids = $request->get('ids', []);
            $updates = $request->get('updates', []);

            $updated = Workstream::whereIn('id', $ids)->update($updates);

            return response()->json([
                'message' => 'Bulk update completed',
                'updated_count' => $updated
            ]);
        });

        // When & Then: Bulk updates should be fast
        $this->assertApiResponseTimeUnder(
            500,
            'PATCH',
            '/api/workstreams/bulk-update',
            [
                'ids' => $workstreams->pluck('id')->toArray(),
                'updates' => ['status' => Workstream::STATUS_ACTIVE]
            ],
            'Bulk update of 100 workstreams'
        );
    }

    /** @test */
    public function search_api_should_respond_quickly_with_large_datasets()
    {
        // Given: Large searchable dataset
        Workstream::factory()->count(1000)->create();
        Release::factory()->count(2000)->create();
        Communication::factory()->count(1500)->create();

        // Create search API endpoint
        Route::get('/api/search', function (Request $request) {
            $query = $request->get('q', '');
            $type = $request->get('type', 'all');

            $results = [];

            if ($type === 'all' || $type === 'workstreams') {
                $results['workstreams'] = Workstream::where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->limit(20)
                    ->get();
            }

            if ($type === 'all' || $type === 'releases') {
                $results['releases'] = Release::where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->with('workstream')
                    ->limit(20)
                    ->get();
            }

            if ($type === 'all' || $type === 'communications') {
                $results['communications'] = Communication::where('subject', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->with('release')
                    ->limit(20)
                    ->get();
            }

            return response()->json(['data' => $results]);
        });

        // When & Then: Search should be fast even with large datasets
        $this->assertApiResponseTimeUnder(
            300,
            'GET',
            '/api/search?q=test&type=all',
            [],
            'Full-text search across all entities'
        );

        // Test specific type searches
        $this->assertApiResponseTimeUnder(
            100,
            'GET',
            '/api/search?q=initiative&type=workstreams',
            [],
            'Workstream-specific search'
        );
    }

    /** @test */
    public function reporting_api_should_generate_complex_reports_efficiently()
    {
        // Given: Complex data for reporting
        $roots = Workstream::factory()->count(5)->create(['parent_workstream_id' => null]);

        foreach ($roots as $root) {
            $children = Workstream::factory()->count(8)->create(['parent_workstream_id' => $root->id]);

            foreach ($children as $child) {
                $releases = Release::factory()->count(12)->create(['workstream_id' => $child->id]);

                foreach ($releases as $release) {
                    ChecklistItemAssignment::factory()->count(10)->create([
                        'release_id' => $release->id,
                        'status' => fake()->randomElement(['pending', 'in_progress', 'completed'])
                    ]);
                }
            }
        }

        // Create rollup reporting API endpoint
        Route::get('/api/workstreams/{workstream}/rollup-report', function (Workstream $workstream) {
            return response()->json([
                'data' => $workstream->getRollupReport()
            ]);
        });

        // When & Then: Complex rollup reports should generate within time limits
        $rootWorkstream = $roots->first();
        $this->assertApiResponseTimeUnder(
            400,
            'GET',
            "/api/workstreams/{$rootWorkstream->id}/rollup-report",
            [],
            'Complex rollup report with 480 releases and 4800 tasks'
        );
    }

    /** @test */
    public function concurrent_api_requests_should_maintain_performance()
    {
        // Given: Data that might be accessed concurrently
        $workstreams = Workstream::factory()->count(50)->create();
        $releases = Release::factory()->count(200)->create();

        // Set up test routes
        Route::get('/api/test/workstreams', function () {
            return response()->json(['data' => Workstream::with('owner')->paginate(20)]);
        });

        Route::get('/api/test/releases', function () {
            return response()->json(['data' => Release::with('workstream')->paginate(20)]);
        });

        Route::get('/api/test/stats', function () {
            return response()->json([
                'data' => [
                    'workstreams_count' => Workstream::count(),
                    'releases_count' => Release::count(),
                    'active_workstreams' => Workstream::where('status', 'active')->count(),
                ]
            ]);
        });

        // When & Then: Simulate concurrent requests
        $totalTime = 0;
        $requestCount = 15;

        for ($i = 0; $i < $requestCount; $i++) {
            $endpoint = [
                '/api/test/workstreams',
                '/api/test/releases',
                '/api/test/stats'
            ][$i % 3];

            $responseTime = $this->measureApiResponseTime('GET', $endpoint);
            $totalTime += $responseTime;

            // Each individual request should still be fast
            $this->assertLessThan(
                200,
                $responseTime,
                "Individual request {$i} to {$endpoint} should be fast"
            );
        }

        // Average response time should be reasonable
        $averageTime = $totalTime / $requestCount;
        $this->assertLessThan(
            150,
            $averageTime,
            "Average response time over {$requestCount} requests should be under 150ms, but was {$averageTime}ms"
        );
    }

    /** @test */
    public function api_with_database_connection_pooling_should_be_efficient()
    {
        // Given: Setup for testing connection efficiency
        Workstream::factory()->count(100)->create();

        Route::get('/api/test/rapid-queries', function () {
            $results = [];

            // Simulate rapid consecutive database queries
            for ($i = 0; $i < 20; $i++) {
                $results[] = Workstream::count();
                $results[] = Workstream::where('status', 'active')->count();
                $results[] = Release::count();
            }

            return response()->json(['data' => $results]);
        });

        // When & Then: Rapid queries should not cause connection issues
        $this->assertApiResponseTimeUnder(
            300,
            'GET',
            '/api/test/rapid-queries',
            [],
            'Rapid consecutive database queries should benefit from connection pooling'
        );
    }

    /** @test */
    public function api_caching_should_improve_response_times()
    {
        // Given: Data for caching test
        Workstream::factory()->count(200)->create();

        Route::get('/api/test/cacheable-stats', function () {
            // This endpoint would normally implement caching
            $stats = [
                'total_workstreams' => Workstream::count(),
                'workstreams_by_status' => Workstream::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status'),
                'workstreams_by_type' => Workstream::selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->get()
                    ->pluck('count', 'type'),
            ];

            return response()->json(['data' => $stats]);
        });

        // When & Then: First request (cache miss)
        $firstRequestTime = $this->measureApiResponseTime('GET', '/api/test/cacheable-stats');

        // Second request (should be faster with caching - this will FAIL initially without caching)
        $secondRequestTime = $this->measureApiResponseTime('GET', '/api/test/cacheable-stats');

        $this->assertLessThan(
            50,
            $secondRequestTime,
            'Cached response should be much faster than initial request. Consider implementing API response caching.'
        );

        // The second request should be significantly faster (when caching is implemented)
        $this->assertLessThan(
            $firstRequestTime * 0.3, // 30% of original time
            $secondRequestTime,
            'Cached request should be significantly faster than uncached request'
        );
    }
}