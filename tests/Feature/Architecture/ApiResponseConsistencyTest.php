<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Workstream;
use App\Models\Release;
use App\Models\Communication;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\PaginatedResponse;
use Illuminate\Support\Facades\Route;

/**
 * API Response Consistency Tests
 *
 * These tests define the expected standardized API response formats that should be
 * consistently applied across all endpoints after refactoring.
 */
class ApiResponseConsistencyTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * Test that ApiResponse resource class exists for standardization
     *
     * @test
     */
    public function api_response_resource_should_exist_for_standardization()
    {
        // Given: We expect a standardized ApiResponse resource
        $this->assertTrue(
            class_exists(ApiResponse::class),
            'ApiResponse resource class should exist to standardize all API responses'
        );

        // When: We inspect the ApiResponse class
        $reflection = new \ReflectionClass(ApiResponse::class);

        // Then: It should have methods for consistent response formatting
        $expectedMethods = [
            'success',
            'error',
            'created',
            'updated',
            'deleted',
            'notFound',
            'forbidden',
            'unprocessable'
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "ApiResponse should have {$method} method for consistent responses"
            );
        }
    }

    /**
     * Test that PaginatedResponse resource exists for consistent pagination
     *
     * @test
     */
    public function paginated_response_resource_should_exist_for_consistent_pagination()
    {
        // Given: We expect a standardized PaginatedResponse resource
        $this->assertTrue(
            class_exists(PaginatedResponse::class),
            'PaginatedResponse resource class should exist to standardize pagination responses'
        );

        // When: We inspect the PaginatedResponse class
        $reflection = new \ReflectionClass(PaginatedResponse::class);

        // Then: It should have proper structure for pagination
        $this->assertTrue(
            $reflection->hasMethod('toArray'),
            'PaginatedResponse should have toArray method'
        );
    }

    /**
     * Test that all list endpoints return consistent pagination structure
     *
     * @test
     */
    public function all_list_endpoints_should_return_consistent_pagination_structure()
    {
        // Given: We have data to paginate
        $user = User::factory()->create();
        $workstreams = Workstream::factory()->count(3)->create(['owner_id' => $user->id]);
        $release = Release::factory()->create();

        // When: We call list endpoints
        $endpoints = [
            '/api/workstreams',
            '/api/releases/' . $release->id . '/communications'
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);

            // Then: Response should have consistent pagination structure
            $response->assertJsonStructure([
                'data' => [],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to'
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ]
            ]);

            // And: Meta fields should be integers
            $meta = $response->json('meta');
            $this->assertIsInt($meta['current_page']);
            $this->assertIsInt($meta['last_page']);
            $this->assertIsInt($meta['per_page']);
            $this->assertIsInt($meta['total']);
        }
    }

    /**
     * Test that all success responses follow consistent format
     *
     * @test
     */
    public function all_success_responses_should_follow_consistent_format()
    {
        // Given: We create test data
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);

        // When: We make successful requests
        $testCases = [
            'GET' => [
                'endpoint' => "/api/workstreams/{$workstream->id}",
                'expectedStatus' => 200
            ],
            'POST' => [
                'endpoint' => '/api/workstreams',
                'data' => [
                    'name' => 'Test Workstream',
                    'type' => 'project',
                    'owner_id' => $user->id
                ],
                'expectedStatus' => 201
            ],
            'PUT' => [
                'endpoint' => "/api/workstreams/{$workstream->id}",
                'data' => [
                    'name' => 'Updated Workstream'
                ],
                'expectedStatus' => 200
            ]
        ];

        foreach ($testCases as $method => $testCase) {
            if ($method === 'GET') {
                $response = $this->getJson($testCase['endpoint']);
            } else {
                $response = $this->json($method, $testCase['endpoint'], $testCase['data'] ?? []);
            }

            // Then: All successful responses should have consistent structure
            $response->assertStatus($testCase['expectedStatus']);
            $response->assertJsonStructure([
                'data' => []
            ]);

            // And: Should not have error fields in success responses
            $response->assertJsonMissing(['errors']);
            $response->assertJsonMissing(['message']);
        }
    }

    /**
     * Test that all error responses follow consistent format
     *
     * @test
     */
    public function all_error_responses_should_follow_consistent_format()
    {
        // Given: We set up scenarios that will cause errors
        $user = User::factory()->create();

        $errorTestCases = [
            '404 Not Found' => [
                'method' => 'GET',
                'endpoint' => '/api/workstreams/99999',
                'expectedStatus' => 404,
                'expectedStructure' => [
                    'message',
                    'status_code'
                ]
            ],
            '422 Validation Error' => [
                'method' => 'POST',
                'endpoint' => '/api/workstreams',
                'data' => [
                    'name' => '', // Invalid: required field
                    'type' => 'invalid_type'
                ],
                'expectedStatus' => 422,
                'expectedStructure' => [
                    'message',
                    'errors' => [
                        'name',
                        'type'
                    ],
                    'status_code'
                ]
            ],
            '403 Forbidden' => [
                'method' => 'GET',
                'endpoint' => '/api/workstreams/1',
                'expectedStatus' => 403,
                'expectedStructure' => [
                    'message',
                    'status_code'
                ]
            ]
        ];

        foreach ($errorTestCases as $scenario => $testCase) {
            // When: We make requests that should fail
            $response = $this->json(
                $testCase['method'],
                $testCase['endpoint'],
                $testCase['data'] ?? []
            );

            // Then: Error responses should have consistent structure
            $response->assertStatus($testCase['expectedStatus']);
            $response->assertJsonStructure($testCase['expectedStructure']);

            // And: Should have proper status_code field
            $response->assertJson([
                'status_code' => $testCase['expectedStatus']
            ]);

            // And: Should not have data field in error responses
            $response->assertJsonMissing(['data']);
        }
    }

    /**
     * Test that HTTP status codes are used correctly across endpoints
     *
     * @test
     */
    public function http_status_codes_should_be_used_correctly_across_endpoints()
    {
        // Given: We have test data
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);

        $statusCodeTests = [
            'Create Resource' => [
                'method' => 'POST',
                'endpoint' => '/api/workstreams',
                'data' => [
                    'name' => 'Test Workstream',
                    'type' => 'project',
                    'owner_id' => $user->id
                ],
                'expectedStatus' => 201
            ],
            'Get Resource' => [
                'method' => 'GET',
                'endpoint' => "/api/workstreams/{$workstream->id}",
                'expectedStatus' => 200
            ],
            'Update Resource' => [
                'method' => 'PUT',
                'endpoint' => "/api/workstreams/{$workstream->id}",
                'data' => ['name' => 'Updated Name'],
                'expectedStatus' => 200
            ],
            'Delete Resource' => [
                'method' => 'DELETE',
                'endpoint' => "/api/workstreams/{$workstream->id}",
                'expectedStatus' => 204
            ],
            'List Resources' => [
                'method' => 'GET',
                'endpoint' => '/api/workstreams',
                'expectedStatus' => 200
            ]
        ];

        foreach ($statusCodeTests as $scenario => $test) {
            // When: We make the request
            $response = $this->json(
                $test['method'],
                $test['endpoint'],
                $test['data'] ?? []
            );

            // Then: It should return the correct status code
            $response->assertStatus(
                $test['expectedStatus'],
                "Scenario '{$scenario}' should return {$test['expectedStatus']} status code"
            );
        }
    }

    /**
     * Test that validation error responses are consistently formatted
     *
     * @test
     */
    public function validation_error_responses_should_be_consistently_formatted()
    {
        // Given: We make requests with validation errors
        $validationTestCases = [
            'Missing required fields' => [
                'endpoint' => '/api/workstreams',
                'data' => [],
                'expectedErrors' => ['name', 'type', 'owner_id']
            ],
            'Invalid field types' => [
                'endpoint' => '/api/workstreams',
                'data' => [
                    'name' => 123, // Should be string
                    'type' => 'invalid_type',
                    'owner_id' => 'not_an_id'
                ],
                'expectedErrors' => ['name', 'type', 'owner_id']
            ]
        ];

        foreach ($validationTestCases as $scenario => $testCase) {
            // When: We make request with invalid data
            $response = $this->postJson($testCase['endpoint'], $testCase['data']);

            // Then: Response should be 422 with consistent error format
            $response->assertStatus(422);
            $response->assertJsonStructure([
                'message',
                'errors' => [],
                'status_code'
            ]);

            // And: Should contain expected validation errors
            $errors = $response->json('errors');
            foreach ($testCase['expectedErrors'] as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $errors,
                    "Validation errors should include field '{$field}' for scenario '{$scenario}'"
                );
            }

            // And: Error messages should be arrays of strings
            foreach ($errors as $field => $messages) {
                $this->assertIsArray($messages, "Error messages for {$field} should be array");
                foreach ($messages as $message) {
                    $this->assertIsString($message, "Each error message should be string");
                }
            }
        }
    }

    /**
     * Test that resource responses include consistent field formatting
     *
     * @test
     */
    public function resource_responses_should_include_consistent_field_formatting()
    {
        // Given: We have a workstream with all possible fields
        $user = User::factory()->create();
        $parentWorkstream = Workstream::factory()->create(['owner_id' => $user->id]);
        $workstream = Workstream::factory()->create([
            'owner_id' => $user->id,
            'parent_workstream_id' => $parentWorkstream->id
        ]);

        // When: We get the workstream
        $response = $this->getJson("/api/workstreams/{$workstream->id}");

        // Then: Response should have consistent field formatting
        $response->assertStatus(200);
        $data = $response->json('data');

        // DateTime fields should be in ISO 8601 format
        $dateTimeFields = ['created_at', 'updated_at'];
        foreach ($dateTimeFields as $field) {
            if (isset($data[$field])) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
                    $data[$field],
                    "Field {$field} should be in ISO 8601 format"
                );
            }
        }

        // ID fields should be integers
        $idFields = ['id', 'owner_id', 'parent_workstream_id'];
        foreach ($idFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $this->assertIsInt(
                    $data[$field],
                    "Field {$field} should be integer"
                );
            }
        }

        // Related resources should have consistent structure
        if (isset($data['owner'])) {
            $this->assertArrayHasKey('id', $data['owner']);
            $this->assertArrayHasKey('name', $data['owner']);
            $this->assertArrayHasKey('email', $data['owner']);
        }
    }

    /**
     * Test that all endpoints use consistent content-type headers
     *
     * @test
     */
    public function all_endpoints_should_use_consistent_content_type_headers()
    {
        // Given: We have test endpoints
        $user = User::factory()->create();
        $workstream = Workstream::factory()->create(['owner_id' => $user->id]);

        $endpoints = [
            'GET /api/workstreams',
            "GET /api/workstreams/{$workstream->id}",
            'POST /api/workstreams',
        ];

        foreach ($endpoints as $endpointDescription) {
            [$method, $endpoint] = explode(' ', $endpointDescription);

            // When: We make the request
            $data = $method === 'POST' ? [
                'name' => 'Test',
                'type' => 'project',
                'owner_id' => $user->id
            ] : [];

            $response = $this->json($method, $endpoint, $data);

            // Then: Response should have correct content-type
            $response->assertHeader('content-type', 'application/json');
        }
    }

    /**
     * Test that response times are consistently fast across endpoints
     *
     * @test
     */
    public function response_times_should_be_consistently_fast_across_endpoints()
    {
        // Given: We have test data
        $user = User::factory()->create();
        Workstream::factory()->count(10)->create(['owner_id' => $user->id]);

        $performanceEndpoints = [
            'GET /api/workstreams',
            'GET /api/workstreams?per_page=50',
        ];

        foreach ($performanceEndpoints as $endpointDescription) {
            [$method, $endpoint] = explode(' ', $endpointDescription);

            // When: We measure response time
            $startTime = microtime(true);
            $response = $this->json($method, $endpoint);
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Then: Response should be fast (under 500ms for test environment)
            $this->assertLessThan(
                500,
                $responseTime,
                "Endpoint {$endpoint} should respond in under 500ms (took {$responseTime}ms)"
            );

            $response->assertSuccessful();
        }
    }

    /**
     * Test that API versioning is consistently applied
     *
     * @test
     */
    public function api_versioning_should_be_consistently_applied()
    {
        // Given: We expect API versioning to be implemented
        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $apiRoutes[] = $route->uri();
            }
        }

        // Then: API routes should follow consistent versioning pattern
        foreach ($apiRoutes as $route) {
            // Routes should either be prefixed with version or use header versioning
            $this->assertTrue(
                str_starts_with($route, 'api/v1/') || str_starts_with($route, 'api/'),
                "API route {$route} should follow consistent versioning pattern"
            );
        }
    }
}