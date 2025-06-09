#!/bin/bash

# Test runner script for OginiSearch Laravel Scout Driver
# Usage: ./run-tests.sh [type]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to check if Ogini server is running
check_ogini_server() {
    if curl -s http://localhost:3000/health > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Display usage information
show_usage() {
    echo "OginiSearch Laravel Scout Driver - Test Runner"
    echo ""
    echo "Usage: $0 [TYPE]"
    echo ""
    echo "Test Types:"
    echo "  ci-safe     Run CI-safe tests (excludes real API calls) [DEFAULT]"
    echo "  unit        Run unit tests only"
    echo "  integration Run integration tests (may skip some without server)"
    echo "  api-calls   Run tests that require real API calls (needs Ogini server)"
    echo "  all         Run all tests including real API calls"
    echo "  coverage    Run tests with coverage report"
    echo ""
    echo "Examples:"
    echo "  $0              # Run CI-safe tests"
    echo "  $0 unit         # Run unit tests"
    echo "  $0 api-calls    # Run real API call tests"
    echo "  $0 coverage     # Generate coverage report"
    echo ""
}

# Main test runner
case "${1:-ci-safe}" in
    "ci-safe"|"ci"|"safe")
        print_info "Running CI-safe tests (excludes real API calls)..."
        vendor/bin/phpunit --testsuite=Unit --exclude-group=quality-assurance,benchmarks,load-tests,error-conditions,integration-tests,real-api-calls --testdox
        print_success "CI-safe tests completed"
        ;;
    
    "unit")
        print_info "Running unit tests..."
        vendor/bin/phpunit --testsuite=Unit --testdox
        print_success "Unit tests completed"
        ;;
    
    "integration")
        print_info "Running integration tests..."
        vendor/bin/phpunit --testsuite=Integration --testdox
        print_success "Integration tests completed"
        ;;
    
    "api-calls"|"api"|"real")
        print_info "Checking for Ogini server on localhost:3000..."
        if check_ogini_server; then
            print_success "Ogini server is running"
            print_info "Running tests that require real API calls..."
            vendor/bin/phpunit --group=real-api-calls --testdox
            print_success "Real API call tests completed"
        else
            print_warning "Ogini server not detected on localhost:3000"
            print_info "To run these tests:"
            echo "  1. Start Ogini server: docker run -p 3000:3000 ogini/server"
            echo "  2. Or run: npm start (if you have the Ogini server locally)"
            echo "  3. Then run: $0 api-calls"
            exit 1
        fi
        ;;
    
    "all"|"full")
        print_info "Running all tests..."
        
        # First run CI-safe tests
        print_info "Step 1/2: Running CI-safe tests..."
        vendor/bin/phpunit --configuration=phpunit.ci.xml --testsuite=CI-Safe --testdox
        
        # Then check for server and run API tests
        print_info "Step 2/2: Checking for real API call tests..."
        if check_ogini_server; then
            print_success "Ogini server detected - running real API call tests..."
            vendor/bin/phpunit --group=real-api-calls --testdox
            print_success "All tests completed successfully!"
        else
            print_warning "Ogini server not detected - skipping real API call tests"
            print_info "All available tests completed (API call tests skipped)"
        fi
        ;;
    
    "coverage")
        print_info "Running tests with coverage report..."
        vendor/bin/phpunit --configuration=phpunit.ci.xml --testsuite=CI-Safe --coverage-html=coverage-report
        print_success "Coverage report generated in ./coverage-report/"
        ;;
    
    "help"|"-h"|"--help")
        show_usage
        ;;
    
    *)
        print_error "Unknown test type: $1"
        echo ""
        show_usage
        exit 1
        ;;
esac 