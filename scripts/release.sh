#!/bin/bash

# Ogini Laravel Scout Driver Release Script
# Usage: ./scripts/release.sh [version] [--dry-run]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_NAME="ogini-search/laravel-scout-driver"
MAIN_BRANCH="main"
CHANGELOG_FILE="CHANGELOG.md"
COMPOSER_FILE="composer.json"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to validate version format
validate_version() {
    local version=$1
    if [[ ! $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9]+(\.[0-9]+)?)?$ ]]; then
        print_error "Invalid version format: $version"
        print_error "Expected format: X.Y.Z or X.Y.Z-suffix"
        print_error "Examples: 1.0.0, 1.0.0-beta, 1.0.0-rc.1"
        exit 1
    fi
}

# Function to check if we're on the main branch
check_branch() {
    local current_branch=$(git branch --show-current)
    if [ "$current_branch" != "$MAIN_BRANCH" ]; then
        print_error "Must be on $MAIN_BRANCH branch to create a release"
        print_error "Current branch: $current_branch"
        exit 1
    fi
}

# Function to check if working directory is clean
check_working_directory() {
    if [ -n "$(git status --porcelain)" ]; then
        print_error "Working directory is not clean"
        print_error "Please commit or stash your changes before creating a release"
        git status --short
        exit 1
    fi
}

# Function to check if tag already exists
check_tag_exists() {
    local version=$1
    local tag="v$version"
    
    if git tag -l | grep -q "^$tag$"; then
        print_error "Tag $tag already exists"
        exit 1
    fi
}

# Function to run tests
run_tests() {
    print_status "Running tests..."
    
    if ! composer test; then
        print_error "Tests failed. Cannot proceed with release."
        exit 1
    fi
    
    print_success "All tests passed"
}

# Function to update changelog
update_changelog() {
    local version=$1
    local date=$(date +%Y-%m-%d)
    
    print_status "Updating changelog..."
    
    # Create a temporary file
    local temp_file=$(mktemp)
    
    # Check if changelog exists
    if [ ! -f "$CHANGELOG_FILE" ]; then
        print_error "Changelog file not found: $CHANGELOG_FILE"
        exit 1
    fi
    
    # Replace [Unreleased] with version and date
    sed "s/## \[Unreleased\]/## [Unreleased]\n\n## [$version] - $date/" "$CHANGELOG_FILE" > "$temp_file"
    
    # Replace TBD with actual date in existing version if present
    sed -i.bak "s/## \[$version\] - TBD/## [$version] - $date/" "$temp_file"
    
    # Move temp file back
    mv "$temp_file" "$CHANGELOG_FILE"
    
    print_success "Changelog updated for version $version"
}

# Function to commit and tag
commit_and_tag() {
    local version=$1
    local tag="v$version"
    
    print_status "Committing changelog and creating tag..."
    
    # Add changelog to git
    git add "$CHANGELOG_FILE"
    
    # Commit if there are changes
    if ! git diff --cached --quiet; then
        git commit -m "chore: update changelog for $version"
    fi
    
    # Create tag
    git tag -a "$tag" -m "Release $version"
    
    print_success "Created tag $tag"
}

# Function to generate release notes
generate_release_notes() {
    local version=$1
    local temp_file=$(mktemp)
    
    print_status "Generating release notes..."
    
    # Extract version section from changelog
    awk "/## \[$version\]/,/## \[.*\]/ { if (/## \[.*\]/ && !/## \[$version\]/) exit; print }" "$CHANGELOG_FILE" | head -n -1 > "$temp_file"
    
    echo "Release notes saved to: $temp_file"
    cat "$temp_file"
    
    rm "$temp_file"
}

# Function to show help
show_help() {
    cat << EOF
Ogini Laravel Scout Driver Release Script

Usage: $0 [version] [options]

Arguments:
  version     The version to release (e.g., 1.0.0, 1.0.0-beta)

Options:
  --dry-run   Show what would be done without making changes
  --help      Show this help message

Examples:
  $0 1.0.0                 # Release version 1.0.0
  $0 1.0.0-beta --dry-run  # Preview release of 1.0.0-beta
  $0 --help                # Show this help

Requirements:
  - Must be on $MAIN_BRANCH branch
  - Working directory must be clean
  - All tests must pass
  - Tag must not already exist

The script will:
  1. Validate version format
  2. Check branch and working directory
  3. Run tests
  4. Update changelog
  5. Commit changes and create git tag
  6. Generate release notes

After running this script, push the tag to trigger the GitHub release workflow:
  git push origin v[version]
EOF
}

# Main function
main() {
    local version=""
    local dry_run=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                dry_run=true
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            -*)
                print_error "Unknown option: $1"
                show_help
                exit 1
                ;;
            *)
                if [ -z "$version" ]; then
                    version=$1
                else
                    print_error "Multiple versions specified"
                    show_help
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Check if version is provided
    if [ -z "$version" ]; then
        print_error "Version is required"
        show_help
        exit 1
    fi
    
    # Display header
    echo "========================================"
    echo "  Ogini Laravel Scout Driver Release"
    echo "========================================"
    echo ""
    
    if [ "$dry_run" = true ]; then
        print_warning "DRY RUN MODE - No changes will be made"
        echo ""
    fi
    
    print_status "Preparing release for version: $version"
    echo ""
    
    # Validate version
    validate_version "$version"
    
    # Check prerequisites
    check_branch
    check_working_directory
    check_tag_exists "$version"
    
    # Run tests
    run_tests
    
    if [ "$dry_run" = true ]; then
        print_warning "DRY RUN: Would update changelog for version $version"
        print_warning "DRY RUN: Would commit changes and create tag v$version"
        print_warning "DRY RUN: Would generate release notes"
        echo ""
        print_status "To actually perform the release, run:"
        print_status "$0 $version"
        print_status ""
        print_status "Then push the tag to trigger GitHub release:"
        print_status "git push origin v$version"
    else
        # Update changelog
        update_changelog "$version"
        
        # Commit and tag
        commit_and_tag "$version"
        
        # Generate release notes
        generate_release_notes "$version"
        
        echo ""
        print_success "Release $version prepared successfully!"
        echo ""
        print_status "Next steps:"
        print_status "1. Push the tag to trigger GitHub release workflow:"
        print_status "   git push origin v$version"
        print_status ""
        print_status "2. The GitHub workflow will:"
        print_status "   - Run tests"
        print_status "   - Create GitHub release"
        print_status "   - Update Packagist (if configured)"
        echo ""
    fi
}

# Run main function with all arguments
main "$@" 