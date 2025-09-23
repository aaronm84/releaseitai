<?php
#!/usr/bin/env php
<?php

/**
 * Authorization Test Suite Runner
 *
 * This script provides a convenient way to run all authorization tests
 * with organized output and comprehensive coverage reporting.
 */

echo "\n";
echo "🔐 ReleaseIt Authorization Test Suite\n";
echo "=====================================\n\n";

$testGroups = [
    'Policy Unit Tests' => [
        'description' => 'Core authorization logic for each resource type',
        'path' => 'tests/Unit/Policies',
        'files' => [
            'WorkstreamPolicyTest.php',
            'ReleasePolicyTest.php',
            'FeedbackPolicyTest.php',
            'ContentPolicyTest.php',
            'UserPolicyTest.php'
        ]
    ],
    'Authorization Integration Tests' => [
        'description' => 'API endpoint authorization enforcement',
        'path' => 'tests/Feature/Authorization',
        'files' => [
            'WorkstreamAuthorizationTest.php',
            'ReleaseAuthorizationTest.php',
            'FeedbackAuthorizationTest.php'
        ]
    ],
    'Permission Inheritance Tests' => [
        'description' => 'Hierarchical permission inheritance',
        'path' => 'tests/Feature/Authorization',
        'files' => [
            'WorkstreamHierarchyPermissionTest.php'
        ]
    ],
    'Role-Based Permission Tests' => [
        'description' => 'Different user role permissions',
        'path' => 'tests/Feature/Authorization',
        'files' => [
            'RoleBasedPermissionTest.php'
        ]
    ],
    'Security Boundary Tests' => [
        'description' => 'Security vulnerabilities and attack prevention',
        'path' => 'tests/Feature/Authorization',
        'files' => [
            'SecurityBoundaryTest.php'
        ]
    ]
];

$commands = [
    'quick' => 'Quick run - all authorization tests',
    'detailed' => 'Detailed run with verbose output',
    'coverage' => 'Run with code coverage report',
    'specific' => 'Run specific test group',
    'help' => 'Show this help message'
];

// Get command line argument
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'quick':
        runQuickTests();
        break;
    case 'detailed':
        runDetailedTests();
        break;
    case 'coverage':
        runCoverageTests();
        break;
    case 'specific':
        runSpecificTests();
        break;
    case 'help':
    default:
        showHelp();
        break;
}

function runQuickTests()
{
    echo "🚀 Running Quick Authorization Tests...\n\n";

    $commands = [
        'php artisan test tests/Unit/Policies --parallel',
        'php artisan test tests/Feature/Authorization --parallel'
    ];

    foreach ($commands as $cmd) {
        echo "Executing: $cmd\n";
        system($cmd);
        echo "\n";
    }

    echo "✅ Quick test run completed!\n";
}

function runDetailedTests()
{
    global $testGroups;

    echo "📋 Running Detailed Authorization Tests...\n\n";

    foreach ($testGroups as $groupName => $groupInfo) {
        echo "📂 $groupName\n";
        echo "   " . $groupInfo['description'] . "\n";
        echo "   " . str_repeat('-', 50) . "\n";

        foreach ($groupInfo['files'] as $file) {
            $testPath = $groupInfo['path'] . '/' . $file;
            echo "   🧪 Testing: $file\n";

            $cmd = "php artisan test $testPath --verbose";
            $output = shell_exec($cmd);

            // Parse output for pass/fail counts
            if (strpos($output, 'FAILED') !== false) {
                echo "   ❌ Some tests failed\n";
            } else {
                echo "   ✅ All tests passed\n";
            }
        }
        echo "\n";
    }

    echo "📊 Detailed test run completed!\n";
}

function runCoverageTests()
{
    echo "📈 Running Authorization Tests with Coverage...\n\n";

    $commands = [
        'php artisan test tests/Unit/Policies tests/Feature/Authorization --coverage --min=80',
        'php artisan test tests/Unit/Policies tests/Feature/Authorization --coverage-html=coverage-html'
    ];

    foreach ($commands as $cmd) {
        echo "Executing: $cmd\n";
        system($cmd);
        echo "\n";
    }

    echo "📊 Coverage report generated in coverage-html/\n";
    echo "✅ Coverage test run completed!\n";
}

function runSpecificTests()
{
    global $testGroups;

    echo "🎯 Select a specific test group to run:\n\n";

    $i = 1;
    $groupNames = array_keys($testGroups);

    foreach ($testGroups as $groupName => $groupInfo) {
        echo "$i. $groupName\n";
        echo "   " . $groupInfo['description'] . "\n\n";
        $i++;
    }

    echo "Enter group number (1-" . count($testGroups) . "): ";
    $handle = fopen("php://stdin", "r");
    $selection = (int)trim(fgets($handle));
    fclose($handle);

    if ($selection >= 1 && $selection <= count($testGroups)) {
        $selectedGroup = $groupNames[$selection - 1];
        $groupInfo = $testGroups[$selectedGroup];

        echo "\n🏃 Running: $selectedGroup\n";
        echo str_repeat('=', 50) . "\n\n";

        foreach ($groupInfo['files'] as $file) {
            $testPath = $groupInfo['path'] . '/' . $file;
            echo "🧪 $file\n";
            $cmd = "php artisan test $testPath --verbose";
            system($cmd);
            echo "\n";
        }

        echo "✅ $selectedGroup tests completed!\n";
    } else {
        echo "❌ Invalid selection\n";
    }
}

function showHelp()
{
    global $commands;

    echo "📚 Authorization Test Suite Commands:\n\n";

    foreach ($commands as $cmd => $description) {
        echo sprintf("  %-12s %s\n", $cmd, $description);
    }

    echo "\nUsage Examples:\n";
    echo "  php tests/run_authorization_tests.php quick\n";
    echo "  php tests/run_authorization_tests.php coverage\n";
    echo "  php tests/run_authorization_tests.php specific\n\n";

    echo "📋 Test Coverage:\n";
    echo "  • Resource ownership and delegation\n";
    echo "  • Workstream hierarchy permissions\n";
    echo "  • Role-based access control\n";
    echo "  • Cross-tenant data isolation\n";
    echo "  • Security vulnerability prevention\n";
    echo "  • API endpoint authorization\n\n";

    echo "🔧 Prerequisites:\n";
    echo "  • Laravel test environment configured\n";
    echo "  • Database migrations run for testing\n";
    echo "  • Model factories implemented\n";
    echo "  • Policy classes ready for implementation\n\n";

    echo "For detailed documentation, see: tests/README_AUTHORIZATION_TESTS.md\n";
}

echo "\n";
?>