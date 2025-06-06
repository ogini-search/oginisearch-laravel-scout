#!/bin/bash

# Test Code Quality workflow locally
echo "=== Testing Code Quality Workflow Locally ==="

echo "PHP Version: $(php -v | head -n 1)"
echo "PHPStan Version: $(vendor/bin/phpstan --version)"
echo "========================================"

echo "Starting PHPStan analysis at $(date)"
echo "PHPStan Config:"
cat phpstan.neon
echo "========================================"

# Run PHPStan with verbose output
echo "Running PHPStan..."
composer analyse -v

echo "========================================"
echo "PHPStan completed at $(date)"

# Run coding standards check if available
if [ -f "vendor/bin/phpcs" ]; then
    echo "Running coding standards check..."
    vendor/bin/phpcs --standard=PSR12 src/
fi

echo "=== Code Quality Test Complete ===" 