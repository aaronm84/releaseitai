<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * Architecture Compliance Tests
 *
 * These tests verify that the application follows SOLID principles, proper dependency injection,
 * Laravel conventions, and exception handling patterns for maintainable architecture.
 */
class ArchitectureComplianceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Single Responsibility Principle (SRP) is followed
     *
     * @test
     */
    public function classes_should_follow_single_responsibility_principle()
    {
        // Given: We analyze classes for SRP compliance
        $srpViolations = [];
        $classFiles = $this->getAllClassFiles();

        foreach ($classFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (!$className) continue;

            try {
                $reflection = new ReflectionClass($className);
                $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

                // Check method count (too many methods might indicate SRP violation)
                if (count($methods) > 15) {
                    $srpViolations[] = "{$className}: Too many public methods (" . count($methods) . "), consider splitting responsibilities";
                }

                // Check for mixed concerns in method names
                $concerns = $this->analyzeConcerns($methods);
                if (count($concerns) > 3) {
                    $srpViolations[] = "{$className}: Mixed concerns detected: " . implode(', ', $concerns);
                }

                // Controllers should only handle HTTP concerns
                if (Str::endsWith($className, 'Controller')) {
                    $businessLogicMethods = array_filter($methods, function ($method) {
                        $methodName = $method->getName();
                        return preg_match('/calculate|process|validate|transform|convert/', $methodName);
                    });

                    if (count($businessLogicMethods) > 0) {
                        $methodNames = array_map(fn($m) => $m->getName(), $businessLogicMethods);
                        $srpViolations[] = "{$className}: Controller should not contain business logic methods: " . implode(', ', $methodNames);
                    }
                }

                // Services should focus on specific domain
                if (Str::endsWith($className, 'Service')) {
                    $content = file_get_contents($file->getPathname());

                    // Check for multiple domain responsibilities
                    $domainKeywords = ['user', 'workstream', 'communication', 'permission', 'release', 'checklist'];
                    $foundDomains = [];

                    foreach ($domainKeywords as $domain) {
                        if (preg_match_all("/{$domain}/i", $content) > 5) {
                            $foundDomains[] = $domain;
                        }
                    }

                    if (count($foundDomains) > 2) {
                        $srpViolations[] = "{$className}: Service handling multiple domains: " . implode(', ', $foundDomains);
                    }
                }

            } catch (\Exception $e) {
                continue;
            }
        }

        $this->assertLessThan(
            10,
            count($srpViolations),
            'Found Single Responsibility Principle violations: ' . implode(', ', array_slice($srpViolations, 0, 10))
        );
    }

    /**
     * Test that Open/Closed Principle (OCP) is supported through interfaces
     *
     * @test
     */
    public function architecture_should_support_open_closed_principle()
    {
        // Given: We check for extensibility through interfaces and inheritance
        $ocpIssues = [];

        // Check that services implement interfaces
        $serviceFiles = File::glob(app_path('Services/*.php'));
        foreach ($serviceFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Services should implement interfaces for extensibility
            if (!preg_match('/implements\s+\w+Interface/', $content)) {
                $ocpIssues[] = "{$className}: Service should implement interface for Open/Closed Principle compliance";
            }

            // Services should not be final (should be extensible)
            if (preg_match('/final\s+class/', $content)) {
                $ocpIssues[] = "{$className}: Service class should not be final to allow extension";
            }
        }

        // Check for strategy pattern implementation where appropriate
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for hardcoded business logic that should use strategy pattern
            $hardcodedPatterns = [
                'switch\s*\(\s*\$.*type.*\)' => 'Type-based logic should use strategy pattern',
                'if\s*\(\s*\$.*->type\s*===' => 'Type checking should use strategy pattern',
                'elseif.*type.*===' => 'Multiple type conditions should use strategy pattern'
            ];

            foreach ($hardcodedPatterns as $pattern => $suggestion) {
                if (preg_match("/{$pattern}/", $content)) {
                    $ocpIssues[] = "{$className}: {$suggestion}";
                }
            }
        }

        $this->assertLessThan(
            8,
            count($ocpIssues),
            'Found Open/Closed Principle issues: ' . implode(', ', array_slice($ocpIssues, 0, 8))
        );
    }

    /**
     * Test that Liskov Substitution Principle (LSP) is followed
     *
     * @test
     */
    public function inheritance_should_follow_liskov_substitution_principle()
    {
        // Given: We check inheritance hierarchies for LSP compliance
        $lspViolations = [];
        $classFiles = $this->getAllClassFiles();

        foreach ($classFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (!$className) continue;

            try {
                $reflection = new ReflectionClass($className);
                $parentClass = $reflection->getParentClass();

                if ($parentClass) {
                    // Check method signatures consistency
                    $parentMethods = $parentClass->getMethods(ReflectionMethod::IS_PUBLIC);
                    $childMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

                    foreach ($parentMethods as $parentMethod) {
                        $childMethod = $reflection->getMethod($parentMethod->getName());

                        if ($childMethod->getDeclaringClass()->getName() === $className) {
                            // Parameter count should not increase (contravariance)
                            if ($childMethod->getNumberOfParameters() > $parentMethod->getNumberOfParameters()) {
                                $lspViolations[] = "{$className}::{$childMethod->getName()}: Cannot add required parameters (LSP violation)";
                            }

                            // Return type should be covariant
                            $parentReturnType = $parentMethod->getReturnType();
                            $childReturnType = $childMethod->getReturnType();

                            if ($parentReturnType && $childReturnType) {
                                $parentTypeName = $parentReturnType->getName();
                                $childTypeName = $childReturnType->getName();

                                // Basic type compatibility check
                                if ($parentTypeName !== $childTypeName &&
                                    !is_subclass_of($childTypeName, $parentTypeName)) {
                                    $lspViolations[] = "{$className}::{$childMethod->getName()}: Return type should be covariant with parent";
                                }
                            }
                        }
                    }
                }

                // Check interface implementations
                $interfaces = $reflection->getInterfaces();
                foreach ($interfaces as $interface) {
                    $interfaceMethods = $interface->getMethods();
                    foreach ($interfaceMethods as $interfaceMethod) {
                        if ($reflection->hasMethod($interfaceMethod->getName())) {
                            $implementedMethod = $reflection->getMethod($interfaceMethod->getName());

                            // Interface method implementations should have compatible signatures
                            if ($implementedMethod->getNumberOfParameters() !== $interfaceMethod->getNumberOfParameters()) {
                                $lspViolations[] = "{$className}::{$implementedMethod->getName()}: Parameter count must match interface";
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                continue;
            }
        }

        $this->assertEmpty(
            $lspViolations,
            'Found Liskov Substitution Principle violations: ' . implode(', ', $lspViolations)
        );
    }

    /**
     * Test that Interface Segregation Principle (ISP) is followed
     *
     * @test
     */
    public function interfaces_should_follow_interface_segregation_principle()
    {
        // Given: We check interfaces for ISP compliance
        $ispViolations = [];
        $interfaceFiles = File::glob(app_path('Contracts/*.php'));

        foreach ($interfaceFiles as $file) {
            $content = file_get_contents($file);
            $interfaceName = basename($file, '.php');

            // Count methods in interface
            preg_match_all('/public function \w+\(/', $content, $methods);
            $methodCount = count($methods[0]);

            // Interfaces should be focused (not too many methods)
            if ($methodCount > 8) {
                $ispViolations[] = "{$interfaceName}: Interface has too many methods ({$methodCount}), consider splitting";
            }

            // Check for mixed concerns in interface methods
            preg_match_all('/public function (\w+)\(/', $content, $methodMatches);
            $methodNames = $methodMatches[1];

            $concerns = [];
            foreach ($methodNames as $methodName) {
                if (preg_match('/^(get|find|retrieve)/', $methodName)) {
                    $concerns[] = 'read';
                } elseif (preg_match('/^(create|store|save)/', $methodName)) {
                    $concerns[] = 'create';
                } elseif (preg_match('/^(update|modify)/', $methodName)) {
                    $concerns[] = 'update';
                } elseif (preg_match('/^(delete|remove)/', $methodName)) {
                    $concerns[] = 'delete';
                } elseif (preg_match('/^(validate|check|verify)/', $methodName)) {
                    $concerns[] = 'validation';
                } elseif (preg_match('/^(send|notify|communicate)/', $methodName)) {
                    $concerns[] = 'communication';
                }
            }

            $uniqueConcerns = array_unique($concerns);
            if (count($uniqueConcerns) > 3) {
                $ispViolations[] = "{$interfaceName}: Interface mixes too many concerns: " . implode(', ', $uniqueConcerns);
            }
        }

        // Check for fat interfaces that force unnecessary implementations
        $implementationFiles = File::glob(app_path('Services/*.php'));
        foreach ($implementationFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for empty method implementations (ISP violation indicator)
            preg_match_all('/public function \w+\([^)]*\)[^{]*\{\s*(\/\/.*?)?\s*\}/', $content, $emptyMethods);
            if (count($emptyMethods[0]) > 2) {
                $ispViolations[] = "{$className}: Has multiple empty method implementations, interface may be too fat";
            }
        }

        $this->assertLessThan(
            5,
            count($ispViolations),
            'Found Interface Segregation Principle violations: ' . implode(', ', $ispViolations)
        );
    }

    /**
     * Test that Dependency Inversion Principle (DIP) is followed
     *
     * @test
     */
    public function architecture_should_follow_dependency_inversion_principle()
    {
        // Given: We check for dependency inversion compliance
        $dipViolations = [];

        // High-level modules should not depend on low-level modules
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Controllers should depend on abstractions (interfaces), not concrete classes
            preg_match_all('/use App\\\\Models\\\\(\w+);/', $content, $modelUses);
            preg_match_all('/use App\\\\Services\\\\(\w+);/', $content, $serviceUses);

            // Check constructor injection
            preg_match('/public function __construct\(([^)]+)\)/', $content, $constructorMatch);
            if ($constructorMatch) {
                $parameters = $constructorMatch[1];

                // Should inject interfaces, not concrete classes
                foreach ($modelUses[1] as $model) {
                    if (Str::contains($parameters, $model)) {
                        $dipViolations[] = "{$className}: Should not inject concrete model {$model}, use repository interface";
                    }
                }

                // Should inject service interfaces
                foreach ($serviceUses[1] as $service) {
                    if (Str::contains($parameters, $service) && !Str::contains($parameters, 'Interface')) {
                        $dipViolations[] = "{$className}: Should inject {$service}Interface instead of concrete {$service}";
                    }
                }
            }

            // Check for direct instantiation (new keyword) in methods
            preg_match_all('/new\s+([A-Z]\w+)\(/', $content, $instantiations);
            foreach ($instantiations[1] as $instantiation) {
                if (in_array($instantiation, $modelUses[1]) || in_array($instantiation, $serviceUses[1])) {
                    $dipViolations[] = "{$className}: Should not instantiate {$instantiation} directly, use dependency injection";
                }
            }
        }

        // Services should depend on abstractions
        $serviceFiles = File::glob(app_path('Services/*.php'));
        foreach ($serviceFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Services should inject repository interfaces, not models directly
            preg_match_all('/use App\\\\Models\\\\(\w+);/', $content, $modelUses);
            if (count($modelUses[1]) > 2) {
                $dipViolations[] = "{$className}: Service depends on too many concrete models, use repository pattern";
            }

            // Check for static method calls to models (violation of DIP)
            preg_match_all('/(\w+)::(create|find|where|all)\(/', $content, $staticCalls);
            foreach ($staticCalls[1] as $staticCall) {
                if (in_array($staticCall, $modelUses[1])) {
                    $dipViolations[] = "{$className}: Should not call static methods on {$staticCall}, use repository";
                }
            }
        }

        $this->assertLessThan(
            12,
            count($dipViolations),
            'Found Dependency Inversion Principle violations: ' . implode(', ', array_slice($dipViolations, 0, 12))
        );
    }

    /**
     * Test that proper dependency injection patterns are used
     *
     * @test
     */
    public function proper_dependency_injection_patterns_should_be_used()
    {
        // Given: We check for proper DI patterns
        $diIssues = [];

        // Check service container bindings
        $serviceProviderFiles = File::glob(app_path('Providers/*.php'));
        $hasServiceBindings = false;

        foreach ($serviceProviderFiles as $file) {
            $content = file_get_contents($file);

            if (preg_match('/\$this->app->bind\(/', $content) || preg_match('/\$this->app->singleton\(/', $content)) {
                $hasServiceBindings = true;
                break;
            }
        }

        if (!$hasServiceBindings) {
            $diIssues[] = 'No service bindings found in service providers, DI container not properly configured';
        }

        // Check that controllers use constructor injection
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Controllers should have constructor for DI (except simple ones)
            $methodCount = preg_match_all('/public function \w+\(/', $content);
            if ($methodCount > 3 && !preg_match('/public function __construct\(/', $content)) {
                $diIssues[] = "{$className}: Complex controller should use constructor injection";
            }

            // Check for service locator anti-pattern
            if (preg_match('/app\(\w+::class\)|resolve\(\w+::class\)/', $content)) {
                $diIssues[] = "{$className}: Avoid service locator pattern, use constructor injection";
            }

            // Check for global function usage that should be injected
            $globalFunctions = ['auth\(\)', 'request\(\)', 'config\(\)', 'cache\(\)'];
            foreach ($globalFunctions as $function) {
                if (preg_match_all("/{$function}/", $content) > 2) {
                    $diIssues[] = "{$className}: Excessive use of global {$function}, consider injection";
                }
            }
        }

        // Check service injection patterns
        $serviceFiles = File::glob(app_path('Services/*.php'));
        foreach ($serviceFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Services should inject dependencies, not resolve them
            if (preg_match('/app\(|resolve\(|Container::/', $content)) {
                $diIssues[] = "{$className}: Service should use constructor injection, not service location";
            }
        }

        $this->assertLessThan(
            8,
            count($diIssues),
            'Found dependency injection pattern issues: ' . implode(', ', array_slice($diIssues, 0, 8))
        );
    }

    /**
     * Test that Laravel conventions are consistently applied
     *
     * @test
     */
    public function laravel_conventions_should_be_consistently_applied()
    {
        // Given: We check for Laravel convention compliance
        $conventionIssues = [];

        // Check Eloquent model conventions
        $modelFiles = File::glob(app_path('Models/*.php'));
        foreach ($modelFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Models should extend Model
            if (!preg_match('/extends Model/', $content)) {
                $conventionIssues[] = "Model {$className}: Should extend Illuminate\\Database\\Eloquent\\Model";
            }

            // Check for proper fillable/guarded
            if (!preg_match('/protected \$fillable|protected \$guarded/', $content)) {
                $conventionIssues[] = "Model {$className}: Should define \$fillable or \$guarded property";
            }

            // Check for proper relationship naming
            preg_match_all('/public function (\w+)\(\).*belongsTo|hasMany|hasOne/', $content, $relationships);
            foreach ($relationships[1] as $relationshipName) {
                if (preg_match('/hasMany|hasOne/', $content)) {
                    // Has relationships should be camelCase and descriptive
                    if (!ctype_lower($relationshipName[0])) {
                        $conventionIssues[] = "Model {$className}: Relationship {$relationshipName} should start with lowercase";
                    }
                }
            }
        }

        // Check controller conventions
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // API controllers should return JsonResponse
            preg_match_all('/public function \w+\([^)]*\):\s*(\w+)/', $content, $returnTypes);
            foreach ($returnTypes[1] as $returnType) {
                if (!in_array($returnType, ['JsonResponse', 'Response']) && $returnType !== '') {
                    $conventionIssues[] = "Controller {$className}: API methods should return JsonResponse";
                }
            }

            // Resource controllers should follow RESTful naming
            $restfulMethods = ['index', 'store', 'show', 'update', 'destroy'];
            preg_match_all('/public function (\w+)\(/', $content, $methods);
            $nonRestfulMethods = array_diff($methods[1], $restfulMethods, ['__construct']);

            if (count($nonRestfulMethods) > count($restfulMethods)) {
                $conventionIssues[] = "Controller {$className}: Consider using RESTful method names or separate controller";
            }
        }

        // Check request validation conventions
        $requestFiles = File::glob(app_path('Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Form requests should extend FormRequest
            if (!preg_match('/extends FormRequest/', $content)) {
                $conventionIssues[] = "Request {$className}: Should extend FormRequest";
            }

            // Should have rules method
            if (!preg_match('/public function rules\(\)/', $content)) {
                $conventionIssues[] = "Request {$className}: Should have rules() method";
            }
        }

        $this->assertLessThan(
            15,
            count($conventionIssues),
            'Found Laravel convention violations: ' . implode(', ', array_slice($conventionIssues, 0, 15))
        );
    }

    /**
     * Test that proper exception handling patterns are implemented
     *
     * @test
     */
    public function proper_exception_handling_patterns_should_be_implemented()
    {
        // Given: We check for proper exception handling
        $exceptionIssues = [];

        // Check for custom exception classes
        $exceptionPath = app_path('Exceptions');
        if (!File::exists($exceptionPath)) {
            $exceptionIssues[] = 'Missing app/Exceptions directory for custom exceptions';
        } else {
            $exceptionFiles = File::glob($exceptionPath . '/*.php');
            if (count($exceptionFiles) < 3) {
                $exceptionIssues[] = 'Should have custom exception classes for different error scenarios';
            }

            foreach ($exceptionFiles as $file) {
                $content = file_get_contents($file);
                $className = basename($file, '.php');

                // Custom exceptions should extend Exception
                if (!preg_match('/extends.*Exception/', $content)) {
                    $exceptionIssues[] = "Exception {$className}: Should extend Exception or its subclasses";
                }
            }
        }

        // Check exception handling in controllers
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for proper try-catch usage
            $tryBlocks = preg_match_all('/try\s*\{/', $content);
            $catchBlocks = preg_match_all('/catch\s*\(/', $content);

            if ($tryBlocks !== $catchBlocks) {
                $exceptionIssues[] = "{$className}: Mismatched try-catch blocks";
            }

            // Check for generic exception catching (anti-pattern)
            if (preg_match('/catch\s*\(\s*\\\\?Exception\s*\$/', $content)) {
                $exceptionIssues[] = "{$className}: Avoid catching generic Exception, catch specific exceptions";
            }

            // Check for empty catch blocks
            if (preg_match('/catch\s*\([^)]+\)\s*\{\s*\}/', $content)) {
                $exceptionIssues[] = "{$className}: Empty catch blocks should at least log the exception";
            }
        }

        // Check service exception handling
        $serviceFiles = File::glob(app_path('Services/*.php'));
        foreach ($serviceFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Services should throw domain-specific exceptions
            preg_match_all('/throw new (\w+)/', $content, $thrownExceptions);
            $genericExceptions = array_filter($thrownExceptions[1], function($exception) {
                return in_array($exception, ['Exception', 'RuntimeException', 'LogicException']);
            });

            if (count($genericExceptions) > 0) {
                $exceptionIssues[] = "{$className}: Should throw domain-specific exceptions instead of generic ones";
            }
        }

        // Check global exception handler
        $handlerFile = app_path('Exceptions/Handler.php');
        if (File::exists($handlerFile)) {
            $content = file_get_contents($handlerFile);

            // Should handle different exception types differently
            if (!preg_match('/instanceof.*Exception/', $content)) {
                $exceptionIssues[] = 'Exception handler should handle different exception types appropriately';
            }

            // Should return proper JSON responses for API
            if (!preg_match('/application\/json/', $content)) {
                $exceptionIssues[] = 'Exception handler should return JSON responses for API endpoints';
            }
        }

        $this->assertLessThan(
            10,
            count($exceptionIssues),
            'Found exception handling issues: ' . implode(', ', array_slice($exceptionIssues, 0, 10))
        );
    }

    /**
     * Helper method to get all class files
     */
    private function getAllClassFiles(): array
    {
        $directories = [
            app_path('Http/Controllers'),
            app_path('Models'),
            app_path('Services'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            app_path('Providers'),
        ];

        $files = [];
        foreach ($directories as $directory) {
            if (File::exists($directory)) {
                $files = array_merge($files, File::allFiles($directory));
            }
        }

        return array_filter($files, function ($file) {
            return $file->getExtension() === 'php';
        });
    }

    /**
     * Helper method to get class name from file
     */
    private function getClassNameFromFile($file): ?string
    {
        $content = file_get_contents($file->getPathname());

        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch);
        preg_match('/class\s+(\w+)/', $content, $classMatch);

        if (!$namespaceMatch || !$classMatch) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }

    /**
     * Helper method to analyze concerns in method names
     */
    private function analyzeConcerns(array $methods): array
    {
        $concerns = [];

        foreach ($methods as $method) {
            $methodName = $method->getName();

            if (preg_match('/^(get|find|show|index)/', $methodName)) {
                $concerns[] = 'data_retrieval';
            } elseif (preg_match('/^(store|create|save)/', $methodName)) {
                $concerns[] = 'data_creation';
            } elseif (preg_match('/^(update|edit|modify)/', $methodName)) {
                $concerns[] = 'data_modification';
            } elseif (preg_match('/^(delete|destroy|remove)/', $methodName)) {
                $concerns[] = 'data_deletion';
            } elseif (preg_match('/^(validate|check|verify)/', $methodName)) {
                $concerns[] = 'validation';
            } elseif (preg_match('/^(send|notify|email)/', $methodName)) {
                $concerns[] = 'communication';
            } elseif (preg_match('/^(process|calculate|compute)/', $methodName)) {
                $concerns[] = 'business_logic';
            } elseif (preg_match('/^(render|display|format)/', $methodName)) {
                $concerns[] = 'presentation';
            }
        }

        return array_unique($concerns);
    }
}