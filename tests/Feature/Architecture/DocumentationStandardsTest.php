<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * Documentation Standards Tests
 *
 * These tests define the expected documentation standards that should be
 * consistently applied across all code for maintainability and developer experience.
 */
class DocumentationStandardsTest extends TestCase
{
    /**
     * Test that all public methods have proper PHPDoc comments
     *
     * @test
     */
    public function all_public_methods_should_have_proper_phpdoc_comments()
    {
        // Given: We analyze all PHP classes for documentation
        $documentationIssues = [];
        $phpFiles = $this->getAllPhpFiles();

        foreach ($phpFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (!$className) continue;

            try {
                $reflection = new ReflectionClass($className);
                $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

                foreach ($methods as $method) {
                    // Skip magic methods and constructor for basic documentation check
                    if (Str::startsWith($method->getName(), '__')) {
                        continue;
                    }

                    $docComment = $method->getDocComment();

                    // Then: All public methods should have PHPDoc comments
                    if (!$docComment) {
                        $documentationIssues[] = "{$className}::{$method->getName()} missing PHPDoc comment";
                        continue;
                    }

                    // Check for required PHPDoc elements
                    $requiredElements = $this->getRequiredPhpDocElements($method);
                    foreach ($requiredElements as $element => $description) {
                        if (!preg_match($element, $docComment)) {
                            $documentationIssues[] = "{$className}::{$method->getName()} missing {$description}";
                        }
                    }
                }
            } catch (\Exception $e) {
                // Skip files that can't be reflected
                continue;
            }
        }

        // Limit output for readability
        $this->assertLessThan(
            50,
            count($documentationIssues),
            'Found PHPDoc documentation issues (showing first 50): ' .
            implode(', ', array_slice($documentationIssues, 0, 50))
        );
    }

    /**
     * Test that API endpoints have comprehensive documentation
     *
     * @test
     */
    public function api_endpoints_should_have_comprehensive_documentation()
    {
        // Given: We check API controllers for documentation
        $controllerPath = app_path('Http/Controllers/Api');
        if (!File::exists($controllerPath)) {
            $this->markTestSkipped('API controllers directory does not exist');
        }

        $controllerFiles = File::glob($controllerPath . '/*.php');
        $apiDocumentationIssues = [];

        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Look for API endpoint methods
            preg_match_all('/public function (\w+)\([^)]*\):\s*JsonResponse/', $content, $methods);

            foreach ($methods[1] as $methodName) {
                // Extract method documentation
                $pattern = "/\/\*\*.*?\*\/\s*public function {$methodName}/s";
                if (!preg_match($pattern, $content, $docMatch)) {
                    $apiDocumentationIssues[] = "{$className}::{$methodName} missing API documentation";
                    continue;
                }

                $docComment = $docMatch[0];

                // Check for required API documentation elements
                $requiredApiElements = [
                    '@param' => 'parameter documentation',
                    '@return' => 'return type documentation',
                    // '@throws' => 'exception documentation', // Optional but recommended
                ];

                // For store/update methods, check for validation documentation
                if (in_array($methodName, ['store', 'update'])) {
                    $requiredApiElements['@param.*Request'] = 'request parameter documentation';
                }

                foreach ($requiredApiElements as $pattern => $description) {
                    if (!preg_match("/{$pattern}/", $docComment)) {
                        $apiDocumentationIssues[] = "{$className}::{$methodName} missing {$description}";
                    }
                }

                // Check for meaningful description
                if (!preg_match('/\*\s+[A-Z][^*@]+/', $docComment)) {
                    $apiDocumentationIssues[] = "{$className}::{$methodName} missing meaningful description";
                }
            }
        }

