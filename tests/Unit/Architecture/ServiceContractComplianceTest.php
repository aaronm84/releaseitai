<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use App\Services\WorkstreamService;
use App\Services\CommunicationService;
use App\Services\PermissionService;
use App\Services\PaginationService;
use App\Contracts\WorkstreamServiceInterface;
use App\Contracts\CommunicationServiceInterface;
use App\Contracts\PermissionServiceInterface;
use App\Contracts\PaginationServiceInterface;

/**
 * Service Contract Compliance Unit Tests
 *
 * These unit tests verify that services properly implement their contracts
 * and can be mocked/tested in isolation.
 */
class ServiceContractComplianceTest extends TestCase
{
    /**
     * Test that WorkstreamService can be resolved from container
     *
     * @test
     */
    public function workstream_service_can_be_resolved_from_container()
    {
        // Given: We expect the service to be bound in container
        // When: We resolve the service
        $service = app(WorkstreamServiceInterface::class);

        // Then: It should return the correct implementation
        $this->assertInstanceOf(WorkstreamService::class, $service);
        $this->assertInstanceOf(WorkstreamServiceInterface::class, $service);
    }

    /**
     * Test that WorkstreamService implements required methods with correct signatures
     *
     * @test
     */
    public function workstream_service_implements_required_methods_with_correct_signatures()
    {
        // Given: We have the service interface
        $interface = WorkstreamServiceInterface::class;
        $implementation = WorkstreamService::class;

        // Then: Implementation should have all interface methods
        $interfaceReflection = new \ReflectionClass($interface);
        $implementationReflection = new \ReflectionClass($implementation);

        foreach ($interfaceReflection->getMethods() as $method) {
            $this->assertTrue(
                $implementationReflection->hasMethod($method->getName()),
                "WorkstreamService should implement {$method->getName()} method"
            );

            $implementedMethod = $implementationReflection->getMethod($method->getName());

            // Check parameter count matches
            $this->assertEquals(
                $method->getNumberOfParameters(),
                $implementedMethod->getNumberOfParameters(),
                "Method {$method->getName()} should have correct parameter count"
            );

            // Check return type matches
            $interfaceReturnType = $method->getReturnType();
            $implementedReturnType = $implementedMethod->getReturnType();

            if ($interfaceReturnType && $implementedReturnType) {
                $this->assertEquals(
                    $interfaceReturnType->getName(),
                    $implementedReturnType->getName(),
                    "Method {$method->getName()} should have correct return type"
                );
            }
        }
    }

    /**
     * Test that CommunicationService can be mocked for testing
     *
     * @test
     */
    public function communication_service_can_be_mocked_for_testing()
    {
        // Given: We create a mock of the service interface
        $mockService = $this->createMock(CommunicationServiceInterface::class);

        // When: We configure the mock
        $mockService->method('createCommunication')
            ->willReturn(['id' => 1, 'subject' => 'Test Communication']);

        $mockService->method('getCommunicationsForRelease')
            ->willReturn(['data' => [], 'meta' => ['total' => 0]]);

        // Then: Mock should behave as expected
        $result = $mockService->createCommunication([], []);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Test Communication', $result['subject']);

        $communications = $mockService->getCommunicationsForRelease(1, []);
        $this->assertArrayHasKey('data', $communications);
        $this->assertArrayHasKey('meta', $communications);
    }

    /**
     * Test that PermissionService follows contract specifications
     *
     * @test
     */
    public function permission_service_follows_contract_specifications()
    {
        // Given: We expect specific method signatures for permission checking
        $interface = PermissionServiceInterface::class;
        $reflection = new \ReflectionClass($interface);

        // Then: Interface should have core permission methods
        $expectedMethods = [
            'canAccessWorkstream' => ['user_id', 'workstream_id', 'permission_type'],
            'canEditWorkstream' => ['user_id', 'workstream_id'],
            'canAdminWorkstream' => ['user_id', 'workstream_id'],
            'getEffectivePermissions' => ['user_id', 'workstream_id'],
        ];

        foreach ($expectedMethods as $methodName => $expectedParams) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "PermissionServiceInterface should have {$methodName} method"
            );

