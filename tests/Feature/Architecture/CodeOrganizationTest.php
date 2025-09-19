<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Code Organization Tests
 *
 * These tests define the expected code organization patterns that eliminate
 * duplication and ensure proper separation of concerns after refactoring.
 */
class CodeOrganizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that duplicate code patterns are eliminated across controllers
     *
     * @test
     */
    public function duplicate_code_patterns_should_be_eliminated_across_controllers()
    {
        // Given: We analyze all controller files for duplicate patterns
        $controllerPath = app_path('Http/Controllers/Api');
        $controllerFiles = File::glob($controllerPath . '/*.php');

        $duplicatePatterns = [];
        $codeBlocks = [];

        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Extract method bodies for comparison
            preg_match_all('/public function \w+\([^)]*\)[^{]*\{([^}]+)\}/', $content, $matches);

            foreach ($matches[1] as $methodBody) {
                $normalizedBody = $this->normalizeCodeBlock($methodBody);

                if (strlen($normalizedBody) > 100) { // Only check substantial code blocks
                    $hash = md5($normalizedBody);

                    if (isset($codeBlocks[$hash])) {
                        $duplicatePatterns[] = [
                            'hash' => $hash,
                            'files' => [$codeBlocks[$hash], $className],
                            'content' => substr($normalizedBody, 0, 200) . '...'
                        ];
                    } else {
                        $codeBlocks[$hash] = $className;
                    }
                }
            }
        }

        // Then: There should be no significant duplicate code blocks
        $this->assertEmpty(
            $duplicatePatterns,
            'Found duplicate code patterns that should be refactored into shared services: ' .
            json_encode($duplicatePatterns, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Test that common functionality is abstracted into reusable components
     *
     * @test
     */
    public function common_functionality_should_be_abstracted_into_reusable_components()
    {
        // Given: We expect common functionality to be abstracted
        $expectedAbstractions = [
            'App\Services\PaginationService' => 'Pagination logic should be abstracted',
            'App\Services\PermissionService' => 'Permission checking should be abstracted',
            'App\Services\ResponseFormatterService' => 'Response formatting should be abstracted',
            'App\Traits\HasPermissions' => 'Permission logic should be available as trait',
            'App\Traits\Paginatable' => 'Pagination should be available as trait',
            'App\Http\Resources\ApiResponse' => 'API responses should be standardized',
        ];

        foreach ($expectedAbstractions as $class => $description) {
            // When: We check if the abstraction exists
            $exists = class_exists($class) || trait_exists($class);

            // Then: Common functionality should be abstracted
            $this->assertTrue(
                $exists,
                "{$description}. Expected class/trait: {$class}"
            );
        }
    }

    /**
     * Test that controllers don't contain duplicated permission checking logic
     *
     * @test
     */
    public function controllers_should_not_contain_duplicated_permission_checking_logic()
    {
        // Given: We analyze controllers for permission checking patterns
        $controllerPath = app_path('Http/Controllers/Api');
        $controllerFiles = File::glob($controllerPath . '/*.php');

        $permissionPatterns = [];
        $duplicatePermissionLogic = [];

        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for permission checking patterns
            $patterns = [
                'userCanAccessWorkstream',
                'userOwnsParentWorkstream',
                'getEffectivePermissions',
                'checkPermissions',
                'canAccess',
                'hasPermission'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all("/private function {$pattern}/", $content, $matches)) {
                    if (isset($permissionPatterns[$pattern])) {
                        $duplicatePermissionLogic[] = [
                            'pattern' => $pattern,
                            'files' => [$permissionPatterns[$pattern], $className]
                        ];
                    } else {
                        $permissionPatterns[$pattern] = $className;
                    }
                }
            }
        }

        // Then: Permission logic should not be duplicated across controllers
        $this->assertEmpty(
            $duplicatePermissionLogic,
            'Permission checking logic is duplicated across controllers and should be abstracted: ' .
            json_encode($duplicatePermissionLogic, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Test that pagination logic is not duplicated across controllers
     *
     * @test
     */
    public function pagination_logic_should_not_be_duplicated_across_controllers()
    {
        // Given: We analyze controllers for pagination patterns
        $controllerPath = app_path('Http/Controllers/Api');
        $controllerFiles = File::glob($controllerPath . '/*.php');

        $paginationDuplication = [];

        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for inline pagination logic that should be abstracted
            $inlinePaginationPatterns = [
                'paginate\(\$perPage\)',
                'currentPage\(\)',
                'lastPage\(\)',
                'perPage\(\)',
                'total\(\)',
                '\$perPage = min\(',
                'per_page.*100'
            ];

            $foundPatterns = [];
            foreach ($inlinePaginationPatterns as $pattern) {
                if (preg_match("/{$pattern}/", $content)) {
                    $foundPatterns[] = $pattern;
                }
            }

            if (count($foundPatterns) > 2) { // More than 2 pagination patterns suggests duplication
                $paginationDuplication[] = [
                    'file' => $className,
                    'patterns' => $foundPatterns
                ];
            }
        }

        // Then: Pagination logic should be abstracted, not duplicated
        $this->assertEmpty(
            $paginationDuplication,
            'Pagination logic is duplicated across controllers and should be abstracted into PaginationService: ' .
            json_encode($paginationDuplication, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Test that naming conventions are consistent across the application
     *
     * @test
     */
    public function naming_conventions_should_be_consistent_across_application()
    {
        // Given: We check various file types for naming consistency
        $namingIssues = [];

        // Check controller naming
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $className = basename($file, '.php');
            if (!Str::endsWith($className, 'Controller')) {
                $namingIssues[] = "Controller {$className} should end with 'Controller'";
            }
        }

        // Check model naming (should be singular)
        $modelFiles = File::glob(app_path('Models/*.php'));
        foreach ($modelFiles as $file) {
            $className = basename($file, '.php');
            if (Str::plural($className) === $className && $className !== 'User') {
                $namingIssues[] = "Model {$className} should be singular";
            }
        }

        // Check service naming
        if (File::exists(app_path('Services'))) {
            $serviceFiles = File::glob(app_path('Services/*.php'));
            foreach ($serviceFiles as $file) {
                $className = basename($file, '.php');
                if (!Str::endsWith($className, 'Service')) {
                    $namingIssues[] = "Service {$className} should end with 'Service'";
                }
            }
        }

        // Check request naming
        $requestFiles = File::glob(app_path('Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $className = basename($file, '.php');
            $validPatterns = ['Store', 'Update', 'Index', 'Delete', 'Get', 'Search'];
            $hasValidPattern = false;

            foreach ($validPatterns as $pattern) {
                if (Str::startsWith($className, $pattern)) {
                    $hasValidPattern = true;
                    break;
                }
            }

            if (!$hasValidPattern || !Str::endsWith($className, 'Request')) {
                $namingIssues[] = "Request {$className} should start with action verb and end with 'Request'";
            }
        }

        // Then: All naming conventions should be consistent
        $this->assertEmpty(
            $namingIssues,
            'Found naming convention violations: ' . implode(', ', $namingIssues)
        );
    }

    /**
     * Test that file organization follows Laravel conventions
     *
     * @test
     */
    public function file_organization_should_follow_laravel_conventions()
    {
        // Given: We expect proper Laravel directory structure
        $expectedDirectories = [
            app_path('Http/Controllers'),
            app_path('Http/Controllers/Api'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            app_path('Models'),
            app_path('Services'),
            app_path('Contracts'),
            app_path('Exceptions'),
            app_path('Traits'),
        ];

        foreach ($expectedDirectories as $directory) {
            // Then: Expected directories should exist
            $this->assertTrue(
                File::exists($directory),
                "Directory {$directory} should exist for proper organization"
            );
        }

        // Check that files are in correct directories
        $organizationIssues = [];

        // Controllers should be in Http/Controllers
        $allPhpFiles = File::allFiles(app_path());
        foreach ($allPhpFiles as $file) {
            $content = file_get_contents($file->getPathname());

            if (preg_match('/class \w+Controller extends/', $content)) {
                $expectedPath = 'Http' . DIRECTORY_SEPARATOR . 'Controllers';
                if (!Str::contains($file->getRelativePath(), $expectedPath)) {
                    $organizationIssues[] = "Controller {$file->getFilename()} should be in Http/Controllers directory";
                }
            }

            if (preg_match('/class \w+ extends Model/', $content)) {
                if ($file->getRelativePath() !== 'Models') {
                    $organizationIssues[] = "Model {$file->getFilename()} should be in Models directory";
                }
            }
        }

        $this->assertEmpty(
            $organizationIssues,
            'Found file organization issues: ' . implode(', ', $organizationIssues)
        );
    }

    /**
     * Test that separation of concerns is properly implemented
     *
     * @test
     */
    public function separation_of_concerns_should_be_properly_implemented()
    {
        // Given: We analyze files for proper separation of concerns
        $concernIssues = [];

        // Controllers should not contain business logic
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for business logic patterns that shouldn't be in controllers
            $businessLogicPatterns = [
                'DB::transaction' => 'Database transactions should be in services',
                'foreach.*update\(' => 'Bulk operations should be in services',
                'complex calculations' => 'Complex business logic should be in services',
                'validation logic' => 'Complex validation should be in Form Requests',
            ];

            // Check for database operations (except simple queries)
            if (preg_match_all('/DB::(transaction|beginTransaction|commit|rollback)/', $content, $matches)) {
                $concernIssues[] = "{$className}: Database transactions should be handled in service layer";
            }

            // Check for complex conditional logic
            if (preg_match_all('/if\s*\([^)]*&&[^)]*\|\|[^)]*\)/', $content, $matches)) {
                if (count($matches[0]) > 2) {
                    $concernIssues[] = "{$className}: Complex conditional logic should be in service layer";
                }
            }

            // Check for business calculations
            if (preg_match_all('/\$\w+\s*=\s*\$\w+\s*[\+\-\*\/]\s*\$\w+/', $content, $matches)) {
                if (count($matches[0]) > 1) {
                    $concernIssues[] = "{$className}: Business calculations should be in service layer";
                }
            }
        }

        // Models should not contain presentation logic
        $modelFiles = File::glob(app_path('Models/*.php'));
        foreach ($modelFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for presentation logic patterns
            if (preg_match('/format.*html|render.*view|display.*/', strtolower($content))) {
                $concernIssues[] = "{$className}: Models should not contain presentation logic";
            }
        }

        // Then: Separation of concerns should be properly maintained
        $this->assertEmpty(
            $concernIssues,
            'Found separation of concerns violations: ' . implode(', ', $concernIssues)
        );
    }

    /**
     * Test that reusable components are properly documented and tested
     *
     * @test
     */
    public function reusable_components_should_be_properly_documented_and_tested()
    {
        // Given: We check for reusable components
        $reusableComponents = [
            app_path('Services'),
            app_path('Traits'),
            app_path('Http/Resources'),
        ];

        $documentationIssues = [];

        foreach ($reusableComponents as $directory) {
            if (File::exists($directory)) {
                $files = File::allFiles($directory);

                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());
                        $className = basename($file->getFilename(), '.php');

                        // Check for class-level documentation
                        if (!preg_match('/\/\*\*.*@/', $content)) {
                            $documentationIssues[] = "{$className}: Reusable component should have PHPDoc documentation";
                        }

                        // Check for corresponding test file
                        $testPaths = [
                            base_path("tests/Unit/{$className}Test.php"),
                            base_path("tests/Feature/{$className}Test.php"),
                        ];

                        $hasTest = false;
                        foreach ($testPaths as $testPath) {
                            if (File::exists($testPath)) {
                                $hasTest = true;
                                break;
                            }
                        }

                        if (!$hasTest) {
                            $documentationIssues[] = "{$className}: Reusable component should have corresponding test file";
                        }
                    }
                }
            }
        }

        // Then: Reusable components should be documented and tested
        $this->assertEmpty(
            $documentationIssues,
            'Found documentation/testing issues with reusable components: ' . implode(', ', $documentationIssues)
        );
    }

    /**
     * Test that code structure follows dependency inversion principle
     *
     * @test
     */
    public function code_structure_should_follow_dependency_inversion_principle()
    {
        // Given: We analyze dependencies between layers
        $dependencyIssues = [];

        // Check that controllers depend on abstractions, not concrete classes
        $controllerFiles = File::glob(app_path('Http/Controllers/Api/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for direct dependencies on concrete classes
            preg_match_all('/use App\\\\Models\\\\(\w+);/', $content, $modelUses);
            preg_match_all('/new\s+(\w+)\(/', $content, $newInstances);

            // Controllers should not instantiate models directly in methods
            foreach ($newInstances[1] as $instantiation) {
                if (in_array($instantiation, $modelUses[1])) {
                    $dependencyIssues[] = "{$className}: Should not instantiate model {$instantiation} directly, use dependency injection";
                }
            }

            // Controllers should inject services through constructor
            if (!preg_match('/__construct\(.*Service/', $content) &&
                preg_match('/public function \w+\(.*\)\s*:\s*JsonResponse/', $content)) {
                $dependencyIssues[] = "{$className}: Should inject services through constructor for dependency inversion";
            }
        }

        // Check that services depend on contracts/interfaces
        if (File::exists(app_path('Services'))) {
            $serviceFiles = File::glob(app_path('Services/*.php'));
            foreach ($serviceFiles as $file) {
                $content = file_get_contents($file);
                $className = basename($file, '.php');

                // Services should implement interfaces
                if (!preg_match('/implements\s+\w+Interface/', $content)) {
                    $dependencyIssues[] = "{$className}: Service should implement interface for testability";
                }
            }
        }

        // Then: Code should follow dependency inversion principle
        $this->assertEmpty(
            $dependencyIssues,
            'Found dependency inversion violations: ' . implode(', ', $dependencyIssues)
        );
    }

    /**
     * Helper method to normalize code blocks for comparison
     */
    private function normalizeCodeBlock(string $code): string
    {
        // Remove comments and normalize whitespace
        $code = preg_replace('/\/\/.*$|\/\*.*?\*\//ms', '', $code);
        $code = preg_replace('/\s+/', ' ', $code);
        $code = trim($code);

        // Remove variable names to focus on structure
        $code = preg_replace('/\$\w+/', '$var', $code);

        return $code;
    }
}