        $this->assertLessThan(
            30,
            count($apiDocumentationIssues),
            'Found API documentation issues: ' . implode(', ', array_slice($apiDocumentationIssues, 0, 30))
        );
    }

    /**
     * Test that README and setup documentation is comprehensive
     *
     * @test
     */
    public function readme_and_setup_documentation_should_be_comprehensive()
    {
        // Given: We expect comprehensive project documentation
        $requiredFiles = [
            base_path('README.md') => 'Project README',
            base_path('CONTRIBUTING.md') => 'Contributing guidelines',
            base_path('INSTALLATION.md') => 'Installation instructions',
        ];

        $documentationIssues = [];

        foreach ($requiredFiles as $filePath => $description) {
            // Then: Required documentation files should exist
            if (!File::exists($filePath)) {
                $documentationIssues[] = "Missing {$description} at {$filePath}";
                continue;
            }

            $content = file_get_contents($filePath);

            // Check README.md specific requirements
            if (Str::endsWith($filePath, 'README.md')) {
                $readmeRequirements = [
                    '# ' => 'Project title',
                    '## Installation' => 'Installation section',
                    '## Usage' => 'Usage section',
                    '## API' => 'API documentation section',
                    '## Testing' => 'Testing section',
                    '## Contributing' => 'Contributing section',
                ];

                foreach ($readmeRequirements as $pattern => $requirement) {
                    if (!Str::contains($content, $pattern)) {
                        $documentationIssues[] = "README.md missing {$requirement}";
                    }
                }

                // Check for adequate length (basic comprehensive check)
                if (strlen($content) < 1000) {
                    $documentationIssues[] = "README.md should be more comprehensive (currently " . strlen($content) . " characters)";
                }
            }

            // Check INSTALLATION.md specific requirements
            if (Str::endsWith($filePath, 'INSTALLATION.md')) {
                $installationRequirements = [
                    'Requirements' => 'System requirements section',
                    'composer install' => 'Composer installation command',
                    'php artisan' => 'Laravel setup commands',
                    'database' => 'Database setup instructions',
                    '.env' => 'Environment configuration',
                ];

                foreach ($installationRequirements as $pattern => $requirement) {
                    if (!Str::contains(strtolower($content), strtolower($pattern))) {
                        $documentationIssues[] = "INSTALLATION.md missing {$requirement}";
                    }
                }
            }
        }

        $this->assertEmpty(
            $documentationIssues,
            'Found documentation completeness issues: ' . implode(', ', $documentationIssues)
        );
    }

    /**
     * Test that inline code documentation meets standards
     *
     * @test
     */
    public function inline_code_documentation_should_meet_standards()
    {
        // Given: We analyze code for inline documentation quality
        $phpFiles = $this->getAllPhpFiles();
        $inlineDocIssues = [];

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            $className = basename($file, '.php');

            // Check for complex methods that need inline comments
            preg_match_all('/public function \w+\([^)]*\)[^{]*\{([^}]+)\}/s', $content, $methods);

            foreach ($methods[1] as $methodBody) {
                $lineCount = substr_count($methodBody, "\n");

                // Methods over 20 lines should have inline comments
                if ($lineCount > 20) {
                    $commentCount = substr_count($methodBody, '//');
                    $blockCommentCount = substr_count($methodBody, '/*');

                    if ($commentCount + $blockCommentCount < 3) {
                        $inlineDocIssues[] = "{$className}: Complex method should have more inline comments";
                    }
                }

                // Check for TODO/FIXME comments that should be addressed
                if (preg_match_all('/(TODO|FIXME|HACK)/', $methodBody, $matches)) {
                    foreach ($matches[0] as $todoType) {
                        $inlineDocIssues[] = "{$className}: Contains {$todoType} comment that should be addressed";
                    }
                }
            }

            // Check for magic numbers that should be documented
            preg_match_all('/[^a-zA-Z_]\d{2,}[^a-zA-Z_]/', $content, $magicNumbers);
            if (count($magicNumbers[0]) > 3) {
                $inlineDocIssues[] = "{$className}: Contains magic numbers that should be documented or extracted to constants";
            }
        }

        $this->assertLessThan(
            20,
            count($inlineDocIssues),
            'Found inline documentation issues: ' . implode(', ', array_slice($inlineDocIssues, 0, 20))
        );
    }

    /**
     * Test that database schema is properly documented
     *
     * @test
     */
    public function database_schema_should_be_properly_documented()
    {
        // Given: We check migration files for documentation
        $migrationPath = database_path('migrations');
        $migrationFiles = File::glob($migrationPath . '/*.php');
        $schemaDocIssues = [];

        foreach ($migrationFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Check for table and column comments in migrations
            if (preg_match('/Schema::create\([\'"](\w+)[\'"]/', $content, $tableMatch)) {
                $tableName = $tableMatch[1];

                // Look for column definitions without comments
                preg_match_all('/\$table->(\w+)\([\'"](\w+)[\'"]/', $content, $columnMatches);

                foreach ($columnMatches[2] as $index => $columnName) {
                    $columnType = $columnMatches[1][$index];

                    // Skip standard Laravel columns
                    if (in_array($columnName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                        continue;
                    }

                    // Check if column has comment
                    $columnPattern = "/\\\$table->{$columnType}\(['\"]?{$columnName}['\"]?[^;]*->comment\(/";
                    if (!preg_match($columnPattern, $content)) {
                        $schemaDocIssues[] = "Migration {$fileName}: Column {$tableName}.{$columnName} should have comment";
                    }
                }

                // Check for foreign key documentation
                if (preg_match_all('/\$table->foreign\(/', $content, $foreignKeys)) {
                    foreach ($foreignKeys[0] as $foreignKey) {
                        if (!Str::contains($content, '->comment(')) {
                            $schemaDocIssues[] = "Migration {$fileName}: Foreign keys should be documented";
                            break;
                        }
                    }
                }
            }
        }

        // Check for database documentation file
        $dbDocFile = base_path('docs/database.md');
        if (!File::exists($dbDocFile)) {
            $schemaDocIssues[] = "Missing database documentation file at docs/database.md";
        }

        $this->assertLessThan(
            25,
            count($schemaDocIssues),
            'Found database schema documentation issues: ' . implode(', ', array_slice($schemaDocIssues, 0, 25))
        );
    }

    /**
     * Test that configuration files are well documented
     *
     * @test
     */
    public function configuration_files_should_be_well_documented()
    {
        // Given: We check configuration files for documentation
        $configPath = config_path();
        $configFiles = File::glob($configPath . '/*.php');
        $configDocIssues = [];

        foreach ($configFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Skip vendor config files
            if (in_array($fileName, ['app.php', 'database.php', 'mail.php'])) {
                continue;
            }

            // Check for configuration array documentation
            preg_match_all('/[\'"](\w+)[\'"] => /', $content, $configKeys);

            foreach ($configKeys[1] as $configKey) {
                // Look for comment above the configuration key
                $keyPattern = "/\/\*.*?\*\/\s*['\"]" . preg_quote($configKey) . "['\"]\s*=>/s";
                $linePattern = "/\/\/.*\n\s*['\"]" . preg_quote($configKey) . "['\"]\s*=>/";

                if (!preg_match($keyPattern, $content) && !preg_match($linePattern, $content)) {
                    $configDocIssues[] = "Config {$fileName}: Key '{$configKey}' should have documentation comment";
                }
            }

            // Check for file-level documentation
            if (!preg_match('/^<\?php\s*\/\*\*/', $content)) {
                $configDocIssues[] = "Config {$fileName}: Should have file-level documentation";
            }
        }

        $this->assertLessThan(
            15,
            count($configDocIssues),
            'Found configuration documentation issues: ' . implode(', ', array_slice($configDocIssues, 0, 15))
        );
    }

    /**
     * Test that test files are properly documented
     *
     * @test
     */
    public function test_files_should_be_properly_documented()
    {
        // Given: We check test files for documentation standards
        $testPaths = [
            base_path('tests/Unit'),
            base_path('tests/Feature'),
        ];

        $testDocIssues = [];

        foreach ($testPaths as $testPath) {
            if (!File::exists($testPath)) continue;

            $testFiles = File::allFiles($testPath);

            foreach ($testFiles as $file) {
                if ($file->getExtension() !== 'php') continue;

                $content = file_get_contents($file->getPathname());
                $className = basename($file->getFilename(), '.php');

                // Check for class-level documentation
                if (!preg_match('/\/\*\*.*?class.*?\*\//s', $content)) {
                    $testDocIssues[] = "Test {$className}: Should have class-level documentation";
                }

                // Check test method documentation
                preg_match_all('/public function (test_\w+|\w+.*test)\(\)/', $content, $testMethods);

                foreach ($testMethods[1] as $methodName) {
                    // Test methods should have @test annotation or test_ prefix
                    $methodPattern = "/\/\*\*.*?\*\/\s*public function {$methodName}/s";
                    if (preg_match($methodPattern, $content, $docMatch)) {
                        $docComment = $docMatch[0];

                        // Should have meaningful test description
                        if (!preg_match('/\*\s+Test.*|@test/', $docComment)) {
                            $testDocIssues[] = "Test {$className}::{$methodName}: Should have test description or @test annotation";
                        }
                    } else {
                        $testDocIssues[] = "Test {$className}::{$methodName}: Should have documentation comment";
                    }
                }
            }
        }

        $this->assertLessThan(
            40,
            count($testDocIssues),
            'Found test documentation issues: ' . implode(', ', array_slice($testDocIssues, 0, 40))
        );
    }

    /**
     * Test that code examples in documentation are valid
     *
     * @test
     */
    public function code_examples_in_documentation_should_be_valid()
    {
        // Given: We check documentation files for code examples
        $docFiles = [
            base_path('README.md'),
            base_path('CONTRIBUTING.md'),
            base_path('INSTALLATION.md'),
        ];

        $codeExampleIssues = [];

        foreach ($docFiles as $docFile) {
            if (!File::exists($docFile)) continue;

            $content = file_get_contents($docFile);

            // Extract code blocks
            preg_match_all('/```(\w+)?\n(.*?)```/s', $content, $codeBlocks);

            foreach ($codeBlocks[2] as $index => $codeBlock) {
                $language = $codeBlocks[1][$index] ?? '';

                // Check PHP code blocks for syntax errors
                if ($language === 'php') {
                    $phpCode = "<?php\n" . $codeBlock;

                    // Basic syntax check (this is a simplified check)
                    if (!$this->isValidPhpSyntax($phpCode)) {
                        $codeExampleIssues[] = basename($docFile) . ": PHP code example has syntax errors";
                    }
                }

                // Check bash/shell commands for common issues
                if (in_array($language, ['bash', 'shell', 'sh'])) {
                    $lines = explode("\n", $codeBlock);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || Str::startsWith($line, '#')) continue;

                        // Check for dangerous commands in documentation
                        $dangerousCommands = ['rm -rf', 'sudo rm', '> /dev/null'];
                        foreach ($dangerousCommands as $dangerous) {
                            if (Str::contains($line, $dangerous)) {
                                $codeExampleIssues[] = basename($docFile) . ": Contains potentially dangerous command: {$dangerous}";
                            }
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $codeExampleIssues,
            'Found issues with code examples in documentation: ' . implode(', ', $codeExampleIssues)
        );
    }

    /**
     * Helper method to get all PHP files in the application
     */
    private function getAllPhpFiles(): array
    {
        $directories = [
            app_path('Http/Controllers'),
            app_path('Models'),
            app_path('Services'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
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
     * Helper method to get class name from file path
     */
    private function getClassNameFromFile($file): ?string
    {
        $content = file_get_contents($file->getPathname());

        // Extract namespace and class name
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch);
        preg_match('/class\s+(\w+)/', $content, $classMatch);

        if (!$namespaceMatch || !$classMatch) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }

    /**
     * Helper method to get required PHPDoc elements for a method
     */
    private function getRequiredPhpDocElements(ReflectionMethod $method): array
    {
        $elements = [];

        // All methods should have description
        $elements['/\*\s+[A-Z][^*@]+/'] = 'method description';

        // Methods with parameters should document them
        if ($method->getNumberOfParameters() > 0) {
            $elements['/@param/'] = '@param documentation';
        }

        // Methods with return type should document it
        if ($method->hasReturnType()) {
            $elements['/@return/'] = '@return documentation';
        }

        return $elements;
    }

    /**
     * Helper method to check if PHP code has valid syntax
     */
    private function isValidPhpSyntax(string $code): bool
    {
        // This is a basic syntax check - in a real implementation you might want to use php -l
        $tokens = @token_get_all($code);
        return $tokens !== false;
    }
}