            $method = $reflection->getMethod($methodName);
            $parameters = $method->getParameters();

            $this->assertEquals(
                count($expectedParams),
                count($parameters),
                "Method {$methodName} should have " . count($expectedParams) . " parameters"
            );

            // Check parameter names match expected
            foreach ($parameters as $index => $parameter) {
                $this->assertEquals(
                    $expectedParams[$index],
                    $parameter->getName(),
                    "Parameter {$index} of {$methodName} should be named {$expectedParams[$index]}"
                );
            }
        }
    }

    /**
     * Test that PaginationService provides consistent interface
     *
     * @test
     */
    public function pagination_service_provides_consistent_interface()
    {
        // Given: We expect pagination methods to have specific signatures
        $interface = PaginationServiceInterface::class;
        $reflection = new \ReflectionClass($interface);

        // Then: Should have pagination methods with correct signatures
        $paginateMethod = $reflection->getMethod('paginate');
        $this->assertTrue($paginateMethod->hasReturnType());

        $formatMethod = $reflection->getMethod('formatPaginationResponse');
        $this->assertTrue($formatMethod->hasReturnType());

        // Method should accept standard pagination parameters
        $parameters = $paginateMethod->getParameters();
        $parameterNames = array_map(fn($p) => $p->getName(), $parameters);

        $this->assertContains('query', $parameterNames);
        $this->assertContains('per_page', $parameterNames);
        $this->assertContains('page', $parameterNames);
    }

    /**
     * Test that services can be swapped through dependency injection
     *
     * @test
     */
    public function services_can_be_swapped_through_dependency_injection()
    {
        // Given: We create alternative implementations
        $alternativeWorkstreamService = new class implements WorkstreamServiceInterface {
            public function getWorkstreams(array $filters = [], int $perPage = 50): array
            {
                return ['data' => 'alternative_implementation'];
            }

            public function createWorkstream(array $data): array
            {
                return ['id' => 999, 'name' => 'Alternative Created'];
            }

            public function updateWorkstream(int $id, array $data): array
            {
                return ['id' => $id, 'updated' => true];
            }

            public function deleteWorkstream(int $id): bool
            {
                return true;
            }

            public function moveWorkstream(int $id, ?int $newParentId): array
            {
                return ['id' => $id, 'moved' => true];
            }

            public function bulkUpdateWorkstreams(array $ids, array $data): array
            {
                return ['updated_count' => count($ids)];
            }

            public function getHierarchy(int $workstreamId): array
            {
                return ['hierarchy' => 'alternative'];
            }

            public function getRollupReport(int $workstreamId): array
            {
                return ['report' => 'alternative'];
            }

            public function validateHierarchyConstraints(int $workstreamId, ?int $newParentId): bool
            {
                return true;
            }

            public function checkPermissions(int $userId, int $workstreamId, string $permission): bool
            {
                return true;
            }
        };

        // When: We bind the alternative implementation
        app()->bind(WorkstreamServiceInterface::class, function () use ($alternativeWorkstreamService) {
            return $alternativeWorkstreamService;
        });

        // Then: The alternative should be resolved
        $resolvedService = app(WorkstreamServiceInterface::class);
        $result = $resolvedService->getWorkstreams();

        $this->assertEquals('alternative_implementation', $result['data']);
    }

    /**
     * Test that service interfaces define proper exception contracts
     *
     * @test
     */
    public function service_interfaces_define_proper_exception_contracts()
    {
        // Given: We expect service methods to document exceptions
        $interfaces = [
            WorkstreamServiceInterface::class,
            CommunicationServiceInterface::class,
            PermissionServiceInterface::class,
        ];

        foreach ($interfaces as $interfaceClass) {
            $reflection = new \ReflectionClass($interfaceClass);
            $methods = $reflection->getMethods();

            foreach ($methods as $method) {
                $docComment = $method->getDocComment();

                // Then: Methods that can fail should document exceptions
                if (in_array($method->getName(), ['createWorkstream', 'updateWorkstream', 'deleteWorkstream'])) {
                    $this->assertStringContains(
                        '@throws',
                        $docComment,
                        "Method {$method->getName()} should document thrown exceptions"
                    );
                }

                // And: Permission methods should indicate what they return
                if (str_contains($method->getName(), 'can') || str_contains($method->getName(), 'check')) {
                    $returnType = $method->getReturnType();
                    $this->assertNotNull($returnType, "Permission method {$method->getName()} should have return type");
                }
            }
        }
    }

    /**
     * Test that services follow consistent naming conventions
     *
     * @test
     */
    public function services_follow_consistent_naming_conventions()
    {
        // Given: We expect consistent method naming across services
        $serviceInterfaces = [
            WorkstreamServiceInterface::class,
            CommunicationServiceInterface::class,
            PermissionServiceInterface::class,
            PaginationServiceInterface::class,
        ];

        foreach ($serviceInterfaces as $interfaceClass) {
            $reflection = new \ReflectionClass($interfaceClass);
            $methods = $reflection->getMethods();

            foreach ($methods as $method) {
                $methodName = $method->getName();

                // Then: Method names should follow conventions
                // CRUD operations should start with appropriate verbs
                if (str_starts_with($methodName, 'get') || str_starts_with($methodName, 'find')) {
                    $returnType = $method->getReturnType();
                    $this->assertNotNull($returnType, "Getter method {$methodName} should have return type");
                }

                if (str_starts_with($methodName, 'create') || str_starts_with($methodName, 'store')) {
                    $this->assertGreaterThan(0, $method->getNumberOfParameters(),
                        "Creator method {$methodName} should have parameters");
                }

                if (str_starts_with($methodName, 'update')) {
                    $this->assertGreaterThanOrEqual(2, $method->getNumberOfParameters(),
                        "Update method {$methodName} should have id and data parameters");
                }

                if (str_starts_with($methodName, 'delete') || str_starts_with($methodName, 'destroy')) {
                    $this->assertGreaterThan(0, $method->getNumberOfParameters(),
                        "Delete method {$methodName} should have id parameter");
                }

                // Boolean methods should start with appropriate prefixes
                $booleanPrefixes = ['can', 'is', 'has', 'should', 'validate'];
                foreach ($booleanPrefixes as $prefix) {
                    if (str_starts_with($methodName, $prefix)) {
                        $returnType = $method->getReturnType();
                        if ($returnType) {
                            $this->assertEquals('bool', $returnType->getName(),
                                "Boolean method {$methodName} should return bool");
                        }
                    }
                }
            }
        }
    }

    /**
     * Test that service implementations are properly typed
     *
     * @test
     */
    public function service_implementations_are_properly_typed()
    {
        // Given: We expect all service implementations to have proper typing
        $services = [
            WorkstreamService::class,
            CommunicationService::class,
            PermissionService::class,
            PaginationService::class,
        ];

        foreach ($services as $serviceClass) {
            if (!class_exists($serviceClass)) {
                $this->markTestSkipped("Service {$serviceClass} does not exist yet");
                continue;
            }

            $reflection = new \ReflectionClass($serviceClass);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if ($method->isConstructor() || $method->isDestructor()) {
                    continue;
                }

                // Then: All public methods should have return types
                $this->assertTrue(
                    $method->hasReturnType(),
                    "Service method {$serviceClass}::{$method->getName()} should have return type"
                );

                // And: All parameters should be typed
                foreach ($method->getParameters() as $parameter) {
                    $this->assertTrue(
                        $parameter->hasType(),
                        "Parameter {$parameter->getName()} in {$serviceClass}::{$method->getName()} should be typed"
                    );
                }
            }
        }
    }
}