#!/bin/bash

# ReleaseIt.ai Laravel Test Runner
# This script runs the comprehensive test suite for Laravel application setup

echo "=========================================="
echo "ReleaseIt.ai Laravel Application Tests"
echo "=========================================="
echo ""

# Check if PHPUnit is available
if ! command -v vendor/bin/phpunit &> /dev/null; then
    echo "❌ PHPUnit not found. Please run 'composer install' first."
    exit 1
fi

# Check if Laravel application exists
if [ ! -f "artisan" ]; then
    echo "❌ Laravel application not found. These tests are designed to validate a Laravel 11 installation."
    echo ""
    echo "To create a new Laravel application:"
    echo "composer create-project laravel/laravel:^11.0 ."
    echo ""
    echo "Then run this test suite again to validate the installation."
    exit 1
fi

echo "🧪 Running comprehensive Laravel application tests..."
echo ""

# Run specific test suites with detailed output
echo "1️⃣  Testing Laravel Application Structure..."
vendor/bin/phpunit tests/Feature/LaravelApplicationStructureTest.php --verbose

echo ""
echo "2️⃣  Testing Database Connection and Migrations..."
vendor/bin/phpunit tests/Feature/DatabaseConnectionTest.php --verbose

echo ""
echo "3️⃣  Testing Authentication Foundation..."
vendor/bin/phpunit tests/Feature/AuthenticationFoundationTest.php --verbose

echo ""
echo "4️⃣  Testing Core Endpoints..."
vendor/bin/phpunit tests/Feature/CoreEndpointsTest.php --verbose

echo ""
echo "5️⃣  Testing Environment Configuration..."
vendor/bin/phpunit tests/Feature/EnvironmentConfigurationTest.php --verbose

echo ""
echo "6️⃣  Testing Configuration Validation..."
vendor/bin/phpunit tests/Unit/ConfigurationValidationTest.php --verbose

echo ""
echo "=========================================="
echo "🏁 All tests completed!"
echo ""
echo "These tests validate that your Laravel 11 application:"
echo "✅ Has proper directory structure"
echo "✅ Can connect to database and run migrations"
echo "✅ Has authentication system configured"
echo "✅ Has core routes properly set up"
echo "✅ Has environment and service configuration"
echo ""
echo "If any tests fail, they indicate missing setup requirements"
echo "that need to be addressed for ReleaseIt.ai functionality."
echo "=========================================="